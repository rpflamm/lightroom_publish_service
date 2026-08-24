<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RPflamm\LightroomPublishService\Authentication\BearerTokenAuthenticator;
use RPflamm\LightroomPublishService\Controller\Api\CollectionApiController;
use RPflamm\LightroomPublishService\Controller\Api\PhotoApiController;
use RPflamm\LightroomPublishService\Controller\Api\PingController;
use RPflamm\LightroomPublishService\Exception\CollectionNotFoundException;
use RPflamm\LightroomPublishService\Exception\ConflictException;
use RPflamm\LightroomPublishService\Exception\InvalidTokenException;
use RPflamm\LightroomPublishService\Exception\PhotoNotFoundException;
use RPflamm\LightroomPublishService\Exception\RateLimitExceededException;
use RPflamm\LightroomPublishService\Exception\ValidationException;
use RPflamm\LightroomPublishService\Http\ApiResponseFactory;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Frontend-stack entry point for the Lightroom Publish Service JSON API.
 *
 * Intercepts every request under {@see self::PATH_PREFIX}, authenticates it via
 * Bearer token, dispatches to the matching controller, and maps exceptions to the
 * API's JSON error envelope. All other requests pass through untouched, so no
 * site configuration is required for the API to function.
 */
final class ApiRequestResolver implements MiddlewareInterface
{
    private const PATH_PREFIX = '/lightroom-publish-service/api/v1';

    public function __construct(
        private readonly BearerTokenAuthenticator $authenticator,
        private readonly PingController $pingController,
        private readonly CollectionApiController $collectionApiController,
        private readonly PhotoApiController $photoApiController,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, self::PATH_PREFIX)) {
            return $handler->handle($request);
        }

        $subPath = '/' . trim(substr($path, strlen(self::PATH_PREFIX)), '/');
        $method = $this->resolveMethod($request);

        try {
            $apiToken = $this->authenticator->authenticate($request);
            $route = $this->matchRoute($method, $subPath);

            if ($route === null) {
                return ApiResponseFactory::error('not_found', 'Unknown API route.', 404);
            }

            return $route($request, $apiToken);
        } catch (RateLimitExceededException $exception) {
            /** @var JsonResponse $response */
            $response = ApiResponseFactory::error('rate_limited', 'Too many requests.', 429);

            return $response->withHeader('Retry-After', (string) $exception->getRetryAfterSeconds());
        } catch (InvalidTokenException $exception) {
            return ApiResponseFactory::error('invalid_token', $exception->getMessage(), 401);
        } catch (PhotoNotFoundException|CollectionNotFoundException $exception) {
            return ApiResponseFactory::error('not_found', $exception->getMessage(), 404);
        } catch (ValidationException $exception) {
            return ApiResponseFactory::error($exception->getErrorCode(), $exception->getMessage(), 422);
        } catch (ConflictException $exception) {
            return ApiResponseFactory::error($exception->getErrorCode(), $exception->getMessage(), 409);
        }
    }

    /**
     * `LrHttp.postMultipart` cannot send a true PUT request, so the Lightroom
     * plugin sends POST with this override header for file-replace updates.
     */
    private function resolveMethod(ServerRequestInterface $request): string
    {
        $override = $request->getHeaderLine('X-HTTP-Method-Override');

        return $override !== '' ? strtoupper($override) : strtoupper($request->getMethod());
    }

    private function matchRoute(string $method, string $path): ?\Closure
    {
        $routes = [
            ['GET', '#^/ping$#', fn(ServerRequestInterface $r, $token) => $this->pingController->ping($token)],

            ['GET', '#^/collections$#', fn(ServerRequestInterface $r, $token) => $this->collectionApiController->list($r)],
            ['POST', '#^/collections$#', fn(ServerRequestInterface $r, $token) => $this->collectionApiController->create($r)],
            ['GET', '#^/collections/(?P<id>\d+)$#', fn(ServerRequestInterface $r, $token, array $m) => $this->collectionApiController->show($r, (int) $m['id'])],
            ['PATCH', '#^/collections/(?P<id>\d+)$#', fn(ServerRequestInterface $r, $token, array $m) => $this->collectionApiController->update($r, (int) $m['id'])],
            ['DELETE', '#^/collections/(?P<id>\d+)$#', fn(ServerRequestInterface $r, $token, array $m) => $this->collectionApiController->delete($r, (int) $m['id'])],
            ['GET', '#^/collections/(?P<id>\d+)/photos$#', fn(ServerRequestInterface $r, $token, array $m) => $this->photoApiController->listByCollection($r, (int) $m['id'])],
            ['POST', '#^/collections/(?P<id>\d+)/photos$#', fn(ServerRequestInterface $r, $token, array $m) => $this->photoApiController->upload($r, (int) $m['id'])],

            ['GET', '#^/photos$#', fn(ServerRequestInterface $r, $token) => $this->photoApiController->listUpdated($r)],
            ['GET', '#^/photos/(?P<id>\d+)$#', fn(ServerRequestInterface $r, $token, array $m) => $this->photoApiController->show($r, (int) $m['id'])],
            ['PUT', '#^/photos/(?P<id>\d+)$#', fn(ServerRequestInterface $r, $token, array $m) => $this->photoApiController->replace($r, (int) $m['id'])],
            ['PATCH', '#^/photos/(?P<id>\d+)$#', fn(ServerRequestInterface $r, $token, array $m) => $this->photoApiController->updateMetadata($r, (int) $m['id'])],
            ['DELETE', '#^/photos/(?P<id>\d+)$#', fn(ServerRequestInterface $r, $token, array $m) => $this->photoApiController->delete($r, (int) $m['id'])],
        ];

        foreach ($routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }
            if (preg_match($pattern, $path, $matches) === 1) {
                return fn(ServerRequestInterface $r, $token) => $handler($r, $token, $matches);
            }
        }

        return null;
    }
}
