<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Functional;

use RPflamm\LightroomPublishService\Service\TokenService;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractApiTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['filemetadata', 'lightroom_publish_service'];

    protected const API_BASE = 'http://localhost/lightroom-publish-service/api/v1';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->get(TokenService::class)->create(static::class, 1);
    }

    protected function authenticated(string $uri): InternalRequest
    {
        return (new InternalRequest($uri))->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function jsonRequest(string $method, string $uri, array $payload = []): InternalRequest
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write(json_encode($payload, JSON_THROW_ON_ERROR));
        $stream->rewind();

        return $this->authenticated($uri)
            ->withMethod($method)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);
    }

    protected function uploadedFixtureFile(): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'lrps-test-');
        copy(__DIR__ . '/Fixtures/test-photo.jpg', $tmpPath);

        return new UploadedFile($tmpPath, filesize($tmpPath), UPLOAD_ERR_OK, 'test-photo.jpg', 'image/jpeg');
    }

    protected function createCollection(string $name = 'Test Collection'): string
    {
        $response = $this->executeFrontendSubRequest(
            $this->jsonRequest('POST', self::API_BASE . '/collections', ['name' => $name]),
        );
        self::assertSame(201, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true)['data']['id'];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    protected function uploadPhoto(string $collectionId, array $metadata = ['title' => 'Test Photo']): array
    {
        $request = $this->authenticated(self::API_BASE . '/collections/' . $collectionId . '/photos')
            ->withMethod('POST')
            ->withUploadedFiles(['file' => $this->uploadedFixtureFile()])
            ->withParsedBody(['metadata' => json_encode($metadata, JSON_THROW_ON_ERROR)]);

        $response = $this->executeFrontendSubRequest($request);
        self::assertSame(201, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true)['data'];
    }
}
