<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Domain\Model;

final class Collection
{
    public function __construct(
        public readonly int $uid,
        public readonly string $name,
        public readonly string $collectionType,
        public readonly ?int $parent,
        public readonly string $description,
        public readonly int $sorting,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            uid: (int) $row['uid'],
            name: (string) $row['name'],
            collectionType: (string) $row['collection_type'],
            parent: (int) ($row['parent'] ?? 0) > 0 ? (int) $row['parent'] : null,
            description: (string) ($row['description'] ?? ''),
            sorting: (int) ($row['sorting'] ?? 0),
        );
    }

    public function isCollectionSet(): bool
    {
        return $this->collectionType === 'collection_set';
    }
}
