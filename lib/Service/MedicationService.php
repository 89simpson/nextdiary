<?php

namespace OCA\NextDiary\Service;

use OCA\NextDiary\Db\EntryMedicationMapper;
use OCA\NextDiary\Db\Medication;
use OCA\NextDiary\Db\MedicationMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\IConfig;

class MedicationService
{
    private MedicationMapper $medicationMapper;
    private EntryMedicationMapper $entryMedicationMapper;
    private IConfig $config;

    public function __construct(MedicationMapper $medicationMapper, EntryMedicationMapper $entryMedicationMapper, IConfig $config)
    {
        $this->medicationMapper = $medicationMapper;
        $this->entryMedicationMapper = $entryMedicationMapper;
        $this->config = $config;
    }

    /**
     * Whether automatic cleanup of unused medications is enabled for the user.
     */
    private function autoCleanupEnabled(string $uid): bool
    {
        return $this->config->getUserValue($uid, 'nextdiary', 'auto_cleanup_unused', 'true') === 'true';
    }

    /**
     * Sync medications for an entry: detach old, attach new.
     *
     * @param string $uid User ID
     * @param int $entryId Entry ID
     * @param string[] $medicationNames Array of medication names
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null]
     * @throws Exception
     */
    public function syncMedicationsForEntry(string $uid, int $entryId, array $medicationNames): array
    {
        $this->entryMedicationMapper->detachAllFromEntry($entryId);

        $result = [];
        foreach ($medicationNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $medication = $this->medicationMapper->findOrCreate($uid, $name);
            $this->entryMedicationMapper->attach($entryId, $medication->getId());
            $result[] = [
                'id' => $medication->getId(),
                'name' => $medication->getMedicationName(),
                'category' => $medication->getCategory(),
            ];
        }

        if ($this->autoCleanupEnabled($uid)) {
            $this->medicationMapper->deleteUnusedMedications($uid);
        }

        return $result;
    }

    /**
     * Get medications for a specific entry.
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null]
     * @throws Exception
     */
    public function getMedicationsForEntry(int $entryId): array
    {
        return $this->entryMedicationMapper->findMedicationsByEntry($entryId);
    }

    /**
     * Get medication cloud for a user (medications with counts).
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null, 'count' => int]
     * @throws Exception
     */
    public function getMedicationCloud(string $uid): array
    {
        return $this->medicationMapper->findByUserWithCounts($uid);
    }

    /**
     * Get a single medication owned by the user.
     *
     * @throws Exception
     * @throws DoesNotExistException When the medication does not exist or is not owned by the user
     */
    public function getMedicationById(string $uid, int $id): Medication
    {
        return $this->medicationMapper->findByIdAndUser($uid, $id);
    }

    /**
     * Get entry IDs by medication.
     *
     * @return int[]
     * @throws Exception
     */
    public function getEntryIdsByMedication(int $medicationId, int $limit = 50, int $offset = 0): array
    {
        return $this->entryMedicationMapper->findEntryIdsByMedication($medicationId, $limit, $offset);
    }

    /**
     * Rename a medication. If a medication with the new name already exists, merge into it.
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null, 'count' => int]
     * @throws Exception
     * @throws DoesNotExistException When the medication does not exist or is not owned by the user
     * @throws \InvalidArgumentException When the new name is empty
     */
    public function renameMedication(string $uid, int $id, string $newName): array
    {
        $newName = trim($newName);
        if ($newName === '') {
            throw new \InvalidArgumentException('empty name');
        }

        $medication = $this->medicationMapper->findByIdAndUser($uid, $id);
        if ($medication->getMedicationName() === $newName) {
            return $this->getMedicationCloud($uid);
        }

        try {
            $existing = $this->medicationMapper->findByName($uid, $newName);
            $this->entryMedicationMapper->reassign($id, $existing->getId());
            $this->medicationMapper->delete($medication);
        } catch (DoesNotExistException $e) {
            $medication->setMedicationName($newName);
            $this->medicationMapper->update($medication);
        }

        return $this->getMedicationCloud($uid);
    }

    /**
     * Delete a medication entirely, removing it from all entries.
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null, 'count' => int]
     * @throws Exception
     * @throws DoesNotExistException When the medication does not exist or is not owned by the user
     */
    public function deleteMedication(string $uid, int $id): array
    {
        $medication = $this->medicationMapper->findByIdAndUser($uid, $id);
        $this->entryMedicationMapper->deleteByRefId($id);
        $this->medicationMapper->delete($medication);

        return $this->getMedicationCloud($uid);
    }

    /**
     * Remove all medications from an entry.
     *
     * @throws Exception
     */
    public function removeMedicationsFromEntry(string $uid, int $entryId): void
    {
        $this->entryMedicationMapper->detachAllFromEntry($entryId);

        if ($this->autoCleanupEnabled($uid)) {
            $this->medicationMapper->deleteUnusedMedications($uid);
        }
    }
}
