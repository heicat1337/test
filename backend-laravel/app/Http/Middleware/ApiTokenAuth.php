<?php

namespace App\Http\Middleware;

use App\Http\Concerns\ApiResponses;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Token 鉴权中间件。从 Authorization: Bearer <token> 提取 plaintext，
 * SHA256 hash 匹配 api_tokens.token_hash，校验 status=active 且未过期。
 *
 * 兼容老 backend includes/api_auth.php + api_token_service.php 的 token 表 schema
 * （token_hash 用 SHA256，scopes jsonb 数组）。
 *
 * 验证通过后把 ApiToken 实例放进 $request->attributes->api_token，供下游
 * controller 与 RequireScope 中间件读取。同步更新 last_used_at。
 */
class ApiTokenAuth
{
    use ApiResponses;

    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('Authorization', '');
        if ($authorization === '' || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $m)) {
            return $this->apiError('unauthorized', '缺少或格式无效的 Authorization', 401, $request);
        }
        $plaintext = trim($m[1]);
        if ($plaintext === '') {
            return $this->apiError('unauthorized', 'Token 不能为空', 401, $request);
        }

        $hash = hash('sha256', $plaintext);
        $token = ApiToken::query()
            ->where('token_hash', $hash)
            ->where('status', 'active')
            ->first();

        if (!$token || $token->isExpired()) {
            return $this->apiError('unauthorized', 'Token 无效或已过期', 401, $request);
        }

        // 更新 last_used_at（与老 touchToken 行为对齐）
        ApiToken::whereKey($token->id)->update(['last_used_at' => now()]);

        $request->attributes->set('api_token', $token);
        return $next($request);
    }
}
