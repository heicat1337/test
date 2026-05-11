<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 统一 API 响应信封 trait。与老 backend api_response.php
 * api_build_{success,error}_payload 字段对齐。
 */
trait ApiResponses
{
    protected function apiSuccess(mixed $data, Request $request, int $status = 200, int $cacheSeconds = 0): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => [
                'request_id' => $this->apiRequestId($request),
                'timestamp'  => now()->toAtomString(),
            ],
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($cacheSeconds > 0) {
            $response->header('Cache-Control', "public, max-age={$cacheSeconds}, stale-while-revalidate=300");
        }
        return $response;
    }

    protected function apiError(string $code, string $message, int $status, Request $request, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }
        return response()->json([
            'success' => false,
            'data'    => null,
            'error'   => $error,
            'meta'    => [
                'request_id' => $this->apiRequestId($request),
                'timestamp'  => now()->toAtomString(),
            ],
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function apiRequestId(Request $request): string
    {
        return $request->header('X-Request-Id', (string) Str::uuid());
    }
}
