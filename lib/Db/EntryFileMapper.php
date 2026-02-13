<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class EntryFileMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary_entry_files', EntryFile::class);
    }

    /**
     * @return EntryFile[]
     * @throws Exception
     */
    public function findByEntry(int $entryId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)))
            ->orderBy('uploaded_at', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @throws Exception
     */
    public function findById(int $id): EntryFile
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

        return $this->findEntity($qb);
    }

    /**
     * @throws Exception
     */
    public function deleteByEntry(int $entryId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)));

        return $qb->executeStatement();
    }

    /**
     * Delete all file records for a user.
     *
     * @throws Exception
     */
    public function deleteAllByUser(string $uid): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));

        return $qb->executeStatement();
    }

    /**
     * @return EntryFile[]
     * @throws Exception
     */
    public function findByUser(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));

        return $this->findEntities($qb);
    }
}
