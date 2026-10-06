<?php

namespace OCA\NextDiary\Tests\Unit\Controller;

use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Db\EntryFile;
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
    /** A real 1x1 PNG: if it reached dompdf it would be embedded as an image XObject. */
    private const PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

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

    /**
     * The markdown export must stay byte-identical: the raw entry text is passed through
     * unchanged (no markdown rendering), metadata lines and separators use CRLF.
     */
    public function testEntriesToMarkdownExactOutput()
    {
        $this->stubMetadataByEntryId([
            1 => [
                'ratings' => ['mood' => 5],
                'tags' => [['id' => 1, 'name' => 'здоровье']],
                'files' => [$this->createEntryFile('photo.jpg', 'NextDiary/2026-10-01/photo.jpg')],
            ],
        ]);
        $entries = [
            $this->createEntry('2026-10-01', "# Привет\n\n![img](data:image/png;base64,AAAA) <b>raw</b>", 1, '2026-10-01 09:09:00'),
            $this->createEntry('2026-10-02', '', 2),
        ];

        $result = $this->conversionService->entriesToMarkdown($entries);

        $expected = "# 2026-10-01, 09:09\r\n\r\n"
            . "- **Mood:** 5/5\r\n"
            . "- **Tags:** здоровье\r\n"
            . "- **Files:**\r\n"
            . "  - photo.jpg — NextDiary/2026-10-01/photo.jpg\r\n\r\n---\r\n\r\n"
            . "# Привет\n\n![img](data:image/png;base64,AAAA) <b>raw</b>"
            . "\r\n\r\n---\r\n\r\n"
            . "# 2026-10-02\r\n\r\n"
            . "\r\n\r\n---\r\n\r\n";
        $this->assertSame($expected, $result);
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

    public function testMarkdownToHtmlEscapesRawHtmlAndDropsUnsafeLinks()
    {
        $markdown = "Hi <script>alert(1)</script> <b onclick=\"x()\">bold</b>\n\n"
            . '[click](javascript:alert(1)) [vb](vbscript:msgbox) [file](file:///etc/passwd) '
            . '[ok](https://example.com)';

        $result = $this->conversionService->markdownToHTML($markdown);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('<b ', $result);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('vbscript:', $result);
        $this->assertStringNotContainsString('file:', $result);
        $this->assertStringContainsString('<a href="https://example.com">ok</a>', $result);
    }

    /**
     * Images must never reach the PDF renderer (dompdf CVE-2026-59942: a data: URI with a huge
     * bitmap exhausts the server). They are rendered as their escaped alt text instead.
     */
    public function testMarkdownToHtmlRendersImagesAsAltText()
    {
        $markdown = '![tiny *pic*](data:image/png;base64,' . self::PIXEL_PNG . ') '
            . '![remote](https://example.com/a.png) '
            . '![local](/etc/passwd) '
            . '![<x>](data:image/gif;base64,R0lGODlh)';

        $result = $this->conversionService->markdownToHTML($markdown);

        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringNotContainsString('data:', $result);
        $this->assertStringNotContainsString('example.com', $result);
        $this->assertStringNotContainsString('/etc/passwd', $result);
        $this->assertSame("<p>tiny <em>pic</em> remote local &lt;x&gt;</p>\n", $result);
    }

    public function testEntriesToPdfRendersOnePagePerEntry()
    {
        $this->stubMetadataByEntryId([
            1 => [
                'ratings' => ['mood' => 4, 'wellbeing' => 2],
                'tags' => [['id' => 1, 'name' => 'здоровье']],
                'symptoms' => [['id' => 2, 'name' => 'головная боль']],
                'medications' => [['id' => 3, 'name' => 'аспирин']],
                'files' => [$this->createEntryFile('photo.jpg', 'NextDiary/2026-10-01/photo.jpg')],
            ],
        ]);
        $entries = [
            $this->createEntry(
                '2026-10-01',
                "# Привет, дневник\n\nСегодня **хорошо**. #здоровье\n\n- пункт один\n- пункт два",
                1,
                '2026-10-01 09:09:00'
            ),
            $this->createEntry(
                '2026-10-02',
                'Second entry with `code` and [link](https://example.com). Ünïcödé ✓',
                2,
                '2026-10-02 09:09:00'
            ),
            $this->createEntry('2026-10-03', '', 3),
        ];

        $pdf = $this->conversionService->entriesToPdf($entries);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(3, $this->countPdfPages($pdf));
        // DejaVu Sans covers Cyrillic; dompdf embeds it as a subset.
        $this->assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]{6}\+DejaVuSans/', $pdf);
    }

    public function testEntriesToPdfDoesNotEmbedImages()
    {
        $this->stubEmptyMetadata();
        $entries = [
            $this->createEntry('2026-10-01', 'before ![pixel](data:image/png;base64,' . self::PIXEL_PNG . ') after', 1),
        ];

        $pdf = $this->conversionService->entriesToPdf($entries);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
        $this->assertStringNotContainsString('/Subtype /Image', $pdf);
    }

    /**
     * Defence in depth: even if an <img> reached dompdf, data:, remote and out-of-chroot
     * sources are refused by the renderer options.
     */
    public function testHtmlToPdfRefusesDataRemoteAndLocalImages()
    {
        $html = '<p>x</p><img src="data:image/png;base64,' . self::PIXEL_PNG . '">'
            . '<img src="/etc/hosts"><img src="https://example.com/a.png">';

        $pdf = (string)$this->conversionService->htmlToPDF($html);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString('/Subtype /Image', $pdf);
    }

    public function testEntryToPdf()
    {
        $this->stubEmptyMetadata();
        $entry = $this->createEntry('2022-04-24', 'Тест');

        $pdf = $this->conversionService->entryToPDF($entry);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
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
     * The CSV output must stay byte-identical to previous releases (BOM, separators, quoting,
     * backslash escape character, column order).
     */
    public function testEntriesToCsvExactOutput()
    {
        $this->stubMetadataByEntryId([
            1 => [
                'ratings' => ['mood' => 4, 'wellbeing' => 3],
                'tags' => [['id' => 1, 'name' => 'мигрень'], ['id' => 2, 'name' => 'back\\"slash']],
                'symptoms' => [['id' => 3, 'name' => 'head,ache']],
            ],
            2 => [
                'tags' => [['id' => 4, 'name' => 'a tag']],
                'medications' => [['id' => 5, 'name' => 'say "hi"']],
            ],
        ]);
        $entries = [
            $this->createEntry('2022-04-24', 'Body', 1, '2022-04-24 09:30:00'),
            $this->createEntry('2022-04-25', 'Body', 2),
        ];

        $csv = $this->conversionService->entriesToCsv($entries);

        $expected = "\xEF\xBB\xBF"
            . 'date,time,mood,wellbeing,symptom_count,medication_count,tag_count,'
            . '"symptom:head,ache","medication:say ""hi""","tag:a tag","tag:back\\"slash",tag:мигрень' . "\n"
            . '2022-04-24,09:30,4,3,1,0,2,1,0,0,1,1' . "\n"
            . '2022-04-25,,,,0,1,1,0,1,1,0,0' . "\n";
        $this->assertSame($expected, $csv);
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

    /** @var array<int, array> ratings per entry id, stored on the entry by createEntry() */
    private $ratingsById = [];

    /**
     * Make collectMetadata() return per-entry relations, looked up by entry id.
     * Must be called before createEntry() so the ratings end up on the entries.
     *
     * @param array<int, array> $metadataById
     */
    private function stubMetadataByEntryId(array $metadataById): void
    {
        $lookup = function (string $key) use ($metadataById) {
            return function (int $entryId) use ($metadataById, $key) {
                return $metadataById[$entryId][$key] ?? [];
            };
        };
        $this->moodService->method('decodeRatings')->willReturnCallback(function (?string $json) {
            return $json ? json_decode($json, true) : null;
        });
        $this->tagService->method('getTagsForEntry')->willReturnCallback($lookup('tags'));
        $this->moodService->method('getSymptomsForEntry')->willReturnCallback($lookup('symptoms'));
        $this->medicationService->method('getMedicationsForEntry')->willReturnCallback($lookup('medications'));
        $this->fileService->method('getFilesForEntry')->willReturnCallback($lookup('files'));
        foreach ($metadataById as $entryId => $metadata) {
            if (isset($metadata['ratings'])) {
                $this->ratingsById[$entryId] = $metadata['ratings'];
            }
        }
    }

    /**
     * Create an Entry element.
     */
    private function createEntry(string $date, string $content, int $id = 1, ?string $createdAt = null): Entry
    {
        $entry = new Entry();
        $entry->setId($id);
        $entry->setUid('testuser');
        $entry->setEntryDate($date);
        $entry->setEntryContent($content);
        if (isset($this->ratingsById[$id])) {
            $entry->setEntryRatings(json_encode($this->ratingsById[$id]));
        }
        if ($createdAt !== null) {
            $entry->setCreatedAt(new \DateTime($createdAt));
        }

        return $entry;
    }

    private function createEntryFile(string $originalName, string $filePath): EntryFile
    {
        $file = new EntryFile();
        $file->setOriginalName($originalName);
        $file->setFilePath($filePath);

        return $file;
    }

    /**
     * Number of pages according to the PDF page tree (dompdf writes it uncompressed),
     * cross-checked against the number of page objects.
     */
    private function countPdfPages(string $pdf): int
    {
        $this->assertSame(
            1,
            preg_match('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $pdf, $matches),
            'PDF page tree not found'
        );
        $pageObjects = preg_match_all('/\/Type\s*\/Page\b(?!s)/', $pdf);
        $this->assertSame((int)$matches[1], $pageObjects, 'Page tree count does not match page objects');

        return (int)$matches[1];
    }
}
