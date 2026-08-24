<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Authentication;

use Psr\Http\Message\ServerRequestInterface;
use RPflamm\LightroomPublishService\Domain\Model\ApiToken;
use RPflamm\LightroomPublishService\Exception\InvalidTokenException;
use RPflamm\LightroomPublishService\Exception\RateLimitExceededException;
use RPflamm\LightroomPublishService\Service\TokenService;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\RateLimiter\Storage\CachingFrameworkStorage;

/**
 * Authenticates incoming Lightroom Publish Service API requests against the
 * "Authorization: Bearer <token>" header.
 *
 * A valid token is never rate-limited, since a single Lightroom publish run
 * can easily fire far more than a handful of requests (one upload/update
 * per photo). Instead, the per-IP rate limit only applies to requests that
 * fail authentication, to slow down brute-forcing/guessing of a token.
 *
 * Built directly on Symfony's RateLimiterFactory (with TYPO3's CachingFrameworkStorage
 * as backend) rather than TYPO3\CMS\Core\RateLimiter\RateLimiterFactory: the latter's
 * public API differs substantially between v13.4 (only a fixed createLoginRateLimiter()
 * method) and v14.3 (generic createLimiter()), while the underlying Symfony factory and
 * TYPO3's cache-backed storage adapter are stable across both supported versions.
 */
final class BearerTokenAuthenticator
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly CachingFrameworkStorage $rateLimiterStorage,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @throws InvalidTokenException
     * @throws RateLimitExceededException
     */
    public function authenticate(ServerRequestInterface $request): ApiToken
    {
        $remoteIp = $this->resolveRemoteIp($request);
        $authorizationHeader = $request->getHeaderLine('Authorization');

        $apiToken = $authorizationHeader !== ''
            ? $this->tokenService->verify($authorizationHeader, $remoteIp)
            : null;

        if ($apiToken !== null) {
            return $apiToken;
        }

        $this->assertNotRateLimited($remoteIp);

        if ($authorizationHeader === '') {
            throw new InvalidTokenException('Missing Authorization header.');
        }

        throw new InvalidTokenException('Invalid, revoked, or malformed API token.');
    }

    /**
     * @throws RateLimitExceededException
     */
    private function assertNotRateLimited(string $remoteIp): void
    {
        $limit = (int) ($this->extensionConfiguration->get('lightroom_publish_service', 'rateLimitRequests') ?? 30);
        $interval = (string) ($this->extensionConfiguration->get('lightroom_publish_service', 'rateLimitInterval') ?? '5 minutes');

        $rateLimiterFactory = new RateLimiterFactory([
            'id' => 'lightroom_publish_service_auth',
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => $interval,
        ], $this->rateLimiterStorage);

        $rateLimit = $rateLimiterFactory->create($remoteIp)->consume();

        if (!$rateLimit->isAccepted()) {
            $retryAfter = max(1, $rateLimit->getRetryAfter()->getTimestamp() - time());
            throw new RateLimitExceededException($retryAfter);
        }
    }

    private function resolveRemoteIp(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if ($normalizedParams instanceof NormalizedParams) {
            return $normalizedParams->getRemoteAddress();
        }

        $serverParams = $request->getServerParams();

        return (string) ($serverParams['REMOTE_ADDR'] ?? 'unknown');
    }
}
