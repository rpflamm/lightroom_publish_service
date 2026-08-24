<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Functional\Controller\Api;

use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Tests\Functional\AbstractApiTestCase;

final class PhotoUpdateTest extends AbstractApiTestCase
{
    #[Test]
    public function putReplacesFileAndMetadataWithoutOrphaningTheOldFile(): void
    {
        $collectionId = $this->createCollection();
        $photo = $this->uploadPhoto($collectionId, ['title' => 'Before']);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file');
        $fileCountBefore = (int)$queryBuilder->count('uid')->from('sys_file')->executeQuery()->fetchOne();

        $request = $this->authenticated(self::API_BASE . '/photos/' . $photo['id'])
            ->withMethod('POST')
            ->withHeader('X-HTTP-Method-Override', 'PUT')
            ->withUploadedFiles(['file' => $this->uploadedFixtureFile()])
            ->withParsedBody(['metadata' => json_encode(['title' => 'After'])]);

        $response = $this->executeFrontendSubRequest($request);
        $data = json_decode((string)$response->getBody(), true)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('After', $data['title']);
        self::assertSame($photo['id'], $data['id']);

        // Replacing in place must not create a second sys_file row.
        $fileCountAfter = (int)$queryBuilder->count('uid')->from('sys_file')->executeQuery()->fetchOne();
        self::assertSame($fileCountBefore, $fileCountAfter);
    }

    #[Test]
    public function patchUpdatesOnlyProvidedMetadataFields(): void
    {
        $collectionId = $this->createCollection();
        $photo = $this->uploadPhoto($collectionId, ['title' => 'Original Title', 'caption' => 'Original Caption']);

        $response = $this->executeFrontendSubRequest(
            $this->jsonRequest('PATCH', self::API_BASE . '/photos/' . $photo['id'], ['title' => 'Updated Title']),
        );
        $data = json_decode((string)$response->getBody(), true)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Updated Title', $data['title']);
        self::assertSame('Original Caption', $data['caption']);
    }

    #[Test]
    public function returns404WhenUpdatingUnknownPhoto(): void
    {
        $response = $this->executeFrontendSubRequest(
            $this->jsonRequest('PATCH', self::API_BASE . '/photos/99999', ['title' => 'Nope']),
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
