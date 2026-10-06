<?php

namespace OCA\NextDiary\Service\Pdf;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PageBoundaries;

/**
 * Concatenates PDF documents page by page without re-rendering them.
 *
 * Every page of an appended document is imported as a form XObject and drawn unscaled onto a
 * new page of the same size, together with its external links, so the merged pages look exactly
 * like the source pages. The producer of the first appended document is taken over; creation
 * and modification date are the time of the merge (as in a freshly rendered document).
 */
class PdfMerger extends Fpdi
{
    public function __construct()
    {
        parent::__construct('P', 'pt');
        $this->SetAutoPageBreak(false);
    }

    /**
     * Append all pages of a PDF document given as string.
     *
     * @return int Number of pages appended
     */
    public function appendDocument(string $pdf): int
    {
        $isFirstDocument = $this->page === 0;
        $pageCount = $this->setSourceFile(StreamReader::createByString($pdf));
        if ($isFirstDocument) {
            $this->takeOverProducer();
        }

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pageId = $this->importPage($pageNumber, PageBoundaries::MEDIA_BOX, true, true);
            $size = $this->getTemplateSize($pageId);
            $this->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $this->useImportedPage($pageId);
        }

        return $pageCount;
    }

    /**
     * Finish the merged document and return it as string.
     */
    public function getMergedPdf(): string
    {
        return $this->Output('S');
    }

    /**
     * Copy the Producer entry of the current source document into the merged document.
     * Optional cosmetics: on any parsing problem the FPDF default stays in place.
     */
    private function takeOverProducer(): void
    {
        try {
            $parser = $this->getPdfReader($this->currentReaderId)->getParser();
            $trailer = $parser->getCrossReference()->getTrailer();
            $info = PdfType::resolve(PdfDictionary::get($trailer, 'Info'), $parser);
            if (!$info instanceof PdfDictionary) {
                return;
            }
            $producer = PdfType::resolve(PdfDictionary::get($info, 'Producer'), $parser);
            if (!$producer instanceof PdfString) {
                return;
            }
            $value = PdfString::unescape($producer->value);
            // Text strings are either UTF-16BE with byte order mark or PDFDocEncoding.
            if (strncmp($value, "\xFE\xFF", 2) === 0) {
                $value = mb_convert_encoding(substr($value, 2), 'UTF-8', 'UTF-16BE');
            }
            if ($value !== '' && mb_check_encoding($value, 'UTF-8')) {
                $this->metadata['Producer'] = $value;
            }
        } catch (\Exception $e) {
            // keep the default producer
        }
    }

    /**
     * Write the document information dictionary, with ModDate equal to CreationDate.
     */
    protected function _putinfo()
    {
        $date = @date('YmdHisO', $this->CreationDate);
        $this->metadata['ModDate'] = 'D:' . substr($date, 0, -2) . "'" . substr($date, -2) . "'";
        parent::_putinfo();
    }
}
