<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Functional\Middleware;

use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Service\TokenService;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BearerTokenAuthenticationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['filemetadata', 'lightroom_publish_service'];

    #[Test]
    public function returns200ForValidToken(): void
    {
        $token = $this->get(TokenService::class)->create('functional-test', 1);

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/lightroom-publish-service/api/v1/ping'))
                ->withHeader('Authorization', 'Bearer ' . $token),
        );

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);
        self::assertSame('functional-test', $body['data']['tokenName']);
    }

    #[Test]
    public function returns401ForMissingAuthorizationHeader(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('http://localhost/lightroom-publish-service/api/v1/ping'),
        );

        self::assertSame(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        self::assertFalse($body['success']);
        self::assertSame('invalid_token', $body['error']['code']);
    }

    #[Test]
    public function returns401ForMalformedToken(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/lightroom-publish-service/api/v1/ping'))
                ->withHeader('Authorization', 'Bearer not-a-real-token'),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function returns401ForRevokedToken(): void
    {
        $tokenService = $this->get(TokenService::class);
        $token = $tokenService->create('to-be-revoked', 1);

        $connection = $this->getConnectionPool()->getConnectionForTable('tx_lightroompublishservice_domain_model_apitoken');
        $connection->update('tx_lightroompublishservice_domain_model_apitoken', ['revoked' => 1], ['name' => 'to-be-revoked']);

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/lightroom-publish-service/api/v1/ping'))
                ->withHeader('Authorization', 'Bearer ' . $token),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function validTokenIsNeverRateLimited(): void
    {
        $token = $this->get(TokenService::class)->create('rate-limit-test-valid', 1);

        // The default rate limit is 30 requests / 5 minutes; a real Lightroom
        // publish run of a handful of photos already exceeds that. A valid
        // token must never be throttled, only failed-auth attempts are.
        for ($i = 0; $i < 40; $i++) {
            $response = $this->executeFrontendSubRequest(
                (new InternalRequest('http://localhost/lightroom-publish-service/api/v1/ping'))
                    ->withHeader('Authorization', 'Bearer ' . $token),
            );
            self::assertSame(200, $response->getStatusCode(), 'Request ' . ($i + 1) . ' of 40 with a valid token was throttled.');
        }
    }

    #[Test]
    public function repeatedInvalidAttemptsAreEventuallyRateLimited(): void
    {
        $sawRateLimited = false;

        for ($i = 0; $i < 40; $i++) {
            $response = $this->executeFrontendSubRequest(
                (new InternalRequest('http://localhost/lightroom-publish-service/api/v1/ping'))
                    ->withHeader('Authorization', 'Bearer lrps_invalid_attempt_' . $i . '_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
            );

            if ($response->getStatusCode() === 429) {
                $sawRateLimited = true;
                $body = json_decode((string)$response->getBody(), true);
                self::assertSame('rate_limited', $body['error']['code']);
                self::assertTrue($response->hasHeader('Retry-After'));
                break;
            }

            self::assertSame(401, $response->getStatusCode());
        }

        self::assertTrue($sawRateLimited, 'Expected repeated invalid-token attempts to eventually be rate-limited.');
    }

    #[Test]
    public function nonApiPathsPassThroughUnaffected(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('http://localhost/some/other/path'),
        );

        // Not authenticated by our middleware, and no site is configured in this
        // test instance, so core's own routing takes over — the key assertion is
        // simply that it is NOT our 401 JSON error response.
        self::assertNotSame(401, $response->getStatusCode());
    }
}
