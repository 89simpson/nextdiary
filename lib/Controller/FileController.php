<?php

namespace OCA\NextDiary\Controller;

use OCA\NextDiary\Db\EntryMapper;
use OCA\NextDiary\Service\FileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class FileController extends Controller
{
    private $userId;
    private FileService $fileService;
    private EntryMapper $entryMapper;
    private LoggerInterface $logger;

    public function __construct(
        $AppName,
        IRequest $request,
        $UserId,
        FileService $fileService,
        EntryMapper $entryMapper,
        LoggerInterface $logger
    ) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->fileService = $fileService;
        $this->entryMapper = $entryMapper;
        $this->logger = $logger;
    }

    /**
     * Verify that the current user owns the entry.
     */
    private function verifyEntryOwnership(int $entryId): ?DataResponse
    {
        try {
            $entry = $this->entryMapper->findById($entryId);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Entry not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('[NextDiary] Entry lookup failed: ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(['error' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        if ($entry->getUid() !== $this->userId) {
            return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }
        return null;
    }

    /**
     * Upload a file for an entry.
     *
     * @NoAdminRequired
     */
    public function upload(int $entryId): DataResponse
    {
        $error = $this->verifyEntryOwnership($entryId);
        if ($error !== null) {
            return $error;
        }

        $file = $this->request->getUploadedFile('file');
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            return new DataResponse(['error' => 'No file uploaded or upload error'], Http::STATUS_BAD_REQUEST);
        }

        $originalName = $file['name'];
        $mimeType = $file['type'] ?: 'application/octet-stream';
        $content = file_get_contents($file['tmp_name']);

        if ($content === false) {
            return new DataResponse(['error' => 'Could not read uploaded file'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        try {
            $entry = $this->entryMapper->findById($entryId);
            $entryDate = $entry->getEntryDate();

            $entryFile = $this->fileService->uploadFile(
                $this->userId,
                $entryId,
                $entryDate,
                $originalName,
                $content,
                $mimeType
            );
            return new DataResponse($entryFile, Http::STATUS_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('[NextDiary] File upload failed: ' . $e->getMessage(), [
                'exception' => $e,
                'userId' => $this->userId,
                'entryId' => $entryId,
            ]);
            return new DataResponse(['error' => 'Upload failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get list of files for an entry.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function listFiles(int $entryId): DataResponse
    {
        $error = $this->verifyEntryOwnership($entryId);
        if ($error !== null) {
            return $error;
        }

        try {
            $files = $this->fileService->getFilesForEntry($entryId);
            return new DataResponse(array_map(function ($f) {
                return $f->jsonSerialize();
            }, $files));
        } catch (\Exception $e) {
            $this->logger->error('[NextDiary] List files failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return new DataResponse(['error' => 'Failed to list files'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a file.
     *
     * @NoAdminRequired
     */
    public function deleteFile(int $entryId, int $fileId): DataResponse
    {
        $error = $this->verifyEntryOwnership($entryId);
        if ($error !== null) {
            return $error;
        }

        try {
            $entryFile = $this->fileService->getFileById($fileId);
            if ($entryFile->getEntryId() !== $entryId || $entryFile->getUid() !== $this->userId) {
                return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
            }
            $this->fileService->deleteFile($this->userId, $fileId);
            return new DataResponse(null, Http::STATUS_NO_CONTENT);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('[NextDiary] File delete failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return new DataResponse(['error' => 'Delete failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download/view a file.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function download(int $fileId): DataResponse|DataDownloadResponse
    {
        try {
            $entryFile = $this->fileService->getFileById($fileId);
            if ($entryFile->getUid() !== $this->userId) {
                return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
            }
            $content = $this->fileService->getFileContent($this->userId, $entryFile->getFilePath());
            return new DataDownloadResponse(
                $content,
                $entryFile->getOriginalName(),
                $entryFile->getMimeType()
            );
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('[NextDiary] File download failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return new DataResponse(['error' => 'Download failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
