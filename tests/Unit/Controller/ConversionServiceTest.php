<?php

namespace OCA\NextDiary\Tests\Unit\Controller;

use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Service\ConversionService;
use OCA\NextDiary\Service\FileService;
use OCA\NextDiary\Service\MedicationService;
use OCA\NextDiary\Service\MoodService;
use OCA\NextDiary\Service\TagService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConversionServiceTest extends TestCase
{
    /** @var ConversionService */
    private $conversionService;
    /** @var IL10N|MockObject */
    private $l10n;
    /** @var TagService|MockObject */
    private $tagService;
    /** @var MoodService|MockObject */
    private $moodService;
    /** @var MedicationService|MockObject */
    private $medicationService;
    /** @var FileService|MockObject */
    private $fileService;

    public function setUp(): void
    {
        parent::setUp();

        $this->l10n = $this->createMock(IL10N::class);
        // buildMetadataLines() runs every label through $this->l->t(); echo the argument back.
        $this->l10n->method('t')->willReturnArgument(0);
        $this->tagService = $this->createMock(TagService::class);
        $this->moodService = $this->createMock(MoodService::class);
        $this->medicationService = $this->createMock(MedicationService::class);
        $this->fileService = $this->createMock(FileService::class);

        $this->conversionService = new ConversionService(
            $this->l10n,
            $this->tagService,
            $this->moodService,
            $this->medicationService,
            $this->fileService
        );
    }

    public function testEntryToMarkdownWithoutMetadata()
    {
        $this->stubEmptyMetadata();
        $entry = $this->createEntry('2022-04-24', 'This is _content_.');

        $result = $this->conversionService->entryToMarkdown($entry);

        $expected = "# 2022-04-24\r\n\r\nThis is _content_.\r\n\r\n---\r\n\r\n";
        $this->assertEquals($expected, $result);
    }

    public function testEntryToMarkdownWithMetadata()
    {
        $entry = $this->createEntry('2022-04-24', 'Diary body text.');
        $metadata = [
            'ratings' => ['mood' => 4, 'wellbeing' => 3],
            'tags' => [['id' => 1, 'name' => 'work']],
            'symptoms' => [['id' => 2, 'name' => 'headache']],
            'medications' => [['id' => 3, 'name' => 'aspirin']],
            'files' => [],
        ];

        $result = $this->conversionService->entryToMarkdown($entry, $metadata);

        $this->assertStringContainsString('# 2022-04-24', $result);
        $this->assertStringContainsString('**Mood:** 4/5', $result);
        $this->assertStringContainsString('**Wellbeing:** 3/5', $result);
        $this->assertStringContainsString('**Tags:** work', $result);
        $this->assertStringContainsString('**Symptoms:** headache', $result);
        $this->assertStringContainsString('**Medications:** aspirin', $result);
        $this->assertStringContainsString('Diary body text.', $result);
        $this->assertStringContainsString('---', $result);
    }

    public function testCollectMetadata()
    {
        $entry = $this->createEntry('2022-04-24', 'Body');
        $this->moodService->method('decodeRatings')->willReturn(['mood' => 5]);
        $this->tagService->method('getTagsForEntry')->willReturn([['id' => 1, 'name' => 'work']]);
        $this->moodService->method('getSymptomsForEntry')->willReturn([]);
        $this->medicationService->method('getMedicationsForEntry')->willReturn([]);
        $this->fileService->method('getFilesForEntry')->willReturn([]);

        $metadata = $this->conversionService->collectMetadata($entry);

        $this->assertSame(['mood' => 5], $metadata['ratings']);
        $this->assertSame([['id' => 1, 'name' => 'work']], $metadata['tags']);
        $this->assertArrayHasKey('symptoms', $metadata);
        $this->assertArrayHasKey('medications', $metadata);
        $this->assertArrayHasKey('files', $metadata);
    }

    public function testMarkdownToHtml()
    {
        $markdown = "# 2022-04-24\r\n\r\nThis is _content_.";
        $expected = "<h1>2022-04-24</h1>\n<p>This is <em>content</em>.</p>\n";

        $result = $this->conversionService->markdownToHTML($markdown);

        $this->assertEquals($expected, $result);
    }

    public function testEntriesToCsvEmpty()
    {
        $csv = $this->conversionService->entriesToCsv([]);

        // The export is prefixed with a UTF-8 BOM so spreadsheets detect the encoding.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('date,time,mood,wellbeing,symptom_count,medication_count,tag_count', $csv);
    }

    public function testEntriesToCsvWithEntry()
    {
        $this->moodService->method('decodeRatings')->willReturn(['mood' => 4, 'wellbeing' => 3]);
        $this->tagService->method('getTagsForEntry')->willReturn([]);
        $this->moodService->method('getSymptomsForEntry')->willReturn([]);
        $this->medicationService->method('getMedicationsForEntry')->willReturn([]);
        $this->fileService->method('getFilesForEntry')->willReturn([]);
        $entry = $this->createEntry('2022-04-24', 'Body');

        $csv = $this->conversionService->entriesToCsv([$entry]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('date,time,mood,wellbeing', $csv);
        $this->assertStringContainsString('2022-04-24', $csv);
    }

    /**
     * Make collectMetadata() resolve to an empty set of relations.
     */
    private function stubEmptyMetadata(): void
    {
        $this->moodService->method('decodeRatings')->willReturn(null);
        $this->moodService->method('getSymptomsForEntry')->willReturn([]);
        $this->tagService->method('getTagsForEntry')->willReturn([]);
        $this->medicationService->method('getMedicationsForEntry')->willReturn([]);
        $this->fileService->method('getFilesForEntry')->willReturn([]);
    }

    /**
     * Create an Entry element.
     */
    private function createEntry(string $date, string $content): Entry
    {
        $entry = new Entry();
        $entry->setId(1);
        $entry->setUid('testuser');
        $entry->setEntryDate($date);
        $entry->setEntryContent($content);

        return $entry;
    }
}
