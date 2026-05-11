<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * AI 服务核心。与老 backend includes/ai_service.php 行为对齐：
 *   - generateContent: 取模型 → 校验额度 → 替换 prompt 变量 → HTTP 调用 → 写入计数
 *   - callApi:         POST OpenAI 兼容 /chat/completions，提取 choices[0].message.content
 *                      过滤 <think>...</think> 推理标签 + Markdown 标题前的赘述
 *   - processPromptVariables: 支持 {{var}} 直插 + {{#if var}}...{{/if}} 条件块
 *   - updateModelUsage:       同日累计；新一天重置 used_today
 *
 * api_key 通过 AiModel 模型的 PgCrypt mutator 自动解密；上游 GeoFlowCrypt 与
 * 老 backend 完全互通——同一个 token 两边都能解。
 */
class AiService
{
    public const REQUEST_TIMEOUT_SECONDS = 180;

    private const SYSTEM_PROMPT = '你是一位专业的内容创作者。严格要求：直接输出最终成品内容，禁止输出任何思考过程、分析步骤、写作计划或内心独白。';

    /**
     * 生成内容（统一入口）。
     *
     * @return array{success:bool, content?:string, model?:string, error?:string}
     */
    public function generateContent(int $modelId, string $prompt, array $variables = []): array
    {
        try {
            $model = $this->getActiveModel($modelId);
            if ($model->daily_limit > 0 && $model->used_today >= $model->daily_limit) {
                throw new RuntimeException('今日 API 调用次数已达上限');
            }

            $resolvedPrompt = $this->processPromptVariables($prompt, $variables);
            $content = $this->callApi($model, $resolvedPrompt);
            $this->updateModelUsage($modelId);

            return [
                'success' => true,
                'content' => $content,
                'model'   => $model->name,
            ];
        } catch (\Throwable $e) {
            Log::error('AI 生成内容失败', ['model_id' => $modelId, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 实际 HTTP 调用 OpenAI 兼容 chat 接口。返回纯文本 content。
     */
    public function callApi(AiModel $model, string $prompt): string
    {
        $url = $this->chatEndpoint((string) ($model->api_url ?? ''));

        $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $model->api_key,   // 自动解密（mutator）
            ])
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withoutVerifying()   // 与老 backend CURLOPT_SSL_VERIFYPEER=false 行为对齐
            ->post($url, [
                'model'    => $model->model_id,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'max_tokens'  => 4000,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('API 调用失败，HTTP 状态码: ' . $response->status());
        }

        $payload = $response->json();
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new RuntimeException('API 响应格式错误');
        }

        return $this->stripThinkingArtifacts(trim($content));
    }

    /**
     * 模板变量替换：{{var}} + {{#if var}}...{{/if}}。
     * 与老 processPromptVariables 行为完全一致。
     */
    public function processPromptVariables(string $prompt, array $variables): string
    {
        // 1) 简单 {{var}} 替换
        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', (string) $value, $prompt);
        }

        // 2) 条件块 {{#if var}} ... {{/if}}
        return (string) preg_replace_callback(
            '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s',
            function ($m) use ($variables) {
                $var = $m[1];
                return !empty($variables[$var]) ? $m[2] : '';
            },
            $prompt,
        );
    }

    /**
     * 同日 used_today + 1；跨日重置 used_today=1。total_used 永远 +1。
     */
    public function updateModelUsage(int $modelId): void
    {
        $model = AiModel::find($modelId);
        if (!$model) {
            return;
        }

        $today = Carbon::today()->toDateString();
        $lastUpdate = optional($model->updated_at)->toDateString();

        if ($lastUpdate !== $today) {
            $model->update([
                'used_today' => 1,
                'total_used' => ($model->total_used ?? 0) + 1,
            ]);
        } else {
            $model->update([
                'used_today' => ($model->used_today ?? 0) + 1,
                'total_used' => ($model->total_used ?? 0) + 1,
            ]);
        }
    }

    // ---- helpers ----

    private function getActiveModel(int $modelId): AiModel
    {
        $model = AiModel::query()
            ->whereKey($modelId)
            ->where('status', 'active')
            ->whereRaw("COALESCE(NULLIF(model_type, ''), 'chat') = 'chat'")
            ->first();
        if (!$model) {
            throw new RuntimeException('AI 模型不存在或未启用');
        }
        return $model;
    }

    /**
     * 根据 base url 推断 chat 端点。
     * 与老 backend ai_chat_endpoint_from_url 行为对齐：
     *   - 末尾不是 /chat/completions 时追加
     *   - 自动加 /v1 前缀（OpenAI 风格）
     */
    private function chatEndpoint(string $apiUrl): string
    {
        $apiUrl = rtrim(trim($apiUrl), '/');
        if ($apiUrl === '') {
            throw new RuntimeException('AI 模型 api_url 为空');
        }
        if (str_ends_with($apiUrl, '/chat/completions')) {
            return $apiUrl;
        }
        if (!str_ends_with($apiUrl, '/v1')) {
            $apiUrl .= '/v1';
        }
        return $apiUrl . '/chat/completions';
    }

    /**
     * 过滤推理模型 chain-of-thought 输出：
     *   - <think>...</think> 标签整段剔除
     *   - 内容中含 Markdown 一级标题时，去掉首个 "#" 标题之前的所有内容
     */
    private function stripThinkingArtifacts(string $content): string
    {
        $content = (string) preg_replace('/<think>.*?<\/think>/s', '', $content);
        if (preg_match('/^#\s+/m', $content)) {
            $content = (string) preg_replace('/\A.*?(?=^#\s+)/ms', '', $content);
        }
        return trim($content);
    }
}
