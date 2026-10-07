<?php

namespace OCA\NextDiary\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\MarkdownConverter;
use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Service\Markdown\ImageAltTextRenderer;
use OCA\NextDiary\Service\Pdf\PdfMerger;
use OCP\IL10N;

/**
 * Convert entries into multiple formats.
 */
class ConversionService
{
    /**
     * Maximum number of entries rendered in one dompdf pass.
     *
     * dompdf keeps the layout of every page of a document in memory until the document is
     * finished, so a single pass over a whole diary grows by roughly 0.4-0.6 MB per entry and
     * runs out of memory at several hundred entries. Larger exports are therefore rendered in
     * chunks that are merged afterwards, which keeps peak memory almost flat (about 44 MB for
     * 200 and 54 MB for 800 short entries, against 112 MB for 200 in one pass). 25 balances
     * memory against file size: every chunk embeds its own font subsets.
     */
    public const PDF_CHUNK_MAX_ENTRIES = 25;

    /**
     * Maximum amount of entry text (in characters) rendered in one dompdf pass, so a chunk of
     * unusually long entries (several pages each) stays bounded too. Roughly ten pages of text.
     * A single entry always gets a chunk of its own, however long it is.
     */
    public const PDF_CHUNK_MAX_CHARACTERS = 30000;

    private IL10N $l;
    private TagService $tagService;
    private MoodService $moodService;
    private MedicationService $medicationService;
    private FileService $fileService;
    private int $pdfChunkMaxEntries = self::PDF_CHUNK_MAX_ENTRIES;
    private int $pdfChunkMaxCharacters = self::PDF_CHUNK_MAX_CHARACTERS;
    private ?MarkdownConverter $markdownConverter = null;

    public function __construct(
        IL10N $l,
        TagService $tagService,
        MoodService $moodService,
        MedicationService $medicationService,
        FileService $fileService
    ) {
        // The bundled libraries (dompdf, CommonMark, FPDI, ...) are registered lazily, only when the
        // export code is actually instantiated. Registering Composer's autoloader on every request
        // (it prepends itself) would let our copies shadow the same libraries of other apps (issue #7).
        require_once __DIR__ . '/../../vendor/autoload.php';

        $this->l = $l;
        $this->tagService = $tagService;
        $this->moodService = $moodService;
        $this->medicationService = $medicationService;
        $this->fileService = $fileService;
    }

    /**
     * Collect all metadata for an entry.
     */
    public function collectMetadata(Entry $entry): array
    {
        $entryId = $entry->getId();
        $ratings = $this->moodService->decodeRatings($entry->getEntryRatings());
        $tags = $this->tagService->getTagsForEntry($entryId);
        $symptoms = $this->moodService->getSymptomsForEntry($entryId);
        $medications = $this->medicationService->getMedicationsForEntry($entryId);
        $files = $this->fileService->getFilesForEntry($entryId);

        return [
            'ratings' => $ratings,
            'tags' => $tags,
            'symptoms' => $symptoms,
            'medications' => $medications,
            'files' => $files,
        ];
    }

    /**
     * Override the PDF chunk limits (PDF_CHUNK_MAX_*), e.g. to exercise the merge path in tests.
     */
    public function setPdfChunkLimits(int $maxEntries, int $maxCharacters = self::PDF_CHUNK_MAX_CHARACTERS): void
    {
        if ($maxEntries < 1 || $maxCharacters < 1) {
            throw new \InvalidArgumentException('PDF chunk limits must be positive');
        }
        $this->pdfChunkMaxEntries = $maxEntries;
        $this->pdfChunkMaxCharacters = $maxCharacters;
    }

