<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class EntryMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary', Entry::class);
    }

    /**
     * Find the diary entry for the given user or date.
     *
     * @return mixed|Entity
     *
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     */
    public function find(string $uid, string $date)
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )->andWhere(
                $qb->expr()->eq('entry_date', $qb->createNamedParameter($date))
            );

        return $this->findEntity($qb);
    }

    /**
     * Find all diary entries for the given user id, ordered by date ascending.
     *
     * @return array|Entity[]
     *
     * @throws Exception
     */
    public function findAll(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )
            ->orderBy('entry_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find the last $amount number of entries ordered by date descending.
     *
     * @return array|Entity[]
     *
     * @throws Exception
     */
    public function findLast(string $uid, int $amount): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )
            ->setMaxResults($amount)
            ->orderBy('entry_date', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Delete all entries for the given user.
     *
     * @throws Exception
     * @returns int Number of deleted entries
     */
    public function deleteAllEntriesForUser(string $uid): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            );

        return $qb->executeStatement();
    }

    /**
     * Find all dates that have entries for the given user.
     *
     * @return array Array of date strings
     *
     * @throws Exception
     */
    public function findAllDates(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('entry_date')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )
            ->orderBy('entry_date', 'ASC');

        $result = $qb->executeQuery();
        $dates = [];
        while ($row = $result->fetch()) {
            $dates[] = $row['entry_date'];
        }
        $result->closeCursor();

        return $dates;
    }
}
