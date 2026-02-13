<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class SymptomMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary_symptoms', Symptom::class);
    }

    /**
     * @return Symptom[]
     * @throws Exception
     */
    public function findByUser(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->orderBy('symptom_name', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @throws Exception
     */
    public function findByName(string $uid, string $name): Symptom
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('symptom_name', $qb->createNamedParameter($name)));

        return $this->findEntity($qb);
    }

    /**
     * @throws Exception
     */
    public function findOrCreate(string $uid, string $name): Symptom
    {
        try {
            return $this->findByName($uid, $name);
        } catch (DoesNotExistException $e) {
            $symptom = new Symptom();
            $symptom->setUid($uid);
            $symptom->setSymptomName($name);
            $symptom->setCreatedAt(new \DateTime());
            return $this->insert($symptom);
        }
    }

    /**
     * @return array Array of ['id', 'name', 'count']
     * @throws Exception
     */
    public function findByUserWithCounts(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('s.id', 's.symptom_name', 's.category')
            ->selectAlias($qb->func()->count('es.id'), 'entry_count')
            ->from($this->getTableName(), 's')
            ->leftJoin('s', 'diary_entry_symptoms', 'es', $qb->expr()->eq('s.id', 'es.symptom_id'))
            ->where($qb->expr()->eq('s.uid', $qb->createNamedParameter($uid)))
            ->groupBy('s.id', 's.symptom_name', 's.category')
            ->having($qb->expr()->gt($qb->func()->count('es.id'), $qb->createNamedParameter(0)))
            ->orderBy('entry_count', 'DESC');

        $result = $qb->executeQuery();
        $symptoms = [];
        while ($row = $result->fetch()) {
            $symptoms[] = [
                'id' => (int) $row['id'],
                'name' => $row['symptom_name'],
                'category' => $row['category'],
                'count' => (int) $row['entry_count'],
            ];
        }
        $result->closeCursor();

        return $symptoms;
    }

    /**
     * Delete all symptoms for a user.
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
    public function deleteUnusedSymptoms(string $uid): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->andWhere(
                $qb->expr()->notIn('id', $qb->createFunction(
                    'SELECT DISTINCT `symptom_id` FROM `*PREFIX*diary_entry_symptoms`'
                ))
            );

        return $qb->executeStatement();
    }
}
