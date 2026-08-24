<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Controller\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RPflamm\LightroomPublishService\Exception\ValidationException;
use RPflamm\LightroomPublishService\Http\ApiResponseFactory;
use RPflamm\LightroomPublishService\Http\RequestPayload;
use RPflamm\LightroomPublishService\Service\PhotoService;

final class PhotoApiController
{
    public function __construct(
        private readonly PhotoService $photoService,
    ) {}

    public function upload(ServerRequestInterface $request, int $collectionUid): ResponseInterface
    {
        $uploadedFile = $request->getUploadedFiles()['file'] ?? null;
        if ($uploadedFile === null) {
            throw new ValidationException('Missing "file" upload part.', 'missing_file');
        }

        $metadata = RequestPayload::multipartMetadata($request);
        $result = $this->photoService->upload($collectionUid, $uploadedFile, $metadata);

        return ApiResponseFactory::success($result, 201);
    }

    public function show(ServerRequestInterface $request, int $uid): ResponseInterface
    {
        return ApiResponseFactory::success($this->photoService->show($uid));
    }

    public function listByCollection(ServerRequestInterface $request, int $collectionUid): ResponseInterface
    {
        return ApiResponseFactory::success($this->photoService->listByCollection($collectionUid));
    }

    public function listUpdated(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $timestamp = $this->parseTimestamp((string) ($query['updatedSince'] ?? ''));
        $collectionUid = isset($query['collection']) && $query['collection'] !== '' ? (int) $query['collection'] : null;

        return ApiResponseFactory::success($this->photoService->listUpdatedSince($timestamp, $collectionUid));
    }

    public function replace(ServerRequestInterface $request, int $uid): ResponseInterface
    {
        $uploadedFile = $request->getUploadedFiles()['file'] ?? null;
        if ($uploadedFile === null) {
            throw new ValidationException('Missing "file" upload part.', 'missing_file');
        }

        $metadata = RequestPayload::multipartMetadata($request);

        return ApiResponseFactory::success($this->photoService->replace($uid, $uploadedFile, $metadata));
    }

    public function updateMetadata(ServerRequestInterface $request, int $uid): ResponseInterface
    {
        $metadata = RequestPayload::jsonBody($request);

        return ApiResponseFactory::success($this->photoService->updateMetadata($uid, $metadata));
    }

    public function delete(ServerRequestInterface $request, int $uid): ResponseInterface
    {
        $this->photoService->delete($uid);

        return ApiResponseFactory::success(null, 200);
    }

    private function parseTimestamp(string $value): int
    {
        if ($value === '') {
            return 0;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }

        try {
            return (new \DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception) {
            throw new ValidationException('"updatedSince" must be a unix timestamp or ISO-8601 date.', 'invalid_date');
        }
    }
}
