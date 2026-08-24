<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use RPflamm\LightroomPublishService\Service\StorageProvisioningService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class StorageProvisioningServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['filemetadata', 'lightroom_publish_service'];

    #[Test]
    public function creatingTheStorageTwiceIsIdempotent(): void
    {
        $service = $this->get(StorageProvisioningService::class);

        $storage1 = $service->getOrCreateStorage();
        $storage2 = $service->getOrCreateStorage();

        self::assertSame($storage1->getUid(), $storage2->getUid());

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_storage');
        $count = (int)$queryBuilder->count('uid')->from('sys_file_storage')
            ->where($queryBuilder->expr()->eq('name', $queryBuilder->createNamedParameter('Lightroom Publish Service')))
            ->executeQuery()->fetchOne();

        self::assertSame(1, $count);
    }

    #[Test]
    public function collectionFoldersAreCreatedLazilyAndReused(): void
    {
        $service = $this->get(StorageProvisioningService::class);

        $folder1 = $service->getOrCreateCollectionFolder(42);
        $folder2 = $service->getOrCreateCollectionFolder(42);

        self::assertSame($folder1->getIdentifier(), $folder2->getIdentifier());
        self::assertStringContainsString('/collections/42/', $folder1->getIdentifier());
    }
}
