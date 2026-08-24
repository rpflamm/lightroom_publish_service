<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Domain\Model;

final class ApiToken
{
    public function __construct(
        public readonly int $uid,
        public readonly string $name,
        public readonly string $selector,
        public readonly string $verifierHash,
        public readonly int $lastUsedAt,
        public readonly string $lastUsedIp,
        public readonly bool $revoked,
        public readonly int $crdate,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            uid: (int)$row['uid'],
            name: (string)$row['name'],
            selector: (string)$row['selector'],
            verifierHash: (string)$row['verifier_hash'],
            lastUsedAt: (int)($row['last_used_at'] ?? 0),
            lastUsedIp: (string)($row['last_used_ip'] ?? ''),
            revoked: (bool)($row['revoked'] ?? false),
            crdate: (int)($row['crdate'] ?? 0),
        );
    }
}
