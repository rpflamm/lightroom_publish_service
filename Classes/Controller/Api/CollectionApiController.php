<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Controller\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RPflamm\LightroomPublishService\Http\ApiResponseFactory;
use RPflamm\LightroomPublishService\Http\RequestPayload;
use RPflamm\LightroomPublishService\Service\CollectionService;

final class CollectionApiController
{
    public function __construct(
        private readonly CollectionService $collectionService,
    ) {}

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        return ApiResponseFactory::success($this->collectionService->listAll());
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $payload = RequestPayload::jsonBody($request);

        return ApiResponseFactory::success($this->collectionService->create($payload), 201);
    }

    public function show(ServerRequestInterface $request, int $uid): ResponseInterface
    {
        return ApiResponseFactory::success($this->collectionService->show($uid));
    }

    public function update(ServerRequestInterface $request, int $uid): ResponseInterface
    {
        $payload = RequestPayload::jsonBody($request);

        return ApiResponseFactory::success($this->collectionService->update($uid, $payload));
    }

    public function delete(ServerRequestInterface $request, int $uid): ResponseInterface
    {
        $cascade = ($request->getQueryParams()['cascade'] ?? '') === '1';
        $this->collectionService->delete($uid, $cascade);

        return ApiResponseFactory::success(null, 200);
    }
}
