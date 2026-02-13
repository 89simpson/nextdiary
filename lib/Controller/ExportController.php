<?php

namespace OCA\NextDiary\Controller;

use OCA\NextDiary\Db\EntryMapper;
use OCA\NextDiary\Service\ConversionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;

/**
 * Download diary entries in multiple formats.
 */
class ExportController extends Controller
{
    private $userId;
    private EntryMapper $mapper;
    private ConversionService $exportService;

    public function __construct($AppName, IRequest $request, $UserId, EntryMapper $mapper, ConversionService $exportService)
    {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->mapper = $mapper;
        $this->exportService = $exportService;
    }

    /**
     * Resolve entries based on scope parameters.
     *
     * @return array ['entries' => Entry[], 'filename' => string]
     */
    private function resolveEntries(string $scope, ?int $entryId, ?string $date, ?string $startDate, ?string $endDate): array
    {
        switch ($scope) {
            case 'single':
                if ($entryId === null) {
                    throw new \InvalidArgumentException('entryId is required for single scope');
                }
                $entry = $this->mapper->findById($entryId);
                if ($entry->getUid() !== $this->userId) {
                    throw new \InvalidArgumentException('Entry does not belong to user');
                }
                return [
                    'entries' => [$entry],
                    'filename' => 'nextdiary_' . $entry->getEntryDate(),
                ];

            case 'day':
                if ($date === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new \InvalidArgumentException('Valid date (YYYY-MM-DD) is required for day scope');
                }
                return [
                    'entries' => $this->mapper->findByDate($this->userId, $date),
                    'filename' => 'nextdiary_' . $date,
                ];

            case 'range':
                if ($startDate === null || $endDate === null
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                    throw new \InvalidArgumentException('Valid startDate and endDate (YYYY-MM-DD) are required for range scope');
                }
                if ($startDate > $endDate) {
                    throw new \InvalidArgumentException('startDate must be <= endDate');
                }
                return [
                    'entries' => $this->mapper->findByDateRange($this->userId, $startDate, $endDate),
                    'filename' => 'nextdiary_' . $startDate . '_to_' . $endDate,
                ];

            case 'all':
            default:
                return [
                    'entries' => $this->mapper->findAll($this->userId),
                    'filename' => 'nextdiary',
                ];
        }
    }

    /**
     * Get entries as one markdown file.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @throws Exception
     */
    public function getMarkdown(string $scope = 'all', ?int $entryId = null, ?string $date = null, ?string $startDate = null, ?string $endDate = null): DataDownloadResponse
    {
        try {
            $resolved = $this->resolveEntries($scope, $entryId, $date, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            return new DataDownloadResponse($e->getMessage(), 'error.txt', 'text/plain');
        }

        $markdownString = $this->exportService->entriesToMarkdown($resolved['entries']);

        return new DataDownloadResponse($markdownString, $resolved['filename'] . '.md', 'text/plain');
    }

    /**
     * Get entries as one PDF file.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @throws Exception
     */
    public function getPdf(string $scope = 'all', ?int $entryId = null, ?string $date = null, ?string $startDate = null, ?string $endDate = null): DataDownloadResponse
    {
        try {
            $resolved = $this->resolveEntries($scope, $entryId, $date, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            return new DataDownloadResponse($e->getMessage(), 'error.txt', 'text/plain');
        }

        if (empty($resolved['entries'])) {
            return new DataDownloadResponse('', $resolved['filename'] . '.pdf', 'application/pdf');
        }

        $pdfString = $this->exportService->entriesToPdf($resolved['entries']);

        return new DataDownloadResponse($pdfString, $resolved['filename'] . '.pdf', 'application/pdf');
    }
}
