<?php

use App\Models\AiModel;
use App\Models\KnowledgeBase;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\KnowledgeRetrievalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

function p63_embeddingModel(): AiModel
{
    return AiModel::create([
        'name' => 'Emb_' . uniqid(),
        'model_id' => 'text-embedding-3-large',
        'model_type' => 'embedding',
        'api_url' => 'https://api.test',
        'api_key' => 'k',
        'status' => 'active',
        'failover_priority' => 1,
    ]);
}

/**
 * 智能 mock：根据请求体 input 数组长度动态返回相同数量的 embedding 向量。
 */
function p63_mockEmbed(?int $unused = null, int $dims = 32): void
{
    Http::fake([
        '*/embeddings' => function (\Illuminate\Http\Client\Request $request) use ($dims) {
            $body   = json_decode($request->body(), true) ?: [];
            $inputs = $body['input'] ?? [];
            $count  = is_array($inputs) ? count($inputs) : 1;
            $data = [];
            for ($i = 0; $i < $count; $i++) {
                $v = array_fill(0, $dims, 0.0);
                $v[$i % $dims] = 1.0;
                $data[] = ['index' => $i, 'embedding' => $v];
            }
            return Http::response(['data' => $data], 200);
        },
    ]);
}

describe('EmbeddingService', function () {
    it('throws when no embedding model is configured', function () {
        $svc = new EmbeddingService();
        expect(fn () => $svc->generateEmbeddings(['hello']))
            ->toThrow(RuntimeException::class, '未配置可用的 embedding 模型');
    });

    it('pads vectors to 3072 dimensions', function () {
        p63_embeddingModel();
        p63_mockEmbed(1, dims: 8);

        $svc = new EmbeddingService();
        $r = $svc->generateEmbeddings(['hello']);
        expect(count($r))->toBe(1);
        expect($r[0]['dimensions'])->toBe(EmbeddingService::STORAGE_DIMENSIONS);
        expect($r[0]['vector_literal'])->toStartWith('[');
        expect($r[0]['vector_literal'])->toContain('1');  // 0.0/1.0 都会有 1
    });

    it('uses default model when none passed', function () {
        $m = p63_embeddingModel();
        p63_mockEmbed(2);

        $svc = new EmbeddingService();
        $r = $svc->generateEmbeddings(['one', 'two']);
        expect($r[0]['model_id'])->toBe($m->id);
        expect(count($r))->toBe(2);
    });

    it('errors when API count mismatches input count', function () {
        p63_embeddingModel();
        Http::fake(['*/embeddings' => Http::response(['data' => []], 200)]);

        expect(fn () => (new EmbeddingService())->generateEmbeddings(['x', 'y']))
            ->toThrow(RuntimeException::class, '数量不匹配');
    });
});

describe('KnowledgeRetrievalService::chunkText', function () {
    it('returns empty for blank input', function () {
        $r = (new KnowledgeRetrievalService())->chunkText('');
        expect($r)->toBe([]);
    });

    it('keeps paragraphs together under maxChars', function () {
        $svc = new KnowledgeRetrievalService();
        $r = $svc->chunkText("para1\n\npara2", maxChars: 100);
        expect(count($r))->toBe(1);
        expect($r[0])->toContain('para1');
        expect($r[0])->toContain('para2');
    });

    it('splits long paragraphs by maxChars', function () {
        $svc = new KnowledgeRetrievalService();
        $long = str_repeat('a', 250);
        $r = $svc->chunkText($long, maxChars: 100);
        expect(count($r))->toBe(3);    // 100 + 100 + 50
    });

    it('starts new chunk when next paragraph would overflow', function () {
        $svc = new KnowledgeRetrievalService();
        // 第二段 80 字符 > 50 maxChars → 触发单段切分；前面的 "aaa" 单独一段
        $r = $svc->chunkText("aaa\n\n" . str_repeat('b', 80), maxChars: 50);
        expect(count($r))->toBeGreaterThanOrEqual(2);
    });
});

