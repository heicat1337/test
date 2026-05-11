<?php

namespace App\Exceptions\Api;

use RuntimeException;
use Throwable;

/**
 * 与老 backend includes/api_response.php::ApiException 对齐的 API 异常。
 *
 * Controller catch 后转成 api_build_error_payload 同格式：
 *   { "success": false, "data": null, "error": { code, message, details? }, "meta": {...} }
 */
class ApiException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $httpStatus = 400,
        private readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public static function validationFailed(array $fieldErrors): self
    {
        return new self('validation_failed', '参数校验失败', 422, ['field_errors' => $fieldErrors]);
    }

    public static function notFound(string $code, string $message): self
    {
        return new self($code, $message, 404);
    }
}
