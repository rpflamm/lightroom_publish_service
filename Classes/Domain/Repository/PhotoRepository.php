<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use RPflamm\LightroomPublishService\Domain\Model\Photo;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class PhotoRepository
{
    private const TABLE = 'tx_lightroompublishservice_domain_model_photo';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function findByUid(int $uid): ?Photo
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return $row ? Photo::fromDatabaseRow($row, $this->resolveFileUid($uid)) : null;
    }

    /**
     * @return Photo[]
     */
    public function findByCollection(int $collectionUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('collection', $queryBuilder->createNamedParameter($collectionUid, ParameterType::INTEGER)))
            ->orderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            fn (array $row) => Photo::fromDatabaseRow($row, $this->resolveFileUid((int)$row['uid'])),
            $rows,
        );
    }

    /**
     * @return Photo[]
     */
    public function findUpdatedSince(int $timestamp, ?int $collectionUid = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($timestamp, ParameterType::INTEGER)))
            ->orderBy('tstamp', 'ASC');

        if ($collectionUid !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('collection', $queryBuilder->createNamedParameter($collectionUid, ParameterType::INTEGER)),
            );
        }

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        return array_map(
            fn (array $row) => Photo::fromDatabaseRow($row, $this->resolveFileUid((int)$row['uid'])),
            $rows,
        );
    }

    private function resolveFileUid(int $photoUid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $fileUid = $queryBuilder
            ->select('uid_local')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('file')),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($photoUid, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchOne();

        return $fileUid !== false ? (int)$fileUid : null;
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
        if ($data === []) {
            return;
        }

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
