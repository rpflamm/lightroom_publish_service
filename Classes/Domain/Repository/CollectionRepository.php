<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use RPflamm\LightroomPublishService\Domain\Model\Collection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class CollectionRepository
{
    private const TABLE = 'tx_lightroompublishservice_domain_model_collection';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function findByUid(int $uid): ?Collection
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return $row ? Collection::fromDatabaseRow($row) : null;
    }

    /**
     * @return Collection[]
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(Collection::fromDatabaseRow(...), $rows);
    }

    /**
     * @return Collection[]
     */
    public function findByParent(?int $parent): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('sorting', 'ASC');

        if ($parent === null) {
            $queryBuilder->where($queryBuilder->expr()->eq('parent', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)));
        } else {
            $queryBuilder->where($queryBuilder->expr()->eq('parent', $queryBuilder->createNamedParameter($parent, ParameterType::INTEGER)));
        }

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        return array_map(Collection::fromDatabaseRow(...), $rows);
    }

    public function countPhotos(int $collectionUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_lightroompublishservice_domain_model_photo');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_lightroompublishservice_domain_model_photo')
            ->where($queryBuilder->expr()->eq('collection', $queryBuilder->createNamedParameter($collectionUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();
    }

    public function nameExistsUnderParent(string $name, ?int $parent, ?int $excludeUid = null): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('name', $queryBuilder->createNamedParameter($name)),
                $queryBuilder->expr()->eq('parent', $queryBuilder->createNamedParameter($parent ?? 0, ParameterType::INTEGER)),
            );

        if ($excludeUid !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($excludeUid, ParameterType::INTEGER)),
            );
        }

        return (int)$queryBuilder->executeQuery()->fetchOne() > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $connection->insert(self::TABLE, array_merge($data, [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
        ]));

        return (int)$connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $uid, array $data): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->update(
            self::TABLE,
            array_merge($data, ['tstamp' => $GLOBALS['EXEC_TIME'] ?? time()]),
            ['uid' => $uid],
        );
    }

    public function delete(int $uid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->update(self::TABLE, ['deleted' => 1], ['uid' => $uid]);
    }
}
