<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 知识库 RAG 服务（与老 includes/knowledge-retrieval.php 对齐主流程）。
 *
 *   - chunkText: 按 maxChars（默认 900）分块
 *   - syncChunks: 删旧 → 生成 embeddings → 写 knowledge_chunks（含 vector(3072) 列）
 *   - fetchContext: 用 query embedding + pgvector cosine 距离 (<=>) 取 top-N
 *
 * 与老版本简化点（Phase 6.3 不实现，后续按需补）：
 *   - 不做"轻量回退向量"（embedding 模型缺失时直接抛错）
 *   - 不做 lexical/词频混合打分（只用 vector 距离）
 *   - 不做 token-level 精确切分（用 mb_substr 按字符数分）
 *
 * 如果未来真要这些功能，参照老 knowledge_retrieval_build_vector / lexical_score 实现。
 */
class KnowledgeRetrievalService
{
    public const DEFAULT_CHUNK_CHARS = 900;
    public const DEFAULT_CONTEXT_CHARS = 2400;
    public const DEFAULT_CONTEXT_LIMIT = 4;

    public function __construct(
        private readonly EmbeddingService $embeddings = new EmbeddingService(),
    ) {}

    /**
     * 按字符数切分长文本。会保留段落结构（先按 \n\n 分，再按 maxChars 强制切）。
     *
     * @return string[]
     */
    public function chunkText(string $content, int $maxChars = self::DEFAULT_CHUNK_CHARS): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }
        $paragraphs = preg_split('/\n{2,}/u', $content) ?: [$content];

        $chunks = [];
        $buf = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }

            // 若单段超长直接切
            if (mb_strlen($para) > $maxChars) {
                if ($buf !== '') {
                    $chunks[] = $buf;
                    $buf = '';
                }
                foreach ($this->sliceByChars($para, $maxChars) as $piece) {
                    $chunks[] = $piece;
                }
                continue;
            }

            $candidate = $buf === '' ? $para : ($buf . "\n\n" . $para);
            if (mb_strlen($candidate) > $maxChars) {
                $chunks[] = $buf;
                $buf = $para;
            } else {
                $buf = $candidate;
            }
        }
        if ($buf !== '') {
            $chunks[] = $buf;
        }
        return $chunks;
    }

    /**
     * 把 KB 内容切块 + 生成 embedding + 写入 knowledge_chunks（会先清旧 chunks）。
     * 返回写入的 chunk 数。
     */
    public function syncChunks(int $knowledgeBaseId, string $content): int
    {
        if ($knowledgeBaseId <= 0) {
            return 0;
        }
        $kb = KnowledgeBase::find($knowledgeBaseId);
        if (!$kb) {
            throw new RuntimeException('知识库不存在');
        }

        $chunks = $this->chunkText($content);
        if ($chunks === []) {
            // 清空所有 chunks
            DB::table('knowledge_chunks')->where('knowledge_base_id', $knowledgeBaseId)->delete();
            return 0;
        }

        $embeddings = $this->embeddings->generateEmbeddings($chunks);
        if (count($embeddings) !== count($chunks)) {
            throw new RuntimeException('embedding 数量与 chunk 数量不一致');
        }

        return DB::transaction(function () use ($knowledgeBaseId, $chunks, $embeddings) {
            DB::table('knowledge_chunks')->where('knowledge_base_id', $knowledgeBaseId)->delete();

            $inserted = 0;
            foreach ($chunks as $i => $text) {
                $embedding = $embeddings[$i];
                DB::statement('
                    INSERT INTO knowledge_chunks (
                        knowledge_base_id, chunk_index, content, content_hash, token_count,
                        embedding_json, embedding_model_id, embedding_dimensions, embedding_provider,
                        embedding_vector, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CAST(? AS vector), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ', [
                    $knowledgeBaseId,
                    $i,
                    $text,
                    hash('sha256', $text),
                    mb_strlen($text),
                    json_encode($embedding['vector'], JSON_UNESCAPED_UNICODE),
                    $embedding['model_id'],
                    $embedding['dimensions'],
                    $embedding['provider'],
                    $embedding['vector_literal'],
                ]);
                $inserted++;
            }
            return $inserted;
        });
    }

    /**
     * 检索：用 query 生成 embedding，从 knowledge_chunks 取最相关的若干 chunk，
     * 拼成 context 字符串（按 maxChars 截断）。
     *
     * @return array{context:string, chunks: array<int, array{id:int, chunk_index:int, content:string, distance:float}>}
     */
    public function fetchContext(
        int $knowledgeBaseId,
        string $query,
        int $limit = self::DEFAULT_CONTEXT_LIMIT,
        int $maxChars = self::DEFAULT_CONTEXT_CHARS,
    ): array {
        $query = trim($query);
        if ($knowledgeBaseId <= 0) {
            return ['context' => '', 'chunks' => []];
        }

        // 没 query 走 fallback：取 chunk_index 前 N 条
        if ($query === '') {
            $fallback = DB::table('knowledge_chunks')
                ->where('knowledge_base_id', $knowledgeBaseId)
                ->orderBy('chunk_index')
                ->limit($limit)
                ->get(['id', 'chunk_index', 'content']);
            return $this->buildContext($fallback->all(), $maxChars);
        }

        try {
            $vectors = $this->embeddings->generateEmbeddings([$query]);
            $literal = $vectors[0]['vector_literal'] ?? '';
            if ($literal === '') {
                throw new RuntimeException('query embedding 为空');
            }

            $rows = DB::select('
                SELECT id, chunk_index, content,
                       (embedding_vector <=> CAST(? AS vector)) AS distance
                FROM knowledge_chunks
                WHERE knowledge_base_id = ?
                  AND embedding_vector IS NOT NULL
                ORDER BY embedding_vector <=> CAST(? AS vector), chunk_index ASC
                LIMIT ?
            ', [$literal, $knowledgeBaseId, $literal, $limit]);

            return $this->buildContext($rows, $maxChars);
        } catch (\Throwable $e) {
            Log::warning('pgvector knowledge retrieval failed, falling back to chunk_index order', [
                'kb_id' => $knowledgeBaseId,
                'error' => $e->getMessage(),
            ]);
            $fallback = DB::table('knowledge_chunks')
                ->where('knowledge_base_id', $knowledgeBaseId)
                ->orderBy('chunk_index')
                ->limit($limit)
                ->get(['id', 'chunk_index', 'content']);
            return $this->buildContext($fallback->all(), $maxChars);
        }
    }

    /**
     * 把 chunk 列表拼成 context 字符串，按 maxChars 截断。
     *
     * @param array<int, object> $rows
     */
    private function buildContext(array $rows, int $maxChars): array
    {
        if ($rows === []) {
            return ['context' => '', 'chunks' => []];
        }

        // 按 chunk_index 升序展示，保留原文顺序
        usort($rows, fn ($a, $b) => (int) ($a->chunk_index ?? 0) <=> (int) ($b->chunk_index ?? 0));

        $parts = [];
        $selected = [];
        $charCount = 0;

        foreach ($rows as $row) {
            $content = trim((string) ($row->content ?? ''));
            if ($content === '') {
                continue;
            }
            $next = $charCount + mb_strlen($content);
            if ($parts !== [] && $next > $maxChars) {
                continue;
            }
            $idx = (int) ($row->chunk_index ?? count($parts));
            $parts[] = "【知识片段" . (count($parts) + 1) . "】\n" . $content;
            $selected[] = [
                'id'          => (int) ($row->id ?? 0),
                'chunk_index' => $idx,
                'content'     => $content,
                'distance'    => isset($row->distance) ? (float) $row->distance : 0.0,
            ];
            $charCount = $next;
        }

        return [
            'context' => trim(implode("\n\n", $parts)),
            'chunks'  => $selected,
        ];
    }

    /**
     * 长段落强切：当单段 > maxChars 时按字符切。
     *
     * @return string[]
     */
    private function sliceByChars(string $text, int $maxChars): array
    {
        $pieces = [];
        $offset = 0;
        $len = mb_strlen($text);
        while ($offset < $len) {
            $pieces[] = mb_substr($text, $offset, $maxChars);
            $offset += $maxChars;
        }
        return $pieces;
    }
}
