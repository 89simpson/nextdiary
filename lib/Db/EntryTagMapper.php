<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class EntryTagMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary_entry_tags', EntryTag::class);
    }

    /**
     * Get tag IDs for an entry.
     *
     * @return int[]
     * @throws Exception
     */
    public function findTagIdsByEntry(int $entryId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('tag_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)));

        $result = $qb->executeQuery();
        $ids = [];
        while ($row = $result->fetch()) {
            $ids[] = (int) $row['tag_id'];
        }
        $result->closeCursor();

        return $ids;
    }

    /**
     * Get tags for an entry (joined with diary_tags).
     *
     * @return array Array of ['id' => int, 'name' => string]
     * @throws Exception
     */
    public function findTagsByEntry(int $entryId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.id', 't.tag_name')
            ->from($this->getTableName(), 'et')
            ->innerJoin('et', 'diary_tags', 't', $qb->expr()->eq('et.tag_id', 't.id'))
            ->where($qb->expr()->eq('et.entry_id', $qb->createNamedParameter($entryId)))
            ->orderBy('t.tag_name', 'ASC');

        $result = $qb->executeQuery();
        $tags = [];
        while ($row = $result->fetch()) {
            $tags[] = [
                'id' => (int) $row['id'],
                'name' => $row['tag_name'],
            ];
        }
        $result->closeCursor();

        return $tags;
    }

    /**
     * Get entry IDs that have a specific tag.
     *
     * @return int[]
     * @throws Exception
     */
    public function findEntryIdsByTag(int $tagId, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('entry_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('tag_id', $qb->createNamedParameter($tagId)))
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $result = $qb->executeQuery();
        $ids = [];
        while ($row = $result->fetch()) {
            $ids[] = (int) $row['entry_id'];
        }
        $result->closeCursor();

        return $ids;
    }

    /**
     * Remove all tags for an entry.
     *
     * @throws Exception
     */
    public function detachAllFromEntry(int $entryId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)));

        return $qb->executeStatement();
    }

    /**
     * Remove all tag associations for a user's entries.
     *
     * @throws Exception
     */
    public function deleteAllForUser(string $uid): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where(
                $qb->expr()->in('entry_id', $qb->createFunction(
                    'SELECT `id` FROM `*PREFIX*diary` WHERE `uid` = ' . $qb->createNamedParameter($uid)
                ))
            );

        return $qb->executeStatement();
    }

    /**
     * Attach a tag to an entry (ignore if already attached).
     *
     * @throws Exception
     */
    public function attach(int $entryId, int $tagId): void
    {
        // Check if already exists
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)))
            ->andWhere($qb->expr()->eq('tag_id', $qb->createNamedParameter($tagId)));

        $result = $qb->executeQuery();
        $count = (int) $result->fetchOne();
        $result->closeCursor();

        if ($count > 0) {
            return;
        }

        $entryTag = new EntryTag();
        $entryTag->setEntryId($entryId);
        $entryTag->setTagId($tagId);
        $this->insert($entryTag);
    }
}
