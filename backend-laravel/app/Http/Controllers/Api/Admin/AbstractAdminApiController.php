<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Api\ApiException;
use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * 管理 API 的公共基类：响应信封 + ApiException 自动转 JSON 错误。
 *
 * 子类（CatalogController / TaskController / ArticleAdminController / JobController）
 * 直接用 $this->run(fn () => $service->...) 把 service 调用与异常处理绑定。
 */
abstract class AbstractAdminApiController extends Controller
{
    use ApiResponses;

    /**
     * 跑业务闭包，自动捕获 ApiException 转成对应 HTTP 状态 + JSON。
     */
    protected function run(Request $request, \Closure $callback, int $successStatus = 200): JsonResponse
    {
        try {
            $data = $callback();
            return $this->apiSuccess($data, $request, $successStatus);
        } catch (ApiException $e) {
            return $this->apiError(
                $e->getErrorCode(),
                $e->getMessage(),
                $e->getHttpStatus(),
                $request,
                $e->getDetails(),
            );
        } catch (Throwable $e) {
            report($e);
            return $this->apiError('internal_error', '服务器内部错误', 500, $request);
        }
    }

    /**
     * 当前请求关联的 token（已由 ApiTokenAuth middleware 注入）。
     * 控制器需要审计 admin_id 时调 token()->created_by_admin_id。
     */
    protected function token(Request $request): ?ApiToken
    {
        $t = $request->attributes->get('api_token');
        return $t instanceof ApiToken ? $t : null;
    }

    protected function auditAdminId(Request $request): int
    {
        $token = $this->token($request);
        return (int) ($token?->created_by_admin_id ?? 0);
    }
}