describe('KnowledgeRetrievalService::syncChunks', function () {
    it('inserts chunks with embedding_vector cast', function () {
        p63_embeddingModel();
        $kb = KnowledgeBase::create(['name' => 'KB_' . uniqid()]);
        p63_mockEmbed();

        // 两段都 > 默认 900 字符强制切，每段独立成块
        $svc = new KnowledgeRetrievalService();
        $content = str_repeat('aaa ', 300) . "\n\n" . str_repeat('bbb ', 300);
        $expectedChunks = count($svc->chunkText($content));

        $n = $svc->syncChunks($kb->id, $content);
        expect($n)->toBe($expectedChunks);
        $rows = DB::table('knowledge_chunks')
            ->where('knowledge_base_id', $kb->id)
            ->orderBy('chunk_index')
            ->get();
        expect($rows->count())->toBe($expectedChunks);
        expect((int) $rows[0]->chunk_index)->toBe(0);

        // 验证 embedding_vector 写入了 PG vector 类型
        $rawVec = DB::selectOne(
            "SELECT embedding_vector::text AS v FROM knowledge_chunks WHERE id = ?",
            [$rows[0]->id]
        );
        expect($rawVec->v)->toStartWith('[');
    });

    it('replaces previous chunks (delete then insert)', function () {
        p63_embeddingModel();
        $kb = KnowledgeBase::create(['name' => 'KB_' . uniqid()]);
        p63_mockEmbed();
        $svc = new KnowledgeRetrievalService();

        // 第一次写：长内容产生多个 chunk
        $longContent = str_repeat('block-A ', 200) . "\n\n" . str_repeat('block-B ', 200) . "\n\n" . str_repeat('block-C ', 200);
        $svc->syncChunks($kb->id, $longContent);
        $firstCount = DB::table('knowledge_chunks')->where('knowledge_base_id', $kb->id)->count();
        expect($firstCount)->toBeGreaterThan(0);

        // 第二次写：短内容
        $svc->syncChunks($kb->id, "only one short paragraph");
        $secondCount = DB::table('knowledge_chunks')->where('knowledge_base_id', $kb->id)->count();
        expect($secondCount)->toBe(1);    // chunkText 短文本只 1 块
    });

    it('clears chunks when content is empty', function () {
        p63_embeddingModel();
        $kb = KnowledgeBase::create(['name' => 'KB_' . uniqid()]);
        p63_mockEmbed();

        $svc = new KnowledgeRetrievalService();
        $svc->syncChunks($kb->id, str_repeat('content ', 200));
        expect(DB::table('knowledge_chunks')->where('knowledge_base_id', $kb->id)->count())->toBeGreaterThan(0);

        $n = $svc->syncChunks($kb->id, "");
        expect($n)->toBe(0);
        expect(DB::table('knowledge_chunks')->where('knowledge_base_id', $kb->id)->count())->toBe(0);
    });
});

describe('KnowledgeRetrievalService::fetchContext', function () {
    it('returns empty for non-existent kb', function () {
        $svc = new KnowledgeRetrievalService();
        $r = $svc->fetchContext(999999, 'foo');
        expect($r['context'])->toBe('');
        expect($r['chunks'])->toBe([]);
    });

    it('returns chunk content with header label', function () {
        p63_embeddingModel();
        $kb = KnowledgeBase::create(['name' => 'KB_' . uniqid()]);
        p63_mockEmbed(2);

        $svc = new KnowledgeRetrievalService();
        $svc->syncChunks($kb->id, "first paragraph about web3\n\nsecond paragraph about defi");

        // 用同样的 embed model mock 查询
        p63_mockEmbed(1);   // query embedding 也走 mock
        $r = $svc->fetchContext($kb->id, 'web3 query');
        expect($r['context'])->toContain('【知识片段');
        expect(count($r['chunks']))->toBeGreaterThanOrEqual(1);
    });

    it('respects maxChars budget', function () {
        p63_embeddingModel();
        $kb = KnowledgeBase::create(['name' => 'KB_' . uniqid()]);
        p63_mockEmbed(3);

        $svc = new KnowledgeRetrievalService();
        // 3 块各 100 字
        $svc->syncChunks($kb->id, str_repeat('a', 100) . "\n\n" . str_repeat('b', 100) . "\n\n" . str_repeat('c', 100));

        p63_mockEmbed(1);
        $r = $svc->fetchContext($kb->id, 'q', limit: 4, maxChars: 150);
        // 第一块就 100 字 < 150，可以放进；第二块加进来会超过 150，跳过
        expect(count($r['chunks']))->toBe(1);
    });

    it('empty query falls back to chunk_index ordering', function () {
        p63_embeddingModel();
        $kb = KnowledgeBase::create(['name' => 'KB_' . uniqid()]);
        p63_mockEmbed(2);

        $svc = new KnowledgeRetrievalService();
        $svc->syncChunks($kb->id, "alpha\n\nbeta");

        $r = $svc->fetchContext($kb->id, '');
        expect($r['context'])->toContain('alpha');
        expect($r['context'])->toContain('beta');
    });
});
