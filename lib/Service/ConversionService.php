<?php

namespace OCA\NextDiary\Service;

use Dompdf\Dompdf;
use iio\libmergepdf\Merger;
use League\CommonMark\CommonMarkConverter;
use OCA\NextDiary\Db\Entry;

/**
 * Convert entries into multiple formats.
 */
class ConversionService
{
    /**
     * Convert an array of entries into one PDF encoded as string.
     *
     * @param array|Entry[] $entries
     */
    public function entriesToPdf(array $entries): string
    {
        $pdfMerger = new Merger();
        /** @var Entry $entry */
        foreach ($entries as $entry) {
            $pdfMerger->addRaw($this->entryToPDF($entry));
        }

        return $pdfMerger->merge();
    }

    /**
     * Convert one entry into a PDF encoded as a string.
     */
    public function entryToPDF(Entry $entry): string
    {
        $data = $this->entryToMarkdown($entry);
        $data = $this->markdownToHTML($data);

        return $this->htmlToPDF($data);
    }

    /**
     * Convert an array of entries into one markdown file.
     */
    public function entriesToMarkdown(array $entries): string
    {
        $markdownString = '';
        /** @var Entry $entry */
        foreach ($entries as $entry) {
            $markdownString .= $this->entryToMarkdown($entry);
        }

        return $markdownString;
    }

    /**
     * Convert one entry into a markdown file.
     */
    public function entryToMarkdown(Entry $entry): string
    {
        $serializedEntry = $entry->jsonSerialize();
        $markdownString = '# '.$serializedEntry['entryDate'];
        $markdownString .= sprintf("\r\n\r\n%s", $serializedEntry['entryContent']);

        return $markdownString;
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

        // Add CSS for Unicode font support (DejaVu Sans supports Cyrillic)
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
