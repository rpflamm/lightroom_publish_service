<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Functional\Controller\Api;

use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Tests\Functional\AbstractApiTestCase;

final class PhotoDeleteTest extends AbstractApiTestCase
{
    #[Test]
    public function deletesPhotoAndCleansUpFalFile(): void
    {
        $collectionId = $this->createCollection();
        $photo = $this->uploadPhoto($collectionId);

        $fileQueryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file');
        $fileName = $collectionId . '_test-photo.jpg';
        $fileRow = $fileQueryBuilder->select('uid', 'identifier')->from('sys_file')
            ->where($fileQueryBuilder->expr()->eq('name', $fileQueryBuilder->createNamedParameter($fileName)))
            ->executeQuery()->fetchAssociative();
        self::assertNotFalse($fileRow, 'Uploaded file must exist before delete.');
        $absoluteFilePath = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/fileadmin/lightroom_publish_service' . $fileRow['identifier'];
        self::assertFileExists($absoluteFilePath, 'Physical file must exist before delete.');

        $response = $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/photos/' . $photo['id'])->withMethod('DELETE'),
        );
        self::assertSame(200, $response->getStatusCode());

        // Photo row must be gone (soft-deleted, invisible via default restrictions).
        $photoQueryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_lightroompublishservice_domain_model_photo');
        $photoCount = (int)$photoQueryBuilder->count('uid')->from('tx_lightroompublishservice_domain_model_photo')
            ->where($photoQueryBuilder->expr()->eq('uid', $photoQueryBuilder->createNamedParameter((int)$photo['id'], ParameterType::INTEGER)))
            ->executeQuery()->fetchOne();
        self::assertSame(0, $photoCount);

        // The sys_file_reference must be gone.
        $refQueryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_reference');
        $refCount = (int)$refQueryBuilder->count('uid')->from('sys_file_reference')
            ->where($refQueryBuilder->expr()->eq('uid_foreign', $refQueryBuilder->createNamedParameter((int)$photo['id'], ParameterType::INTEGER)))
            ->executeQuery()->fetchOne();
        self::assertSame(0, $refCount);

        // The sys_file row and the physical file itself must both be gone, not just dereferenced.
        $sysFileCount = (int)$fileQueryBuilder->count('uid')->from('sys_file')
            ->where($fileQueryBuilder->expr()->eq('uid', $fileQueryBuilder->createNamedParameter((int)$fileRow['uid'], ParameterType::INTEGER)))
            ->executeQuery()->fetchOne();
        self::assertSame(0, $sysFileCount);
        self::assertFileDoesNotExist($absoluteFilePath);
    }

    #[Test]
    public function returns404WhenDeletingUnknownPhoto(): void
    {
        $response = $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/photos/99999')->withMethod('DELETE'),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function showReturns404AfterDelete(): void
    {
        $collectionId = $this->createCollection();
        $photo = $this->uploadPhoto($collectionId);

        $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/photos/' . $photo['id'])->withMethod('DELETE'),
        );

        $response = $this->executeFrontendSubRequest(
            $this->authenticated(self::API_BASE . '/photos/' . $photo['id']),
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
