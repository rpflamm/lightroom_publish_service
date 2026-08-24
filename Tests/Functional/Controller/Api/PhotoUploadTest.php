<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Functional\Controller\Api;

use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Tests\Functional\AbstractApiTestCase;

final class PhotoUploadTest extends AbstractApiTestCase
{
    #[Test]
    public function uploadsPhotoWithMetadata(): void
    {
        $collectionId = $this->createCollection();

        $data = $this->uploadPhoto($collectionId, [
            'title' => 'Cathedral',
            'keywords' => ['camino', 'spain'],
            'rating' => 5,
        ]);

        self::assertSame('Cathedral', $data['title']);
        self::assertSame(['camino', 'spain'], $data['keywords']);
        self::assertSame(5, $data['rating']);
        self::assertNotEmpty($data['contentHash']);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file');
        $fileCount = (int) $queryBuilder->count('uid')->from('sys_file')
            ->where($queryBuilder->expr()->eq('name', $queryBuilder->createNamedParameter($collectionId . '_test-photo.jpg')))
            ->executeQuery()->fetchOne();
        self::assertSame(1, $fileCount);

        $refQueryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_reference');
        $refCount = (int) $refQueryBuilder->count('uid')->from('sys_file_reference')
            ->where(
                $refQueryBuilder->expr()->eq('tablenames', $refQueryBuilder->createNamedParameter('tx_lightroompublishservice_domain_model_photo')),
                $refQueryBuilder->expr()->eq('uid_foreign', $refQueryBuilder->createNamedParameter((int) $data['id'], ParameterType::INTEGER)),
            )
            ->executeQuery()->fetchOne();
        self::assertSame(1, $refCount);
    }

    #[Test]
    public function returns422WhenFileIsMissing(): void
    {
        $collectionId = $this->createCollection();

        $request = $this->authenticated(self::API_BASE . '/collections/' . $collectionId . '/photos')
            ->withMethod('POST')
            ->withParsedBody(['metadata' => json_encode(['title' => 'No file'])]);

        $response = $this->executeFrontendSubRequest($request);
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('missing_file', $body['error']['code']);
    }

    #[Test]
    public function returns404ForUnknownCollection(): void
    {
        $request = $this->authenticated(self::API_BASE . '/collections/99999/photos')
            ->withMethod('POST')
            ->withUploadedFiles(['file' => $this->uploadedFixtureFile()])
            ->withParsedBody(['metadata' => json_encode(['title' => 'Orphan'])]);

        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(404, $response->getStatusCode());
    }
}
