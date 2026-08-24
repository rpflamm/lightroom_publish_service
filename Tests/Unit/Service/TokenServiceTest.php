<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Domain\Model\ApiToken;
use RPflamm\LightroomPublishService\Domain\Repository\ApiTokenRepository;
use RPflamm\LightroomPublishService\Service\TokenService;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TokenServiceTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'test-encryption-key';
    }

    #[Test]
    public function createReturnsATokenMatchingTheExpectedFormat(): void
    {
        $repository = $this->createMock(ApiTokenRepository::class);
        $repository->expects(self::once())->method('create');

        $tokenService = new TokenService($repository, new HashService());
        $token = $tokenService->create('my token', 1);

        self::assertMatchesRegularExpression('/^lrps_[A-Za-z0-9_-]{16}_[A-Za-z0-9_-]{43}$/', $token);
    }

    #[Test]
    public function verifySucceedsForAMatchingValidToken(): void
    {
        $repository = $this->createMock(ApiTokenRepository::class);
        $hashService = new HashService();

        $capturedSelector = null;
        $capturedHash = null;
        $repository->method('create')->willReturnCallback(
            function (string $name, string $selector, string $verifierHash) use (&$capturedSelector, &$capturedHash) {
                $capturedSelector = $selector;
                $capturedHash = $verifierHash;

                return 1;
            },
        );

        $tokenService = new TokenService($repository, $hashService);
        $plaintext = $tokenService->create('my token', 1);

        $apiToken = new ApiToken(1, 'my token', $capturedSelector, $capturedHash, 0, '', false, time());
        $repository->method('findActiveBySelector')->willReturn($apiToken);
        $repository->expects(self::once())->method('updateLastUsed');

        $result = $tokenService->verify('Bearer ' . $plaintext, '127.0.0.1');

        self::assertNotNull($result);
        self::assertSame(1, $result->uid);
    }

    #[Test]
    public function verifyFailsForATamperedVerifier(): void
    {
        $repository = $this->createMock(ApiTokenRepository::class);
        $hashService = new HashService();

        $capturedSelector = null;
        $capturedHash = null;
        $repository->method('create')->willReturnCallback(
            function (string $name, string $selector, string $verifierHash) use (&$capturedSelector, &$capturedHash) {
                $capturedSelector = $selector;
                $capturedHash = $verifierHash;

                return 1;
            },
        );

        $tokenService = new TokenService($repository, $hashService);
        $tokenService->create('my token', 1);

        $apiToken = new ApiToken(1, 'my token', $capturedSelector, $capturedHash, 0, '', false, time());
        $repository->method('findActiveBySelector')->willReturn($apiToken);
        $repository->expects(self::never())->method('updateLastUsed');

        $tamperedToken = 'lrps_' . $capturedSelector . '_' . str_repeat('a', 43);
        $result = $tokenService->verify('Bearer ' . $tamperedToken, '127.0.0.1');

        self::assertNull($result);
    }

    #[Test]
    public function verifyFailsForARevokedOrUnknownSelector(): void
    {
        $repository = $this->createMock(ApiTokenRepository::class);
        $repository->method('findActiveBySelector')->willReturn(null);

        $tokenService = new TokenService($repository, new HashService());
        $result = $tokenService->verify('Bearer lrps_' . str_repeat('a', 16) . '_' . str_repeat('b', 43), '127.0.0.1');

        self::assertNull($result);
    }

    #[Test]
    public function verifyFailsForAMalformedAuthorizationHeader(): void
    {
        $repository = $this->createMock(ApiTokenRepository::class);
        $repository->expects(self::never())->method('findActiveBySelector');

        $tokenService = new TokenService($repository, new HashService());

        self::assertNull($tokenService->verify('Bearer not-a-token', '127.0.0.1'));
        self::assertNull($tokenService->verify('', '127.0.0.1'));
        self::assertNull($tokenService->verify('lrps_abc_def', '127.0.0.1'));
    }
}
