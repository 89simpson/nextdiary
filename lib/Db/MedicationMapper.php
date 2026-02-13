<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class MedicationMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary_medications', Medication::class);
    }

    /**
     * @return Medication[]
     * @throws Exception
     */
    public function findByUser(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->orderBy('medication_name', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @throws Exception
     */
    public function findByName(string $uid, string $name): Medication
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('medication_name', $qb->createNamedParameter($name)));

        return $this->findEntity($qb);
    }

    /**
     * @throws Exception
     */
    public function findOrCreate(string $uid, string $name): Medication
    {
        try {
            return $this->findByName($uid, $name);
        } catch (DoesNotExistException $e) {
            $medication = new Medication();
            $medication->setUid($uid);
            $medication->setMedicationName($name);
            $medication->setCreatedAt(new \DateTime());
            return $this->insert($medication);
        }
    }

    /**
     * @return array Array of ['id', 'name', 'count']
     * @throws Exception
     */
    public function findByUserWithCounts(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.medication_name', 'm.category')
            ->selectAlias($qb->func()->count('em.id'), 'entry_count')
            ->from($this->getTableName(), 'm')
            ->leftJoin('m', 'diary_entry_meds', 'em', $qb->expr()->eq('m.id', 'em.medication_id'))
            ->where($qb->expr()->eq('m.uid', $qb->createNamedParameter($uid)))
            ->groupBy('m.id', 'm.medication_name', 'm.category')
            ->having($qb->expr()->gt($qb->func()->count('em.id'), $qb->createNamedParameter(0)))
            ->orderBy('entry_count', 'DESC');

        $result = $qb->executeQuery();
        $medications = [];
        while ($row = $result->fetch()) {
            $medications[] = [
                'id' => (int) $row['id'],
                'name' => $row['medication_name'],
                'category' => $row['category'],
                'count' => (int) $row['entry_count'],
            ];
        }
        $result->closeCursor();

        return $medications;
    }

    /**
     * Delete all medications for a user.
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
    public function deleteUnusedMedications(string $uid): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->andWhere(
                $qb->expr()->notIn('id', $qb->createFunction(
                    'SELECT DISTINCT `medication_id` FROM `*PREFIX*diary_entry_meds`'
                ))
            );

        return $qb->executeStatement();
    }
}
