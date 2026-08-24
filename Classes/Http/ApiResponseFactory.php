<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Http;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Builds the JSON envelope used by all Lightroom Publish Service API responses:
 * {"success": true, "data": ...} or {"success": false, "error": {"code": ..., "message": ...}}.
 */
final class ApiResponseFactory
{
    public static function success(mixed $data = null, int $status = 200): ResponseInterface
    {
        return new JsonResponse([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    public static function error(string $code, string $message, int $status): ResponseInterface
    {
        return new JsonResponse([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
