<?php

namespace App\Http\Middleware;

use App\Http\Concerns\ApiResponses;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 检查 ApiTokenAuth 注入的 token 是否含指定 scope。
 *
 * 用法（routes/api.php）：
 *   Route::get('catalog', [...])->middleware('api.scope:catalog:read');
 */
class RequireScope
{
    use ApiResponses;

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('api_token');
        if (!$token instanceof ApiToken) {
            return $this->apiError('unauthorized', 'Token 鉴权未生效', 401, $request);
        }

        $scopes = is_array($token->scopes) ? $token->scopes : [];
        if (!in_array($scope, $scopes, true) && !in_array('*', $scopes, true)) {
            return $this->apiError(
                'forbidden',
                '当前 Token 没有访问此接口的权限',
                403,
                $request,
                ['required_scope' => $scope],
            );
        }
        return $next($request);
    }
}
