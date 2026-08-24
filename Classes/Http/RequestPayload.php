<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Http;

use Psr\Http\Message\ServerRequestInterface;
use RPflamm\LightroomPublishService\Exception\ValidationException;

/**
 * Small helpers for reading the two request body shapes the API accepts:
 * a raw JSON body (collections CRUD, metadata-only PATCH), or a multipart
 * form with a "metadata" field containing JSON text (photo upload/replace).
 */
final class RequestPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function jsonBody(ServerRequestInterface $request): array
    {
        $contents = (string)$request->getBody();
        if (trim($contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ValidationException('Request body is not valid JSON.', 'invalid_json');
        }

        if (!is_array($decoded)) {
            throw new ValidationException('Request body must be a JSON object.', 'invalid_json');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public static function multipartMetadata(ServerRequestInterface $request): array
    {
        $parsedBody = $request->getParsedBody();
        $metadataField = is_array($parsedBody) ? ($parsedBody['metadata'] ?? null) : null;

        if ($metadataField === null || $metadataField === '') {
            return [];
        }

        try {
            $decoded = json_decode((string)$metadataField, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ValidationException('"metadata" field is not valid JSON.', 'invalid_json');
        }

        if (!is_array($decoded)) {
            throw new ValidationException('"metadata" field must be a JSON object.', 'invalid_json');
        }

        return $decoded;
    }
}
