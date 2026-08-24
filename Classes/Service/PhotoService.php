<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Service;

use Psr\Http\Message\UploadedFileInterface;
use RPflamm\LightroomPublishService\Domain\Model\Photo;
use RPflamm\LightroomPublishService\Domain\Repository\PhotoRepository;
use RPflamm\LightroomPublishService\Exception\PhotoNotFoundException;
use RPflamm\LightroomPublishService\Exception\ValidationException;

final class PhotoService
{
    public function __construct(
        private readonly PhotoRepository $photoRepository,
        private readonly CollectionService $collectionService,
        private readonly FileUploadService $fileUploadService,
        private readonly MetadataMappingService $metadataMappingService,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function upload(int $collectionUid, UploadedFileInterface $uploadedFile, array $metadata): array
    {
        $this->collectionService->assertExists($collectionUid);
        $this->assertUploadOk($uploadedFile);
        // Validate before touching FAL/DB, so a malformed payload never leaves an orphaned file behind.
        $metadataRow = $this->metadataMappingService->toMetadataRow($metadata);

        $photoUid = $this->photoRepository->create(['collection' => $collectionUid, 'content_hash' => '']);

        $file = $this->fileUploadService->storeUploadedFile($uploadedFile, $collectionUid, $photoUid);
        $this->fileUploadService->linkFileToPhoto($photoUid, $file);
        $contentHash = $this->fileUploadService->hashFile($file);
        $this->photoRepository->update($photoUid, ['content_hash' => $contentHash]);

        $this->metadataMappingService->applyRow($file->getUid(), $metadataRow);

        return $this->toApiArray($this->getOrFail($photoUid));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $photoUid): array
    {
        return $this->toApiArray($this->getOrFail($photoUid));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByCollection(int $collectionUid): array
    {
        $this->collectionService->assertExists($collectionUid);

        return array_map(fn (Photo $photo) => $this->toApiArray($photo), $this->photoRepository->findByCollection($collectionUid));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUpdatedSince(int $timestamp, ?int $collectionUid): array
    {
        return array_map(
            fn (Photo $photo) => $this->toApiArray($photo),
            $this->photoRepository->findUpdatedSince($timestamp, $collectionUid),
        );
    }

    /**
     * File-and-metadata replace (full republish), used for PUT /photos/{id}.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function replace(int $photoUid, UploadedFileInterface $uploadedFile, array $metadata): array
    {
        $photo = $this->getOrFail($photoUid);
        $this->assertUploadOk($uploadedFile);
        // Validate before touching FAL, so a malformed payload never leaves the file half-replaced.
        $metadataRow = $this->metadataMappingService->toMetadataRow($metadata);

        $existingFile = $photo->fileUid !== null ? $this->fileUploadService->getFileForPhoto($photo->fileUid) : null;

        if ($existingFile !== null) {
            $file = $this->fileUploadService->replaceFile($existingFile, $uploadedFile);
        } else {
            $file = $this->fileUploadService->storeUploadedFile($uploadedFile, $photo->collection, $photoUid);
            $this->fileUploadService->linkFileToPhoto($photoUid, $file);
        }

        $contentHash = $this->fileUploadService->hashFile($file);
        $this->photoRepository->update($photoUid, ['content_hash' => $contentHash]);
        $this->metadataMappingService->applyRow($file->getUid(), $metadataRow);

        return $this->toApiArray($this->getOrFail($photoUid));
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function updateMetadata(int $photoUid, array $metadata): array
    {
        $photo = $this->getOrFail($photoUid);
        if ($photo->fileUid !== null) {
            $this->metadataMappingService->applyToFile($photo->fileUid, $metadata);
        }

        return $this->toApiArray($this->getOrFail($photoUid));
    }

    public function delete(int $photoUid): void
    {
        $this->getOrFail($photoUid);
        $this->fileUploadService->deletePhotoFileReferences($photoUid);
        $this->photoRepository->delete($photoUid);
    }

    private function assertUploadOk(UploadedFileInterface $uploadedFile): void
    {
        if ($uploadedFile->getError() !== \UPLOAD_ERR_OK) {
            throw new ValidationException('File upload failed (upload error code ' . $uploadedFile->getError() . ').', 'upload_failed');
        }
    }

    private function getOrFail(int $photoUid): Photo
    {
        $photo = $this->photoRepository->findByUid($photoUid);
        if ($photo === null) {
            throw new PhotoNotFoundException(sprintf('Photo %d does not exist.', $photoUid));
        }

        return $photo;
    }

    /**
     * @return array<string, mixed>
     */
    private function toApiArray(Photo $photo): array
    {
        $publicUrl = null;
        if ($photo->fileUid !== null) {
            $file = $this->fileUploadService->getFileForPhoto($photo->fileUid);
            $publicUrl = $file?->getPublicUrl();
        }

        return $this->metadataMappingService->toApiArray($photo, $publicUrl);
    }
}
