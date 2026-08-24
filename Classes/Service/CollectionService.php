<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Service;

use RPflamm\LightroomPublishService\Domain\Model\Collection;
use RPflamm\LightroomPublishService\Domain\Repository\CollectionRepository;
use RPflamm\LightroomPublishService\Domain\Repository\PhotoRepository;
use RPflamm\LightroomPublishService\Exception\CollectionNotFoundException;
use RPflamm\LightroomPublishService\Exception\ConflictException;
use RPflamm\LightroomPublishService\Exception\ValidationException;

final class CollectionService
{
    private const VALID_TYPES = ['collection', 'collection_set'];

    public function __construct(
        private readonly CollectionRepository $collectionRepository,
        private readonly PhotoRepository $photoRepository,
        private readonly FileUploadService $fileUploadService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        return array_map(
            fn(Collection $collection) => $this->toApiArray($collection),
            $this->collectionRepository->findAll(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('"name" is required.', 'name_required');
        }

        $type = (string) ($payload['type'] ?? 'collection');
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new ValidationException(sprintf('"type" must be one of: %s.', implode(', ', self::VALID_TYPES)), 'invalid_type');
        }

        $parent = isset($payload['parent']) ? (int) $payload['parent'] : null;
        $this->assertValidParent($parent);

        if ($this->collectionRepository->nameExistsUnderParent($name, $parent)) {
            throw new ConflictException('A collection with this name already exists under the given parent.', 'duplicate_name');
        }

        $uid = $this->collectionRepository->create([
            'name' => $name,
            'collection_type' => $type,
            'parent' => $parent ?? 0,
            'description' => (string) ($payload['description'] ?? ''),
        ]);

        return $this->toApiArray($this->getOrFail($uid));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $uid): array
    {
        return $this->toApiArray($this->getOrFail($uid));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function update(int $uid, array $payload): array
    {
        $collection = $this->getOrFail($uid);
        $data = [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                throw new ValidationException('"name" must not be empty.', 'name_required');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('parent', $payload)) {
            $parent = $payload['parent'] !== null ? (int) $payload['parent'] : null;
            if ($parent === $uid) {
                throw new ValidationException('A collection cannot be its own parent.', 'invalid_parent');
            }
            $this->assertValidParent($parent);
            $data['parent'] = $parent ?? 0;
        }

        if (array_key_exists('description', $payload)) {
            $data['description'] = (string) $payload['description'];
        }

        if ($data !== []) {
            $checkName = $data['name'] ?? $collection->name;
            $checkParent = array_key_exists('parent', $data) ? ($data['parent'] !== 0 ? $data['parent'] : null) : $collection->parent;
            if ($this->collectionRepository->nameExistsUnderParent($checkName, $checkParent, $uid)) {
                throw new ConflictException('A collection with this name already exists under the given parent.', 'duplicate_name');
            }

            $this->collectionRepository->update($uid, $data);
        }

        return $this->toApiArray($this->getOrFail($uid));
    }

    public function delete(int $uid, bool $cascade): void
    {
        $this->getOrFail($uid);

        $childCollections = $this->collectionRepository->findByParent($uid);
        $photoCount = $this->collectionRepository->countPhotos($uid);

        if (($childCollections !== [] || $photoCount > 0) && !$cascade) {
            throw new ConflictException(
                'Collection is not empty. Pass ?cascade=1 to delete it together with its contents.',
                'not_empty',
            );
        }

        foreach ($childCollections as $child) {
            $this->delete($child->uid, true);
        }

        foreach ($this->photoRepository->findByCollection($uid) as $photo) {
            $this->fileUploadService->deletePhotoFileReferences($photo->uid);
            $this->photoRepository->delete($photo->uid);
        }

        $this->collectionRepository->delete($uid);
    }

    public function assertExists(int $uid): void
    {
        $this->getOrFail($uid);
    }

    /**
     * A collection may only be nested under a collection set -- collections
     * are always leaves and can never have children of their own.
     */
    private function assertValidParent(?int $parent): void
    {
        if ($parent === null) {
            return;
        }

        $parentCollection = $this->collectionRepository->findByUid($parent);
        if ($parentCollection === null) {
            throw new CollectionNotFoundException(sprintf('Parent collection %d does not exist.', $parent));
        }

        if (!$parentCollection->isCollectionSet()) {
            throw new ValidationException('"parent" must reference a collection set.', 'invalid_parent_type');
        }
    }

    private function getOrFail(int $uid): Collection
    {
        $collection = $this->collectionRepository->findByUid($uid);
        if ($collection === null) {
            throw new CollectionNotFoundException(sprintf('Collection %d does not exist.', $uid));
        }

        return $collection;
    }

    /**
     * @return array<string, mixed>
     */
    private function toApiArray(Collection $collection): array
    {
        return [
            'id' => (string) $collection->uid,
            'name' => $collection->name,
            'type' => $collection->collectionType,
            'parent' => $collection->parent !== null ? (string) $collection->parent : null,
            'description' => $collection->description,
            'photoCount' => $this->collectionRepository->countPhotos($collection->uid),
        ];
    }
}
