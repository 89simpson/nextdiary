<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EntrySymptomMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary_entry_symptoms', EntrySymptom::class);
    }

    /**
     * @return array Array of ['id' => int, 'name' => string]
     * @throws Exception
     */
    public function findSymptomsByEntry(int $entryId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('s.id', 's.symptom_name', 's.category')
            ->from($this->getTableName(), 'es')
            ->innerJoin('es', 'diary_symptoms', 's', $qb->expr()->eq('es.symptom_id', 's.id'))
            ->where($qb->expr()->eq('es.entry_id', $qb->createNamedParameter($entryId)))
            ->orderBy('s.symptom_name', 'ASC');

        $result = $qb->executeQuery();
        $symptoms = [];
        while ($row = $result->fetch()) {
            $symptoms[] = [
                'id' => (int) $row['id'],
                'name' => $row['symptom_name'],
                'category' => $row['category'],
            ];
        }
        $result->closeCursor();

        return $symptoms;
    }

    /**
     * @return int[]
     * @throws Exception
     */
    public function findEntryIdsBySymptom(int $symptomId, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('entry_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('symptom_id', $qb->createNamedParameter($symptomId)))
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
     * Remove all symptom associations for a user's entries.
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
     * @throws Exception
     */
    public function attach(int $entryId, int $symptomId): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)))
            ->andWhere($qb->expr()->eq('symptom_id', $qb->createNamedParameter($symptomId)));

        $result = $qb->executeQuery();
        $count = (int) $result->fetchOne();
        $result->closeCursor();

        if ($count > 0) {
            return;
        }

        $es = new EntrySymptom();
        $es->setEntryId($entryId);
        $es->setSymptomId($symptomId);
        $this->insert($es);
    }

    /**
     * Move all entry associations from one symptom to another.
     *
     * Rows that would collide with an existing association on the target symptom
     * are removed first, then the remaining rows are re-pointed to the target.
     *
     * @throws Exception
     */
    public function reassign(int $fromId, int $toId): void
    {
        // Entries already associated with the target symptom
        $qb = $this->db->getQueryBuilder();
        $qb->select('entry_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('symptom_id', $qb->createNamedParameter($toId, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $existing = [];
        while ($row = $result->fetch()) {
            $existing[] = (int) $row['entry_id'];
        }
        $result->closeCursor();

        // Drop source rows that would become duplicates on the target symptom
        if (!empty($existing)) {
            $deleteQb = $this->db->getQueryBuilder();
            $deleteQb->delete($this->getTableName())
                ->where($deleteQb->expr()->eq('symptom_id', $deleteQb->createNamedParameter($fromId, IQueryBuilder::PARAM_INT)))
                ->andWhere($deleteQb->expr()->in('entry_id', $deleteQb->createNamedParameter($existing, IQueryBuilder::PARAM_INT_ARRAY)));
            $deleteQb->executeStatement();
        }

        // Re-point the remaining associations to the target symptom
        $updateQb = $this->db->getQueryBuilder();
        $updateQb->update($this->getTableName())
            ->set('symptom_id', $updateQb->createNamedParameter($toId, IQueryBuilder::PARAM_INT))
            ->where($updateQb->expr()->eq('symptom_id', $updateQb->createNamedParameter($fromId, IQueryBuilder::PARAM_INT)));
        $updateQb->executeStatement();
    }

    /**
     * Remove all associations for a given symptom.
     *
     * @throws Exception
     */
    public function deleteByRefId(int $id): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('symptom_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
