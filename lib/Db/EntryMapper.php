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
     * Find a single entry by integer ID.
     *
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     */
    public function findById(int $id): Entry
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id))
            );

        return $this->findEntity($qb);
    }

    /**
     * Find all entries for a given user and date.
     *
     * @return Entry[]
     * @throws Exception
     */
    public function findByDate(string $uid, string $date): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )->andWhere(
                $qb->expr()->eq('entry_date', $qb->createNamedParameter($date))
            )
            ->orderBy('created_at', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find the first entry for user+date (legacy compatibility).
     *
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     */
    public function find(string $uid, string $date): Entry
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )->andWhere(
                $qb->expr()->eq('entry_date', $qb->createNamedParameter($date))
            )
            ->orderBy('created_at', 'ASC')
            ->setMaxResults(1);

        return $this->findEntity($qb);
    }

    /**
     * Find all diary entries for the given user id, ordered by date ascending.
     *
     * @return Entry[]
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
            ->orderBy('entry_date', 'ASC')
            ->addOrderBy('created_at', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find the last $amount entries ordered by creation time descending.
     *
     * @return Entry[]
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
            ->orderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Delete all entries for the given user.
     *
     * @throws Exception
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
     * Find all entries for a given user within a date range.
     *
     * @return Entry[]
     * @throws Exception
     */
    public function findByDateRange(string $uid, string $startDate, string $endDate): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )->andWhere(
                $qb->expr()->gte('entry_date', $qb->createNamedParameter($startDate))
            )->andWhere(
                $qb->expr()->lte('entry_date', $qb->createNamedParameter($endDate))
            )
            ->orderBy('entry_date', 'ASC')
            ->addOrderBy('created_at', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find all distinct dates that have entries for the given user.
     *
     * @return string[]
     * @throws Exception
     */
    public function findAllDates(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->selectDistinct('entry_date')
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
