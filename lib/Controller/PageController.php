<?php

namespace OCA\NextDiary\Controller;

use OCA\NextDiary\Db\Entry;
use OCA\NextDiary\Db\EntryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\Util;
use Psr\Log\LoggerInterface;

class PageController extends Controller
{
    private $userId;
    /** @var EntryMapper */
    private $mapper;
    /** @var LoggerInterface */
    private $logger;

    public function __construct($AppName, IRequest $request, $UserId, EntryMapper $mapper, LoggerInterface $logger)
    {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->mapper = $mapper;
        $this->logger = $logger;
    }

    private function sanitizeUtf8(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse
    {
        Util::addScript($this->appName, 'nextdiary-main');
        return new TemplateResponse('nextdiary', 'index');
    }

    // ─── New API endpoints (v0.0.2) ───

    /**
     * Get all entries for a specific date.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEntriesByDate(string $date): DataResponse
    {
        try {
            $entries = $this->mapper->findByDate($this->userId, $date);
            foreach ($entries as $entry) {
                $entry->setEntryContent($this->sanitizeUtf8((string) $entry->getEntryContent()));
            }
            return new DataResponse($entries);
        } catch (\Exception $e) {
            $this->logger->error('[NextDiary] getEntriesByDate failed: ' . $e->getMessage(), [
                'exception' => $e,
                'userId' => $this->userId,
                'date' => $date,
            ]);
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single entry by ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEntryById(int $id): DataResponse
    {
        try {
            $entry = $this->mapper->findById($id);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Entry not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        if ($entry->getUid() !== $this->userId) {
            return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $entry->setEntryContent($this->sanitizeUtf8((string) $entry->getEntryContent()));
        return new DataResponse($entry);
    }

    /**
     * Create a new entry for a given date.
     *
     * @NoAdminRequired
     */
    public function createEntry(string $date, string $content = ''): DataResponse
    {
        $content = $this->sanitizeUtf8(strip_tags($content));
        $now = new \DateTime();

        $entry = new Entry();
        $entry->setUid($this->userId);
        $entry->setEntryDate($date);
        $entry->setEntryContent($content);
        $entry->setCreatedAt($now);
        $entry->setUpdatedAt($now);

        try {
            $inserted = $this->mapper->insert($entry);
            return new DataResponse($inserted, Http::STATUS_CREATED);
        } catch (Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update an existing entry by ID.
     *
     * @NoAdminRequired
     */
    public function updateEntryById(int $id, string $content): DataResponse
    {
        try {
            $entry = $this->mapper->findById($id);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Entry not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        if ($entry->getUid() !== $this->userId) {
            return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $content = $this->sanitizeUtf8(strip_tags($content));
        $entry->setEntryContent($content);
        $entry->setUpdatedAt(new \DateTime());

        try {
            $updated = $this->mapper->update($entry);
            return new DataResponse($updated);
        } catch (Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete an entry by ID.
     *
     * @NoAdminRequired
     */
    public function deleteEntry(int $id): DataResponse
    {
        try {
            $entry = $this->mapper->findById($id);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Entry not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        if ($entry->getUid() !== $this->userId) {
            return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        try {
            $this->mapper->delete($entry);
            return new DataResponse(null, Http::STATUS_NO_CONTENT);
        } catch (Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    // ─── Legacy API endpoints (backward compatible) ───

    /**
     * Get first entry for a date (legacy).
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEntry(string $date): DataResponse
    {
        try {
            $entry = $this->mapper->find($this->userId, $date);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['isEmpty' => true]);
        } catch (MultipleObjectsReturnedException|Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $entry->setEntryContent($this->sanitizeUtf8((string) $entry->getEntryContent()));
        return new DataResponse($entry);
    }

    /**
     * Get last N entries with excerpts.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getLastEntries(int $amount): DataResponse
    {
        try {
            $entries = $this->mapper->findLast($this->userId, $amount);
            $response = array_map(function ($entry) {
                $content = $this->sanitizeUtf8((string) $entry->getEntryContent());
                return [
                    'id' => $entry->getId(),
                    'date' => $entry->getEntryDate(),
                    'createdAt' => $entry->getCreatedAt() ? $entry->getCreatedAt()->format('c') : null,
                    'excerpt' => mb_substr($content, 0, 40),
                ];
            }, $entries);

            return new DataResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('[NextDiary] getLastEntries failed: ' . $e->getMessage(), [
                'exception' => $e,
                'userId' => $this->userId,
                'amount' => $amount,
            ]);
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Legacy upsert: create or update entry by date.
     *
     * @NoAdminRequired
     */
    public function updateEntry(string $date, string $content): DataResponse
    {
        if ('' === $content) {
            try {
                $entries = $this->mapper->findByDate($this->userId, $date);
                foreach ($entries as $entry) {
                    $this->mapper->delete($entry);
                }
            } catch (\Exception $e) {
                $this->logger->notice('Could not delete diary entries: ' . $e->getMessage());
            }
            return new DataResponse(['isEmpty' => true]);
        }

        $content = $this->sanitizeUtf8(strip_tags($content));
        $now = new \DateTime();

        try {
            $entry = $this->mapper->find($this->userId, $date);
            $entry->setEntryContent($content);
            $entry->setUpdatedAt($now);
            return new DataResponse($this->mapper->update($entry));
        } catch (DoesNotExistException $e) {
            $entry = new Entry();
            $entry->setUid($this->userId);
            $entry->setEntryDate($date);
            $entry->setEntryContent($content);
            $entry->setCreatedAt($now);
            $entry->setUpdatedAt($now);
            return new DataResponse($this->mapper->insert($entry));
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all dates that have diary entries.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEntryDates(): DataResponse
    {
        try {
            $dates = $this->mapper->findAllDates($this->userId);
            return new DataResponse($dates);
        } catch (Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
