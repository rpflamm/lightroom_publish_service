<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Service;

use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\UploadedFileInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handles the FAL side of photo upload/replace/delete: moving the uploaded
 * binary into the dedicated Lightroom storage and maintaining the direct
 * sys_file_reference link to a tx_lightroompublishservice_domain_model_photo row.
 *
 * sys_file_reference rows are written directly rather than through DataHandler,
 * since the API runs outside any backend user session/DataHandler context.
 */
final class FileUploadService
{
    private const PHOTO_TABLE = 'tx_lightroompublishservice_domain_model_photo';

    public function __construct(
        private readonly StorageProvisioningService $storageProvisioningService,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function storeUploadedFile(UploadedFileInterface $uploadedFile, int $collectionUid, int $photoUid): File
    {
        $folder = $this->storageProvisioningService->getOrCreateCollectionFolder($collectionUid);
        $tmpPath = $this->moveToTemporaryFile($uploadedFile);
        $targetFileName = $photoUid . '_' . $this->sanitizeOriginalFileName($uploadedFile);

        $storage = $folder->getStorage();

        return $storage->addFile($tmpPath, $folder, $targetFileName, DuplicationBehavior::REPLACE);
    }

    public function replaceFile(File $file, UploadedFileInterface $uploadedFile): File
    {
        $tmpPath = $this->moveToTemporaryFile($uploadedFile);
        $storage = $file->getStorage();

        return $storage->replaceFile($file, $tmpPath);
    }

    public function hashFile(File $file): string
    {
        $tmpPath = $file->getForLocalProcessing(false);

        return hash_file('sha256', $tmpPath) ?: '';
    }

    public function linkFileToPhoto(int $photoUid, File $file, int $pid = 0): void
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $connection->insert('sys_file_reference', [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
            'uid_local' => $file->getUid(),
            'uid_foreign' => $photoUid,
            'tablenames' => self::PHOTO_TABLE,
            'fieldname' => 'file',
            'sorting_foreign' => 1,
        ]);
    }

    public function getFileForPhoto(int $fileUid): ?File
    {
        try {
            return $this->resourceFactory->getFileObject($fileUid);
        } catch (\Exception) {
            return null;
        }
    }

    public function deletePhotoFileReferences(int $photoUid): void
    {
        $referenceQueryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $references = $referenceQueryBuilder
            ->select('uid', 'uid_local')
            ->from('sys_file_reference')
            ->where(
                $referenceQueryBuilder->expr()->eq('tablenames', $referenceQueryBuilder->createNamedParameter(self::PHOTO_TABLE)),
                $referenceQueryBuilder->expr()->eq('fieldname', $referenceQueryBuilder->createNamedParameter('file')),
                $referenceQueryBuilder->expr()->eq('uid_foreign', $referenceQueryBuilder->createNamedParameter($photoUid, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($references as $reference) {
            $file = $this->getFileForPhoto((int)$reference['uid_local']);
            if ($file !== null) {
                $file->getStorage()->deleteFile($file);
            }
        }

        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $connection->delete('sys_file_reference', ['uid_foreign' => $photoUid, 'tablenames' => self::PHOTO_TABLE, 'fieldname' => 'file']);
    }

    private function moveToTemporaryFile(UploadedFileInterface $uploadedFile): string
    {
        $tmpPath = GeneralUtility::tempnam('lightroom-publish-service-upload-');
        $uploadedFile->moveTo($tmpPath);

        return $tmpPath;
    }

    private function sanitizeOriginalFileName(UploadedFileInterface $uploadedFile): string
    {
        $name = $uploadedFile->getClientFilename() ?? 'photo.jpg';
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'photo.jpg';

        return $name === '' ? 'photo.jpg' : $name;
    }
}
