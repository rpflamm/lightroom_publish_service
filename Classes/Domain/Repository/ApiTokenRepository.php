<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use RPflamm\LightroomPublishService\Domain\Model\ApiToken;
use TYPO3\CMS\Core\Database\ConnectionPool;

class ApiTokenRepository
{
    private const TABLE = 'tx_lightroompublishservice_domain_model_apitoken';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function findActiveBySelector(string $selector): ?ApiToken
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('selector', $queryBuilder->createNamedParameter($selector)),
                $queryBuilder->expr()->eq('revoked', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ? ApiToken::fromDatabaseRow($row) : null;
    }

    /**
     * @return ApiToken[]
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(ApiToken::fromDatabaseRow(...), $rows);
    }

    public function findByUid(int $uid): ?ApiToken
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return $row ? ApiToken::fromDatabaseRow($row) : null;
    }

    public function create(string $name, string $selector, string $verifierHash, int $creatorBackendUserUid): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $connection->insert(self::TABLE, [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'cruser_id' => $creatorBackendUserUid,
            'name' => $name,
            'selector' => $selector,
            'verifier_hash' => $verifierHash,
            'revoked' => 0,
        ]);

        return (int) $connection->lastInsertId();
    }

    public function updateLastUsed(int $uid, string $ip): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->update(
            self::TABLE,
            [
                'last_used_at' => $GLOBALS['EXEC_TIME'] ?? time(),
                'last_used_ip' => $ip,
            ],
            ['uid' => $uid],
        );
    }

    public function revoke(int $uid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->update(self::TABLE, ['revoked' => 1], ['uid' => $uid]);
    }
}
