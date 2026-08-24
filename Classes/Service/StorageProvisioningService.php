<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Idempotently provisions the dedicated FAL storage used to hold all
 * Lightroom-published photos, isolated from editor-managed fileadmin/ content.
 */
final class StorageProvisioningService
{
    private const STORAGE_NAME = 'Lightroom Publish Service';
    private const BASE_PATH = 'fileadmin/lightroom_publish_service/';

    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getOrCreateStorage(): ResourceStorage
    {
        $storageUid = (int)($this->extensionConfiguration->get('lightroom_publish_service', 'storageUid') ?? 0);

        if ($storageUid > 0) {
            $storage = $this->storageRepository->findByUid($storageUid);
            if ($storage !== null) {
                return $storage;
            }
        }

        $existingUid = $this->findExistingStorageUidByName();
        if ($existingUid !== null) {
            $this->rememberStorageUid($existingUid);

            return $this->storageRepository->getStorageObject($existingUid);
        }

        GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/' . self::BASE_PATH);

        $newUid = $this->storageRepository->createLocalStorage(
            self::STORAGE_NAME,
            self::BASE_PATH,
            'relative',
            'Managed exclusively by the Lightroom Publish Service API. Do not edit files here manually.',
        );
        $this->rememberStorageUid($newUid);

        return $this->storageRepository->getStorageObject($newUid);
    }

    public function getOrCreateCollectionFolder(int $collectionUid): Folder
    {
        $storage = $this->getOrCreateStorage();
        $identifier = '/collections/' . $collectionUid . '/';

        if ($storage->hasFolder($identifier)) {
            return $storage->getFolder($identifier);
        }

        $rootFolder = $storage->hasFolder('/collections/')
            ? $storage->getFolder('/collections/')
            : $storage->createFolder('collections');

        return $storage->createFolder((string)$collectionUid, $rootFolder);
    }

    private function findExistingStorageUidByName(): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_storage');
        $uid = $queryBuilder
            ->select('uid')
            ->from('sys_file_storage')
            ->where($queryBuilder->expr()->eq('name', $queryBuilder->createNamedParameter(self::STORAGE_NAME)))
            ->executeQuery()
            ->fetchOne();

        return $uid !== false ? (int)$uid : null;
    }

    private function rememberStorageUid(int $uid): void
    {
        $this->extensionConfiguration->set('lightroom_publish_service/storageUid', (string)$uid);
    }
}
