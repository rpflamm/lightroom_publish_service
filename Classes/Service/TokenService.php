<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Service;

use RPflamm\LightroomPublishService\Domain\Model\ApiToken;
use RPflamm\LightroomPublishService\Domain\Repository\ApiTokenRepository;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Generates and verifies static Bearer API tokens of the form "lrps_<selector>_<verifier>".
 *
 * Only the selector (a fast, non-secret DB lookup key) and an HMAC hash of the verifier
 * are ever persisted; the plaintext token is returned exactly once, at creation time.
 */
final class TokenService
{
    private const TOKEN_PREFIX = 'lrps';
    private const TOKEN_PATTERN = '/^lrps_([A-Za-z0-9_-]{16})_([A-Za-z0-9_-]{43})$/';

    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly HashService $hashService,
    ) {}

    /**
     * Creates a new token and returns the plaintext value. This is the only
     * point in time the plaintext is ever available.
     */
    public function create(string $name, int $creatorBackendUserUid): string
    {
        $selector = $this->base64UrlEncode(random_bytes(12));
        $verifier = $this->base64UrlEncode(random_bytes(32));

        $this->apiTokenRepository->create(
            $name,
            $selector,
            $this->hashVerifier($verifier),
            $creatorBackendUserUid,
        );

        return sprintf('%s_%s_%s', self::TOKEN_PREFIX, $selector, $verifier);
    }

    /**
     * Verifies a raw "Authorization: Bearer ..." header value and, on success,
     * returns the matched token record (with last-used bookkeeping already updated).
     */
    public function verify(string $authorizationHeader, string $remoteIp): ?ApiToken
    {
        $token = $this->extractToken($authorizationHeader);
        if ($token === null) {
            return null;
        }

        [$selector, $verifier] = $token;

        $apiToken = $this->apiTokenRepository->findActiveBySelector($selector);
        if ($apiToken === null) {
            return null;
        }

        if (!hash_equals($apiToken->verifierHash, $this->hashVerifier($verifier))) {
            return null;
        }

        if (time() - $apiToken->lastUsedAt > 60) {
            $this->apiTokenRepository->updateLastUsed($apiToken->uid, $remoteIp);
        }

        return $apiToken;
    }

    /**
     * @return array{0: string, 1: string}|null [selector, verifier]
     */
    private function extractToken(string $authorizationHeader): ?array
    {
        if (!str_starts_with($authorizationHeader, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authorizationHeader, 7));
        if (preg_match(self::TOKEN_PATTERN, $token, $matches) !== 1) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    private function hashVerifier(string $verifier): string
    {
        return $this->hashService->hmac($verifier, self::class);
    }

    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
