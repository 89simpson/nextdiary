<?php

namespace OCA\NextDiary\Service;

use Dompdf\Dompdf;
use iio\libmergepdf\Merger;
use League\CommonMark\CommonMarkConverter;
use OCA\NextDiary\Db\Entry;
use OCP\IL10N;

/**
 * Convert entries into multiple formats.
 */
class ConversionService
{
    private IL10N $l;
    private TagService $tagService;
    private MoodService $moodService;
    private MedicationService $medicationService;
    private FileService $fileService;

    public function __construct(
        IL10N $l,
        TagService $tagService,
        MoodService $moodService,
        MedicationService $medicationService,
        FileService $fileService
    ) {
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
     * Convert an array of entries into one PDF encoded as string.
     *
     * @param array|Entry[] $entries
     */
    public function entriesToPdf(array $entries): string
    {
        $pdfMerger = new Merger();
        foreach ($entries as $entry) {
            $metadata = $this->collectMetadata($entry);
            $pdfMerger->addRaw($this->entryToPDF($entry, $metadata));
        }

        return $pdfMerger->merge();
    }

    /**
     * Convert one entry into a PDF encoded as a string.
     */
    public function entryToPDF(Entry $entry, ?array $metadata = null): string
    {
        if ($metadata === null) {
            $metadata = $this->collectMetadata($entry);
        }

        $html = $this->entryToHTML($entry, $metadata);

        return $this->htmlToPDF($html);
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
        fputcsv($stream, $header);

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
            fputcsv($stream, $line);
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

        // Content: markdown to HTML (escape raw HTML for safety)
        $content = $serializedEntry['entryContent'] ?? '';
        if (!empty(trim($content))) {
            $converter = new CommonMarkConverter([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $html .= $converter->convertToHtml($content);
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
     * Convert markdown into HTML.
     */
    public function markdownToHTML(string $markdown): string
    {
        $converter = new CommonMarkConverter();

        return $converter->convertToHtml($markdown);
    }

    /**
     * Convert HTML into a PDF encoded as a string.
     */
    public function htmlToPDF(string $html): ?string
    {
        $pdf = new Dompdf();
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

        $pdf->loadHtml($styledHtml);
        $pdf->render();

        return $pdf->output();
    }
}
