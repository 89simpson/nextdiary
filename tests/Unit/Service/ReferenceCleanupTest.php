<?php

namespace OCA\NextDiary\Tests\Unit\Service;

use OCA\NextDiary\Db\EntryMedicationMapper;
use OCA\NextDiary\Db\EntrySymptomMapper;
use OCA\NextDiary\Db\EntryTagMapper;
use OCA\NextDiary\Db\MedicationMapper;
use OCA\NextDiary\Db\SymptomMapper;
use OCA\NextDiary\Db\TagMapper;
use OCA\NextDiary\Service\MedicationService;
use OCA\NextDiary\Service\MoodService;
use OCA\NextDiary\Service\TagService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The "auto_cleanup_unused" setting controls whether reference lists (tags,
 * symptoms, medications) that are no longer attached to any entry are deleted
 * automatically. Enabled => cleanup runs; disabled => empty references are kept
 * so they can be shown and deleted manually.
 */
class ReferenceCleanupTest extends TestCase
{
    /** @var IConfig|MockObject */
    private $config;

    public function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(IConfig::class);
    }

    private function withSetting(string $value): void
    {
        $this->config->method('getUserValue')
            ->with('u1', 'nextdiary', 'auto_cleanup_unused', 'true')
            ->willReturn($value);
    }

    public function testTagCleanupRunsWhenEnabled(): void
    {
        $this->withSetting('true');
        $tagMapper = $this->createMock(TagMapper::class);
        $entryTagMapper = $this->createMock(EntryTagMapper::class);
        $tagMapper->expects($this->once())->method('deleteUnusedTags')->with('u1');

        (new TagService($tagMapper, $entryTagMapper, $this->config))
            ->syncTagsByNames('u1', 5, []);
    }

    public function testTagCleanupSkippedWhenDisabled(): void
    {
        $this->withSetting('false');
        $tagMapper = $this->createMock(TagMapper::class);
        $entryTagMapper = $this->createMock(EntryTagMapper::class);
        $tagMapper->expects($this->never())->method('deleteUnusedTags');

        (new TagService($tagMapper, $entryTagMapper, $this->config))
            ->syncTagsByNames('u1', 5, []);
    }

    public function testSymptomCleanupRunsWhenEnabled(): void
    {
        $this->withSetting('true');
        $symptomMapper = $this->createMock(SymptomMapper::class);
        $entrySymptomMapper = $this->createMock(EntrySymptomMapper::class);
        $symptomMapper->expects($this->once())->method('deleteUnusedSymptoms')->with('u1');

        (new MoodService($symptomMapper, $entrySymptomMapper, $this->config))
            ->syncSymptomsForEntry('u1', 5, []);
    }

    public function testSymptomCleanupSkippedWhenDisabled(): void
    {
        $this->withSetting('false');
        $symptomMapper = $this->createMock(SymptomMapper::class);
        $entrySymptomMapper = $this->createMock(EntrySymptomMapper::class);
        $symptomMapper->expects($this->never())->method('deleteUnusedSymptoms');

        (new MoodService($symptomMapper, $entrySymptomMapper, $this->config))
            ->syncSymptomsForEntry('u1', 5, []);
    }

    public function testMedicationCleanupRunsWhenEnabled(): void
    {
        $this->withSetting('true');
        $medicationMapper = $this->createMock(MedicationMapper::class);
        $entryMedicationMapper = $this->createMock(EntryMedicationMapper::class);
        $medicationMapper->expects($this->once())->method('deleteUnusedMedications')->with('u1');

        (new MedicationService($medicationMapper, $entryMedicationMapper, $this->config))
            ->syncMedicationsForEntry('u1', 5, []);
    }

    public function testMedicationCleanupSkippedWhenDisabled(): void
    {
        $this->withSetting('false');
        $medicationMapper = $this->createMock(MedicationMapper::class);
        $entryMedicationMapper = $this->createMock(EntryMedicationMapper::class);
        $medicationMapper->expects($this->never())->method('deleteUnusedMedications');

        (new MedicationService($medicationMapper, $entryMedicationMapper, $this->config))
            ->syncMedicationsForEntry('u1', 5, []);
    }
}
