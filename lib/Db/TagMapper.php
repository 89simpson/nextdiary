<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class TagMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary_tags', Tag::class);
    }

    /**
     * @return Tag[]
     * @throws Exception
     */
    public function findByUser(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->orderBy('tag_name', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @throws Exception
     */
    public function findByName(string $uid, string $tagName): Tag
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('tag_name', $qb->createNamedParameter($tagName)));

        return $this->findEntity($qb);
    }

    /**
     * @throws Exception
     */
    public function findOrCreate(string $uid, string $tagName): Tag
    {
        try {
            return $this->findByName($uid, $tagName);
        } catch (DoesNotExistException $e) {
            $tag = new Tag();
            $tag->setUid($uid);
            $tag->setTagName($tagName);
            $tag->setCreatedAt(new \DateTime());
            return $this->insert($tag);
        }
    }

    /**
     * Get tags with entry counts for a user.
     *
     * @return array Array of ['id' => int, 'name' => string, 'count' => int]
     * @throws Exception
     */
    public function findByUserWithCounts(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.id', 't.tag_name', 't.created_at')
            ->selectAlias($qb->func()->count('et.id'), 'entry_count')
            ->from($this->getTableName(), 't')
            ->leftJoin('t', 'diary_entry_tags', 'et', $qb->expr()->eq('t.id', 'et.tag_id'))
            ->where($qb->expr()->eq('t.uid', $qb->createNamedParameter($uid)))
            ->groupBy('t.id', 't.tag_name', 't.created_at')
            ->having($qb->expr()->gt($qb->func()->count('et.id'), $qb->createNamedParameter(0)))
            ->orderBy('entry_count', 'DESC');

        $result = $qb->executeQuery();
        $tags = [];
        while ($row = $result->fetch()) {
            $tags[] = [
                'id' => (int) $row['id'],
                'name' => $row['tag_name'],
                'count' => (int) $row['entry_count'],
                'createdAt' => $row['created_at'],
            ];
        }
        $result->closeCursor();

        return $tags;
    }

    /**
     * Delete all tags for a user.
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
     * @throws Exception
     */
    public function deleteUnusedTags(string $uid): int
    {
        $subQb = $this->db->getQueryBuilder();
        $subQb->select('tag_id')->from('diary_entry_tags');

        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->andWhere(
                $qb->expr()->notIn('id', $qb->createFunction(
                    'SELECT DISTINCT `tag_id` FROM `*PREFIX*diary_entry_tags`'
                ))
            );

        return $qb->executeStatement();
    }
}
