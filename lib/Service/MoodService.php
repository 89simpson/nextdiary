<?php

namespace OCA\NextDiary\Service;

use OCA\NextDiary\Db\EntrySymptomMapper;
use OCA\NextDiary\Db\Symptom;
use OCA\NextDiary\Db\SymptomMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\IConfig;

class MoodService
{
    private SymptomMapper $symptomMapper;
    private EntrySymptomMapper $entrySymptomMapper;
    private IConfig $config;

    public function __construct(SymptomMapper $symptomMapper, EntrySymptomMapper $entrySymptomMapper, IConfig $config)
    {
        $this->symptomMapper = $symptomMapper;
        $this->entrySymptomMapper = $entrySymptomMapper;
        $this->config = $config;
    }

    /**
     * Whether automatic cleanup of unused symptoms is enabled for the user.
     */
    private function autoCleanupEnabled(string $uid): bool
    {
        return $this->config->getUserValue($uid, 'nextdiary', 'auto_cleanup_unused', 'true') === 'true';
    }

    /**
     * Encode ratings (mood, wellbeing) to JSON string.
     *
     * @param array|null $ratings ['mood' => int 1-5, 'wellbeing' => int 1-5]
     * @return string|null JSON string or null
     */
    public function encodeRatings(?array $ratings): ?string
    {
        if ($ratings === null || empty($ratings)) {
            return null;
        }

        $clean = [];
        if (isset($ratings['mood'])) {
            $clean['mood'] = max(1, min(5, (int) $ratings['mood']));
        }
        if (isset($ratings['wellbeing'])) {
            $clean['wellbeing'] = max(1, min(5, (int) $ratings['wellbeing']));
        }

        return empty($clean) ? null : json_encode($clean);
    }

    /**
     * Decode ratings JSON string to array.
     *
     * @return array|null ['mood' => int, 'wellbeing' => int]
     */
    public function decodeRatings(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        return json_decode($json, true);
    }

    /**
     * Sync symptoms for an entry: detach old, attach new.
     *
     * @param string $uid User ID
     * @param int $entryId Entry ID
     * @param string[] $symptomNames Array of symptom names
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null]
     * @throws Exception
     */
    public function syncSymptomsForEntry(string $uid, int $entryId, array $symptomNames): array
    {
        $this->entrySymptomMapper->detachAllFromEntry($entryId);

        $result = [];
        foreach ($symptomNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $symptom = $this->symptomMapper->findOrCreate($uid, $name);
            $this->entrySymptomMapper->attach($entryId, $symptom->getId());
            $result[] = [
                'id' => $symptom->getId(),
                'name' => $symptom->getSymptomName(),
                'category' => $symptom->getCategory(),
            ];
        }

        if ($this->autoCleanupEnabled($uid)) {
            $this->symptomMapper->deleteUnusedSymptoms($uid);
        }

        return $result;
    }

    /**
     * Get symptoms for a specific entry.
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null]
     * @throws Exception
     */
    public function getSymptomsForEntry(int $entryId): array
    {
        return $this->entrySymptomMapper->findSymptomsByEntry($entryId);
    }

    /**
     * Get symptom cloud for a user (symptoms with counts).
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null, 'count' => int]
     * @throws Exception
     */
    public function getSymptomCloud(string $uid): array
    {
        return $this->symptomMapper->findByUserWithCounts($uid);
    }

    /**
     * Get a single symptom owned by the user.
     *
     * @throws Exception
     * @throws DoesNotExistException When the symptom does not exist or is not owned by the user
     */
    public function getSymptomById(string $uid, int $id): Symptom
    {
        return $this->symptomMapper->findByIdAndUser($uid, $id);
    }

    /**
     * Get entry IDs by symptom.
     *
     * @return int[]
     * @throws Exception
     */
    public function getEntryIdsBySymptom(int $symptomId, int $limit = 50, int $offset = 0): array
    {
        return $this->entrySymptomMapper->findEntryIdsBySymptom($symptomId, $limit, $offset);
    }

    /**
     * Rename a symptom. If a symptom with the new name already exists, merge into it.
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null, 'count' => int]
     * @throws Exception
     * @throws DoesNotExistException When the symptom does not exist or is not owned by the user
     * @throws \InvalidArgumentException When the new name is empty
     */
    public function renameSymptom(string $uid, int $id, string $newName): array
    {
        $newName = trim($newName);
        if ($newName === '') {
            throw new \InvalidArgumentException('empty name');
        }

        $symptom = $this->symptomMapper->findByIdAndUser($uid, $id);
        if ($symptom->getSymptomName() === $newName) {
            return $this->getSymptomCloud($uid);
        }

        try {
            $existing = $this->symptomMapper->findByName($uid, $newName);
            $this->entrySymptomMapper->reassign($id, $existing->getId());
            $this->symptomMapper->delete($symptom);
        } catch (DoesNotExistException $e) {
            $symptom->setSymptomName($newName);
            $this->symptomMapper->update($symptom);
        }

        return $this->getSymptomCloud($uid);
    }

    /**
     * Delete a symptom entirely, removing it from all entries.
     *
     * @return array Array of ['id' => int, 'name' => string, 'category' => string|null, 'count' => int]
     * @throws Exception
     * @throws DoesNotExistException When the symptom does not exist or is not owned by the user
     */
    public function deleteSymptom(string $uid, int $id): array
    {
        $symptom = $this->symptomMapper->findByIdAndUser($uid, $id);
        $this->entrySymptomMapper->deleteByRefId($id);
        $this->symptomMapper->delete($symptom);

        return $this->getSymptomCloud($uid);
    }

    /**
     * Remove all symptoms from an entry.
     *
     * @throws Exception
     */
    public function removeSymptomsFromEntry(string $uid, int $entryId): void
    {
        $this->entrySymptomMapper->detachAllFromEntry($entryId);

        if ($this->autoCleanupEnabled($uid)) {
            $this->symptomMapper->deleteUnusedSymptoms($uid);
        }
    }
}
