<?php

namespace OCA\NextDiary\Controller;

use OCA\NextDiary\Db\EntryMapper;
use OCA\NextDiary\Service\ConversionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
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
     * A single entry that does not exist or belongs to another user is answered
     * like PageController::getEntryById does: 404 / 403.
     *
     * @return array|DataResponse ['entries' => Entry[], 'filename' => string], or the error response
     * @throws \InvalidArgumentException on missing or malformed parameters
     */
    private function resolveEntries(string $scope, ?int $entryId, ?string $date, ?string $startDate, ?string $endDate)
    {
        switch ($scope) {
            case 'single':
                if ($entryId === null) {
                    throw new \InvalidArgumentException('entryId is required for single scope');
                }
                try {
                    $entry = $this->mapper->findById($entryId);
                } catch (DoesNotExistException $e) {
                    return new DataResponse(['error' => 'Entry not found'], Http::STATUS_NOT_FOUND);
                }
                if ($entry->getUid() !== $this->userId) {
                    return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
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
    public function getMarkdown(string $scope = 'all', ?int $entryId = null, ?string $date = null, ?string $startDate = null, ?string $endDate = null): Response
    {
        try {
            $resolved = $this->resolveEntries($scope, $entryId, $date, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            return new DataDownloadResponse($e->getMessage(), 'error.txt', 'text/plain');
        }
        if ($resolved instanceof Response) {
            return $resolved;
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
    public function getPdf(string $scope = 'all', ?int $entryId = null, ?string $date = null, ?string $startDate = null, ?string $endDate = null): Response
    {
        try {
            $resolved = $this->resolveEntries($scope, $entryId, $date, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            return new DataDownloadResponse($e->getMessage(), 'error.txt', 'text/plain');
        }
        if ($resolved instanceof Response) {
            return $resolved;
        }

        if (empty($resolved['entries'])) {
            return new DataDownloadResponse('', $resolved['filename'] . '.pdf', 'application/pdf');
        }

        $pdfString = $this->exportService->entriesToPdf($resolved['entries']);

        return new DataDownloadResponse($pdfString, $resolved['filename'] . '.pdf', 'application/pdf');
    }

    /**
     * Get entries as one CSV file in analytical (wide, one-hot) format.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @throws Exception
     */
    public function getCsv(string $scope = 'all', ?int $entryId = null, ?string $date = null, ?string $startDate = null, ?string $endDate = null): Response
    {
        try {
            $resolved = $this->resolveEntries($scope, $entryId, $date, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            return new DataDownloadResponse($e->getMessage(), 'error.txt', 'text/plain');
        }
        if ($resolved instanceof Response) {
            return $resolved;
        }

        $csvString = $this->exportService->entriesToCsv($resolved['entries']);

        return new DataDownloadResponse($csvString, 'nextdiary-export.csv', 'text/csv; charset=UTF-8');
    }
}
