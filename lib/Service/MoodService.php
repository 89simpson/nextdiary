<?php

namespace OCA\NextDiary\Service;

use OCA\NextDiary\Db\EntrySymptomMapper;
use OCA\NextDiary\Db\SymptomMapper;
use OCP\DB\Exception;

class MoodService
{
    private SymptomMapper $symptomMapper;
    private EntrySymptomMapper $entrySymptomMapper;

    public function __construct(SymptomMapper $symptomMapper, EntrySymptomMapper $entrySymptomMapper)
    {
        $this->symptomMapper = $symptomMapper;
        $this->entrySymptomMapper = $entrySymptomMapper;
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

        $this->symptomMapper->deleteUnusedSymptoms($uid);

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
     * Remove all symptoms from an entry and clean up unused symptoms.
     *
     * @throws Exception
     */
    public function removeSymptomsFromEntry(string $uid, int $entryId): void
    {
        $this->entrySymptomMapper->detachAllFromEntry($entryId);
        $this->symptomMapper->deleteUnusedSymptoms($uid);
    }
}
