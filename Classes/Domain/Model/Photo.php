<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Domain\Model;

final class Photo
{
    public function __construct(
        public readonly int $uid,
        public readonly int $collection,
        public readonly ?int $fileUid,
        public readonly string $contentHash,
        public readonly int $tstamp,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row, ?int $fileUid = null): self
    {
        return new self(
            uid: (int)$row['uid'],
            collection: (int)$row['collection'],
            fileUid: $fileUid,
            contentHash: (string)($row['content_hash'] ?? ''),
            tstamp: (int)($row['tstamp'] ?? 0),
        );
    }
}
