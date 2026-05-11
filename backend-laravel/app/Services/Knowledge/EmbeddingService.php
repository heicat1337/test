<?php

namespace App\Services\Knowledge;

use App\Models\AiModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Embedding 服务（与老 includes/embedding-service.php 对齐）。
 *
 * 用 AiModel 里 model_type='embedding' 的活动模型生成向量。
 * 与 OpenAI embeddings/v1/embeddings 端点协议兼容。
 */
class EmbeddingService
{
    public const REQUEST_TIMEOUT_SECONDS = 120;
    public const STORAGE_DIMENSIONS = 3072;

    /**
     * 取默认 embedding 模型（优先 ai_models 表 active + embedding type，
     * 否则取 site_settings.default_embedding_model_id）。
     */
    public function getDefaultModel(): ?AiModel
    {
        $configuredId = (int) \App\Models\SiteSetting::value('default_embedding_model_id', '0');
        if ($configuredId > 0) {
            $model = AiModel::query()
                ->whereKey($configuredId)
                ->where('status', 'active')
                ->where('model_type', 'embedding')
                ->first();
            if ($model) {
                return $model;
            }
        }
        // fallback：库里任意一个 embedding 模型
        return AiModel::query()
            ->where('status', 'active')
            ->where('model_type', 'embedding')
            ->orderBy('failover_priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * 批量生成 embedding 向量。
     *
     * @param string[] $inputs
     * @return array<int, array{vector:float[], vector_literal:string, dimensions:int, provider:string, model_id:int}>
     */
    public function generateEmbeddings(array $inputs, ?AiModel $model = null): array
    {
        $model ??= $this->getDefaultModel();
        if (!$model) {
            throw new RuntimeException('未配置可用的 embedding 模型');
        }
        $inputs = array_values(array_filter(array_map(fn ($s) => trim((string) $s), $inputs), fn ($s) => $s !== ''));
        if ($inputs === []) {
            return [];
        }

        $url = $this->embeddingEndpoint((string) $model->api_url);
        $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $model->api_key,
            ])
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withoutVerifying()
            ->post($url, [
                'model' => $model->model_id,
                'input' => $inputs,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Embedding API 调用失败，HTTP ' . $response->status());
        }

        $payload = $response->json();
        $data = $payload['data'] ?? [];
        if (!is_array($data) || count($data) !== count($inputs)) {
            throw new RuntimeException('Embedding API 响应数量不匹配');
        }

        $this->updateModelUsage($model->id);

        $out = [];
        foreach ($data as $i => $item) {
            $vector = $item['embedding'] ?? [];
            if (!is_array($vector) || $vector === []) {
                throw new RuntimeException('Embedding API 第 ' . $i . ' 条返回的向量为空');
            }
            $vector = $this->padVector($vector, self::STORAGE_DIMENSIONS);
            $out[] = [
                'vector'         => $vector,
                'vector_literal' => $this->toPgVectorLiteral($vector),
                'dimensions'     => count($vector),
                'provider'       => parse_url($url, PHP_URL_HOST) ?: '',
                'model_id'       => (int) $model->id,
            ];
        }
        return $out;
    }

    /**
     * 把向量 pad 到 STORAGE_DIMENSIONS（PG vector(3072) 列要求固定维度）；
     * 多余部分截断，不足部分补 0。
     *
     * @param float[] $vector
     * @return float[]
     */
    public function padVector(array $vector, int $dims = self::STORAGE_DIMENSIONS): array
    {
        $count = count($vector);
        if ($count === $dims) {
            return array_map('floatval', $vector);
        }
        if ($count > $dims) {
            return array_map('floatval', array_slice($vector, 0, $dims));
        }
        return array_pad(array_map('floatval', $vector), $dims, 0.0);
    }

    /**
     * 把 PHP 数组转 PG vector 字面量 `[1.0,2.0,...]`。
     *
     * @param float[] $vector
     */
    public function toPgVectorLiteral(array $vector): string
    {
        return '[' . implode(',', array_map(
            fn ($v) => is_float($v) || is_int($v) ? rtrim(rtrim(sprintf('%.6f', $v), '0'), '.') : '0',
            $vector,
        )) . ']';
    }

    private function updateModelUsage(int $modelId): void
    {
        try {
            AiModel::whereKey($modelId)->update([
                'used_today' => \DB::raw('used_today + 1'),
                'total_used' => \DB::raw('total_used + 1'),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('embedding model usage update failed', ['model_id' => $modelId, 'error' => $e->getMessage()]);
        }
    }

    private function embeddingEndpoint(string $apiUrl): string
    {
        $apiUrl = rtrim(trim($apiUrl), '/');
        if ($apiUrl === '') {
            throw new RuntimeException('embedding 模型 api_url 为空');
        }
        if (str_ends_with($apiUrl, '/embeddings')) {
            return $apiUrl;
        }
        if (!str_ends_with($apiUrl, '/v1')) {
            $apiUrl .= '/v1';
        }
        return $apiUrl . '/embeddings';
    }
}
