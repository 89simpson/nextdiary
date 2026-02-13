<?php

declare(strict_types=1);

namespace OCA\NextDiary\Listener;

use OCA\NextDiary\Db\EntryFileMapper;
use OCA\NextDiary\Db\EntryMapper;
use OCA\NextDiary\Db\EntryMedicationMapper;
use OCA\NextDiary\Db\EntrySymptomMapper;
use OCA\NextDiary\Db\EntryTagMapper;
use OCA\NextDiary\Db\MedicationMapper;
use OCA\NextDiary\Db\SymptomMapper;
use OCA\NextDiary\Db\TagMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

class UserDeletedListener implements IEventListener
{
    private LoggerInterface $logger;
    private EntryMapper $entryMapper;
    private EntryTagMapper $entryTagMapper;
    private EntrySymptomMapper $entrySymptomMapper;
    private EntryMedicationMapper $entryMedicationMapper;
    private EntryFileMapper $entryFileMapper;
    private TagMapper $tagMapper;
    private SymptomMapper $symptomMapper;
    private MedicationMapper $medicationMapper;
    private IRootFolder $rootFolder;

    public function __construct(
        EntryMapper $entryMapper,
        EntryTagMapper $entryTagMapper,
        EntrySymptomMapper $entrySymptomMapper,
        EntryMedicationMapper $entryMedicationMapper,
        EntryFileMapper $entryFileMapper,
        TagMapper $tagMapper,
        SymptomMapper $symptomMapper,
        MedicationMapper $medicationMapper,
        IRootFolder $rootFolder,
        LoggerInterface $logger
    ) {
        $this->entryMapper = $entryMapper;
        $this->entryTagMapper = $entryTagMapper;
        $this->entrySymptomMapper = $entrySymptomMapper;
        $this->entryMedicationMapper = $entryMedicationMapper;
        $this->entryFileMapper = $entryFileMapper;
        $this->tagMapper = $tagMapper;
        $this->symptomMapper = $symptomMapper;
        $this->medicationMapper = $medicationMapper;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof UserDeletedEvent)) {
            return;
        }

        $uid = $event->getUser()->getUID();

        try {
            // 1. Delete junction tables (entry_tags, entry_symptoms, entry_meds)
            $this->entryTagMapper->deleteAllForUser($uid);
            $this->entrySymptomMapper->deleteAllForUser($uid);
            $this->entryMedicationMapper->deleteAllForUser($uid);

            // 2. Delete files from disk
            $files = $this->entryFileMapper->findByUser($uid);
            foreach ($files as $file) {
                try {
                    $userFolder = $this->rootFolder->getUserFolder($uid);
                    $node = $userFolder->get($file->getFilePath());
                    $node->delete();
                } catch (NotFoundException $e) {
                    // File already gone
                }
            }

            // 3. Try to remove NextDiary folder
            try {
                $userFolder = $this->rootFolder->getUserFolder($uid);
                $appFolder = $userFolder->get('NextDiary');
                $appFolder->delete();
            } catch (NotFoundException $e) {
                // Folder doesn't exist
            }

            // 4. Delete file records from DB
            $this->entryFileMapper->deleteAllByUser($uid);

            // 5. Delete master tables (tags, symptoms, medications)
            $this->tagMapper->deleteAllByUser($uid);
            $this->symptomMapper->deleteAllByUser($uid);
            $this->medicationMapper->deleteAllByUser($uid);

            // 6. Delete diary entries
            $deletedEntries = $this->entryMapper->deleteAllEntriesForUser($uid);

            $this->logger->info("[NextDiary] All data deleted for user $uid ($deletedEntries entries)");
        } catch (\Exception $e) {
            $this->logger->error("[NextDiary] Error deleting data for user $uid: " . $e->getMessage());
        }
    }
}