    /**
     * Convert an array of entries into one PDF encoded as string.
     *
     * Every entry starts on a new page. Small exports are rendered in a single dompdf pass.
     * Larger ones are rendered chunk by chunk (see PDF_CHUNK_MAX_*): each chunk's dompdf
     * document is released before the next one is rendered, and the finished chunk pages are
     * merged unchanged (see PdfMerger). Chunks always end at an entry boundary, which is a page
     * boundary anyway, so the result looks exactly like a single pass.
     *
     * @param array|Entry[] $entries
     */
    public function entriesToPdf(array $entries): string
    {
        $chunks = $this->splitIntoPdfChunks(array_values($entries));
        if (count($chunks) <= 1) {
            return $this->renderEntriesToPdf($chunks[0] ?? []);
        }

        $merger = new PdfMerger();
        foreach ($chunks as $chunk) {
            $chunkPdf = $this->renderEntriesToPdf($chunk);
            // The dompdf document tree contains reference cycles: collect it now, so at most
            // one chunk's layout is ever held in memory (the merger keeps only finished pages).
            gc_collect_cycles();
            $merger->appendDocument($chunkPdf);
            unset($chunkPdf);
        }

        return $merger->getMergedPdf();
    }

    /**
     * Group entries into chunks that respect both chunk limits (entry count and text size).
     *
     * @param Entry[] $entries
     * @return Entry[][]
     */
    private function splitIntoPdfChunks(array $entries): array
    {
        $chunks = [];
        $chunk = [];
        $characters = 0;
        foreach ($entries as $entry) {
            $length = mb_strlen((string)$entry->getEntryContent());
            if (!empty($chunk)
                && (count($chunk) >= $this->pdfChunkMaxEntries || $characters + $length > $this->pdfChunkMaxCharacters)) {
                $chunks[] = $chunk;
                $chunk = [];
                $characters = 0;
            }
            $chunk[] = $entry;
            $characters += $length;
        }
        if (!empty($chunk)) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * Render entries into one PDF in a single dompdf pass, one entry per page (at least).
     *
     * @param Entry[] $entries
     */
    private function renderEntriesToPdf(array $entries): string
    {
        $sections = [];
        foreach ($entries as $entry) {
            $metadata = $this->collectMetadata($entry);
            $class = empty($sections) ? 'entry' : 'entry entry-page-break';
            $sections[] = '<div class="' . $class . '">' . $this->entryToHTML($entry, $metadata) . '</div>';
        }

        return (string)$this->htmlToPDF(implode("\n", $sections));
    }

    /**
     * Convert one entry into a PDF encoded as a string.
     */
    public function entryToPDF(Entry $entry, ?array $metadata = null): string
    {
        if ($metadata === null) {
            $metadata = $this->collectMetadata($entry);
        }

        $html = '<div class="entry">' . $this->entryToHTML($entry, $metadata) . '</div>';

        return (string)$this->htmlToPDF($html);
    }

    /**
     * Convert an array of entries into one markdown file.
     */
    public function entriesToMarkdown(array $entries): string
    {
        $markdownString = '';
        foreach ($entries as $entry) {
            $metadata = $this->collectMetadata($entry);
            $markdownString .= $this->entryToMarkdown($entry, $metadata);
        }

        return $markdownString;
    }

    /**
     * Convert one entry into markdown with metadata.
     */
    public function entryToMarkdown(Entry $entry, ?array $metadata = null): string
    {
        if ($metadata === null) {
            $metadata = $this->collectMetadata($entry);
        }

        $serializedEntry = $entry->jsonSerialize();
        $date = $serializedEntry['entryDate'];
        $time = '';
        if (!empty($serializedEntry['createdAt'])) {
            $created = $serializedEntry['createdAt'];
            if (is_string($created) && strlen($created) > 10) {
                $time = ', ' . substr($created, 11, 5);
            }
        }

        $md = '# ' . $date . $time . "\r\n\r\n";

        // Metadata block
        $metaLines = $this->buildMetadataLines($metadata);
        if (!empty($metaLines)) {
            $md .= implode("\r\n", $metaLines) . "\r\n\r\n---\r\n\r\n";
        }

        // Content
        $content = $serializedEntry['entryContent'] ?? '';
        if (!empty(trim($content))) {
            $md .= $content;
        }

        $md .= "\r\n\r\n---\r\n\r\n";

        return $md;
    }

    /**
     * Convert an array of entries into one CSV file in analytical (wide, one-hot) format.
     *
     * Note text is not included. One row per entry; one column per unique symptom,
     * medication and tag across all exported entries (union, sorted alphabetically).
     *
     * @param array|Entry[] $entries
     */
    public function entriesToCsv(array $entries): string
    {
        $rows = [];
        $symptomUnion = [];
        $medicationUnion = [];
        $tagUnion = [];

        foreach ($entries as $entry) {
            $metadata = $this->collectMetadata($entry);
            $serializedEntry = $entry->jsonSerialize();

            $date = $serializedEntry['entryDate'];
            $time = '';
            if (!empty($serializedEntry['createdAt'])) {
                $created = $serializedEntry['createdAt'];
                if (is_string($created) && strlen($created) > 10) {
                    $time = substr($created, 11, 5);
                }
            }

            $ratings = $metadata['ratings'] ?? null;
            $mood = $ratings['mood'] ?? '';
            $wellbeing = $ratings['wellbeing'] ?? '';

            $symptomNames = array_map(fn($s) => $s['name'], $metadata['symptoms'] ?? []);
            $medicationNames = array_map(fn($m) => $m['name'], $metadata['medications'] ?? []);
            $tagNames = array_map(fn($t) => $t['name'], $metadata['tags'] ?? []);

            foreach ($symptomNames as $name) {
                $symptomUnion[$name] = true;
            }
            foreach ($medicationNames as $name) {
                $medicationUnion[$name] = true;
            }
            foreach ($tagNames as $name) {
                $tagUnion[$name] = true;
            }

            $rows[] = [
                'date' => $date,
                'time' => $time,
                'mood' => $mood,
                'wellbeing' => $wellbeing,
                'symptom_count' => count($symptomNames),
                'medication_count' => count($medicationNames),
                'tag_count' => count($tagNames),
                'symptoms' => array_fill_keys($symptomNames, true),
                'medications' => array_fill_keys($medicationNames, true),
                'tags' => array_fill_keys($tagNames, true),
            ];
        }

        $symptomList = array_keys($symptomUnion);
        $medicationList = array_keys($medicationUnion);
        $tagList = array_keys($tagUnion);
        sort($symptomList, SORT_STRING);
        sort($medicationList, SORT_STRING);
        sort($tagList, SORT_STRING);

        $header = ['date', 'time', 'mood', 'wellbeing', 'symptom_count', 'medication_count', 'tag_count'];
        foreach ($symptomList as $name) {
            $header[] = 'symptom:' . $name;
        }
        foreach ($medicationList as $name) {
            $header[] = 'medication:' . $name;
        }
        foreach ($tagList as $name) {
            $header[] = 'tag:' . $name;
        }

        $stream = fopen('php://temp', 'r+');
        // Separator, enclosure and escape are passed explicitly (PHP 8.4 deprecates relying on
        // the default escape character); the values equal the previous defaults, so the output
        // is unchanged.
        fputcsv($stream, $header, ',', '"', '\\');

        foreach ($rows as $row) {
            $line = [
                $row['date'],
                $row['time'],
                $row['mood'],
                $row['wellbeing'],
                $row['symptom_count'],
                $row['medication_count'],
                $row['tag_count'],
            ];
            foreach ($symptomList as $name) {
                $line[] = isset($row['symptoms'][$name]) ? 1 : 0;
            }
            foreach ($medicationList as $name) {
                $line[] = isset($row['medications'][$name]) ? 1 : 0;
            }
            foreach ($tagList as $name) {
                $line[] = isset($row['tags'][$name]) ? 1 : 0;
            }
            fputcsv($stream, $line, ',', '"', '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * Build metadata lines for markdown.
     */
    private function buildMetadataLines(array $metadata): array
    {
        $lines = [];
        $ratings = $metadata['ratings'] ?? null;

        if ($ratings && isset($ratings['mood'])) {
            $lines[] = '- **' . $this->l->t('Mood') . ':** ' . $ratings['mood'] . '/5';
        }
        if ($ratings && isset($ratings['wellbeing'])) {
            $lines[] = '- **' . $this->l->t('Wellbeing') . ':** ' . $ratings['wellbeing'] . '/5';
        }

        $tags = $metadata['tags'] ?? [];
        if (!empty($tags)) {
            $names = array_map(fn($t) => $t['name'], $tags);
            $lines[] = '- **' . $this->l->t('Tags') . ':** ' . implode(', ', $names);
        }

        $symptoms = $metadata['symptoms'] ?? [];
        if (!empty($symptoms)) {
            $names = array_map(fn($s) => $s['name'], $symptoms);
            $lines[] = '- **' . $this->l->t('Symptoms') . ':** ' . implode(', ', $names);
        }

        $medications = $metadata['medications'] ?? [];
        if (!empty($medications)) {
            $names = array_map(fn($m) => $m['name'], $medications);
            $lines[] = '- **' . $this->l->t('Medications') . ':** ' . implode(', ', $names);
        }

        $files = $metadata['files'] ?? [];
        if (!empty($files)) {
            $lines[] = '- **' . $this->l->t('Files') . ':**';
            foreach ($files as $file) {
                $serialized = $file->jsonSerialize();
                $originalName = $serialized['originalName'] ?? 'file';
                $filePath = $serialized['filePath'] ?? '';
                $lines[] = '  - ' . $originalName . ' — ' . $filePath;
            }
        }

        return $lines;
    }

    /**
     * Convert one entry into HTML with metadata for PDF.
     */
    private function entryToHTML(Entry $entry, array $metadata): string
    {
        $serializedEntry = $entry->jsonSerialize();
        $date = htmlspecialchars($serializedEntry['entryDate']);
        $time = '';
        if (!empty($serializedEntry['createdAt'])) {
            $created = $serializedEntry['createdAt'];
            if (is_string($created) && strlen($created) > 10) {
                $time = ', ' . htmlspecialchars(substr($created, 11, 5));
            }
        }

        $html = '<h1>' . $date . $time . '</h1>';

        // Metadata block
        $metaHtml = $this->buildMetadataHTML($metadata);
        if (!empty($metaHtml)) {
            $html .= '<div class="entry-meta">' . $metaHtml . '</div>';
        }

        // Content: markdown to HTML (raw HTML escaped, unsafe links and images neutralised)
        $content = $serializedEntry['entryContent'] ?? '';
        if (!empty(trim($content))) {
            $html .= $this->markdownToHTML($content);
        }

        return $html;
    }

    /**
     * Build metadata HTML block for PDF.
     */
    private function buildMetadataHTML(array $metadata): string
    {
        $rows = [];
        $ratings = $metadata['ratings'] ?? null;

        if ($ratings && isset($ratings['mood'])) {
            $rows[] = '<tr><td class="meta-label">' . htmlspecialchars($this->l->t('Mood')) . ':</td><td>' . (int)$ratings['mood'] . '/5</td></tr>';
        }
        if ($ratings && isset($ratings['wellbeing'])) {
            $rows[] = '<tr><td class="meta-label">' . htmlspecialchars($this->l->t('Wellbeing')) . ':</td><td>' . (int)$ratings['wellbeing'] . '/5</td></tr>';
        }

        $tags = $metadata['tags'] ?? [];
        if (!empty($tags)) {
            $names = array_map(fn($t) => htmlspecialchars($t['name']), $tags);
            $rows[] = '<tr><td class="meta-label">' . htmlspecialchars($this->l->t('Tags')) . ':</td><td>' . implode(', ', $names) . '</td></tr>';
        }

        $symptoms = $metadata['symptoms'] ?? [];
        if (!empty($symptoms)) {
            $names = array_map(fn($s) => htmlspecialchars($s['name']), $symptoms);
            $rows[] = '<tr><td class="meta-label">' . htmlspecialchars($this->l->t('Symptoms')) . ':</td><td>' . implode(', ', $names) . '</td></tr>';
        }

        $medications = $metadata['medications'] ?? [];
        if (!empty($medications)) {
            $names = array_map(fn($m) => htmlspecialchars($m['name']), $medications);
            $rows[] = '<tr><td class="meta-label">' . htmlspecialchars($this->l->t('Medications')) . ':</td><td>' . implode(', ', $names) . '</td></tr>';
        }

        $files = $metadata['files'] ?? [];
        if (!empty($files)) {
            $fileLines = [];
            foreach ($files as $file) {
                $serialized = $file->jsonSerialize();
                $originalName = htmlspecialchars($serialized['originalName'] ?? 'file');
                $filePath = htmlspecialchars($serialized['filePath'] ?? '');
                $fileLines[] = $originalName . ' — ' . $filePath;
            }
            $rows[] = '<tr><td class="meta-label">' . htmlspecialchars($this->l->t('Files')) . ':</td><td>' . implode('<br>', $fileLines) . '</td></tr>';
        }

        if (empty($rows)) {
            return '';
        }

        return '<table>' . implode('', $rows) . '</table>';
    }

    /**
     * Convert user-provided markdown into HTML that is safe to hand to the PDF renderer.
     *
     * - raw HTML in the markdown is escaped, not passed through;
     * - links with unsafe protocols (javascript:, vbscript:, file:, data:) are dropped;
     * - images are rendered as their alt text (see ImageAltTextRenderer), so no image source
     *   (remote URL, local path or data: URI) ever reaches dompdf;
     * - nesting depth and delimiters per line are capped to bound parser work on hostile input.
     */
    public function markdownToHTML(string $markdown): string
    {
        // Built once per service instance and reused for every entry of an export.
        if ($this->markdownConverter === null) {
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 100,
                'max_delimiters_per_line' => 1000,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addRenderer(Image::class, new ImageAltTextRenderer(), 100);
            $this->markdownConverter = new MarkdownConverter($environment);
        }

        return $this->markdownConverter->convert($markdown)->getContent();
    }

    /**
     * Build the dompdf options used for every export.
     *
     * Only local files inside dompdf's own directory (its chroot, e.g. the bundled fonts) may be
     * read. Remote fetching, data: URIs, embedded PHP and PDF JavaScript are disabled.
     */
    private function createPdfOptions(): Options
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        // Keeps the default chroot check for file:// and removes data://, http:// and https://.
        $options->setAllowedProtocols(['file://']);
        $options->setDefaultFont('DejaVu Sans');

        return $options;
    }

    /**
     * Convert HTML into a PDF encoded as a string.
     */
    public function htmlToPDF(string $html): ?string
    {
        $pdf = new Dompdf($this->createPdfOptions());
        $pdf->setPaper('A4', 'portrait');

        $styledHtml = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: 12pt;
                        line-height: 1.6;
                    }
                    h1 {
                        font-size: 18pt;
                        margin-bottom: 10pt;
                    }
                    p {
                        margin-bottom: 8pt;
                    }
                    .entry-page-break {
                        page-break-before: always;
                    }
                    .entry-meta {
                        background: #f5f5f5;
                        padding: 8pt;
                        margin-bottom: 12pt;
                        font-size: 10pt;
                        border-radius: 4pt;
                    }
                    .entry-meta table {
                        width: 100%;
                        border-collapse: collapse;
                    }
                    .entry-meta td {
                        padding: 2pt 4pt;
                        vertical-align: top;
                    }
                    .entry-meta .meta-label {
                        font-weight: bold;
                        white-space: nowrap;
                        width: 1%;
                    }
                </style>
            </head>
            <body>
                ' . $html . '
            </body>
            </html>
        ';

        $pdf->loadHtml($styledHtml, 'UTF-8');
        $pdf->render();
        $output = $pdf->output();
        unset($pdf);

        return $output;
    }
}
