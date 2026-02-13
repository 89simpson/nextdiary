<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class EntryMedicationMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'diary_entry_meds', EntryMedication::class);
    }

    /**
     * @return array Array of ['id' => int, 'name' => string]
     * @throws Exception
     */
    public function findMedicationsByEntry(int $entryId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('m.id', 'm.medication_name', 'm.category')
            ->from($this->getTableName(), 'em')
            ->innerJoin('em', 'diary_medications', 'm', $qb->expr()->eq('em.medication_id', 'm.id'))
            ->where($qb->expr()->eq('em.entry_id', $qb->createNamedParameter($entryId)))
            ->orderBy('m.medication_name', 'ASC');

        $result = $qb->executeQuery();
        $medications = [];
        while ($row = $result->fetch()) {
            $medications[] = [
                'id' => (int) $row['id'],
                'name' => $row['medication_name'],
                'category' => $row['category'],
            ];
        }
        $result->closeCursor();

        return $medications;
    }

    /**
     * @return int[]
     * @throws Exception
     */
    public function findEntryIdsByMedication(int $medicationId, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('entry_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('medication_id', $qb->createNamedParameter($medicationId)))
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
     * Remove all medication associations for a user's entries.
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
    public function attach(int $entryId, int $medicationId): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)))
            ->andWhere($qb->expr()->eq('medication_id', $qb->createNamedParameter($medicationId)));

        $result = $qb->executeQuery();
        $count = (int) $result->fetchOne();
        $result->closeCursor();

        if ($count > 0) {
            return;
        }

        $em = new EntryMedication();
        $em->setEntryId($entryId);
        $em->setMedicationId($medicationId);
        $this->insert($em);
    }
}
