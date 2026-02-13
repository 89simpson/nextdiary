<?php

namespace OCA\NextDiary\Service;

use OCA\NextDiary\Db\EntryFile;
use OCA\NextDiary\Db\EntryFileMapper;
use OCP\DB\Exception;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

class FileService
{
    private const APP_FOLDER = 'NextDiary';
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB

    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'exe', 'bat', 'cmd', 'com', 'sh', 'bash',
        'js', 'vbs', 'wsf', 'ps1',
        'htaccess', 'htpasswd',
    ];

    private EntryFileMapper $mapper;
    private IRootFolder $rootFolder;
    private LoggerInterface $logger;

    public function __construct(
        EntryFileMapper $mapper,
        IRootFolder $rootFolder,
        LoggerInterface $logger
    ) {
        $this->mapper = $mapper;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    /**
     * Get or create folder: /NextDiary/{entryDate}/
     */
    private function getDateFolder(string $uid, string $entryDate): Folder
    {
        $userFolder = $this->rootFolder->getUserFolder($uid);

        try {
            $appFolder = $userFolder->get(self::APP_FOLDER);
        } catch (NotFoundException $e) {
            $appFolder = $userFolder->newFolder(self::APP_FOLDER);
        }

        try {
            $dateFolder = $appFolder->get($entryDate);
        } catch (NotFoundException $e) {
            $dateFolder = $appFolder->newFolder($entryDate);
        }

        return $dateFolder;
    }

    /**
     * Generate a unique filename to avoid collisions.
     */
    private function uniqueFileName(Folder $folder, string $originalName): string
    {
        $name = $originalName;
        $counter = 1;

        while ($folder->nodeExists($name)) {
            $pathInfo = pathinfo($originalName);
            $base = $pathInfo['filename'] ?? 'file';
            $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $name = $base . ' (' . $counter . ')' . $ext;
            $counter++;
        }

        return $name;
    }

    /**
     * Upload a file for an entry.
     *
     * @throws NotPermittedException
     * @throws Exception
     */
    public function uploadFile(string $uid, int $entryId, string $entryDate, string $originalName, string $content, string $mimeType): EntryFile
    {
        $size = strlen($content);
        if ($size > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File too large. Maximum size is 50 MB.');
        }

        // Validate file extension (block dangerous types)
        $pathInfo = pathinfo($originalName);
        $extension = strtolower($pathInfo['extension'] ?? '');
        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('File type not allowed.');
        }

        // Sanitize filename: separate name and extension to prevent double-extension attacks
        $baseName = preg_replace('/[^\w\s\-\(\)]/u', '_', $pathInfo['filename'] ?? 'file');
        if (empty($baseName)) {
            $baseName = 'file';
        }
        $safeName = !empty($extension) ? $baseName . '.' . $extension : $baseName;

        // Ensure no path traversal
        if (str_contains($safeName, '..')) {
            throw new \InvalidArgumentException('Invalid file name.');
        }

        $dateFolder = $this->getDateFolder($uid, $entryDate);
        $fileName = $this->uniqueFileName($dateFolder, $safeName);
        $file = $dateFolder->newFile($fileName);
        $file->putContent($content);

        $filePath = self::APP_FOLDER . '/' . $entryDate . '/' . $fileName;

        $entryFile = new EntryFile();
        $entryFile->setEntryId($entryId);
        $entryFile->setUid($uid);
        $entryFile->setFilePath($filePath);
        $entryFile->setOriginalName($originalName);
        $entryFile->setMimeType($mimeType);
        $entryFile->setSizeBytes($size);
        $entryFile->setUploadedAt(new \DateTime());

        return $this->mapper->insert($entryFile);
    }

    /**
     * Get files for an entry.
     *
     * @return EntryFile[]
     * @throws Exception
     */
    public function getFilesForEntry(int $entryId): array
    {
        return $this->mapper->findByEntry($entryId);
    }

    /**
     * Get a single file record by ID.
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws Exception
     */
    public function getFileById(int $fileId): EntryFile
    {
        return $this->mapper->findById($fileId);
    }

    /**
     * Get file content from Nextcloud storage.
     *
     * @throws NotFoundException
     */
    public function getFileContent(string $uid, string $filePath): string
    {
        $userFolder = $this->rootFolder->getUserFolder($uid);
        $file = $userFolder->get($filePath);
        return $file->getContent();
    }

    /**
     * Delete a file from storage and database.
     *
     * @throws Exception
     */
    public function deleteFile(string $uid, int $fileId): void
    {
        $entryFile = $this->mapper->findById($fileId);

        // Delete from Nextcloud storage
        try {
            $userFolder = $this->rootFolder->getUserFolder($uid);
            $file = $userFolder->get($entryFile->getFilePath());
            $file->delete();
        } catch (NotFoundException $e) {
            $this->logger->warning('[NextDiary] File not found on disk during delete: ' . $entryFile->getFilePath());
        } catch (NotPermittedException $e) {
            $this->logger->error('[NextDiary] Permission denied deleting file: ' . $entryFile->getFilePath());
            throw $e;
        }

        $this->mapper->delete($entryFile);

        // Try to remove empty date folder
        $this->cleanupEmptyFolder($uid, $entryFile->getFilePath());
    }

    /**
     * Delete all files for an entry.
     *
     * @throws Exception
     */
    public function deleteFilesForEntry(string $uid, int $entryId): void
    {
        $files = $this->mapper->findByEntry($entryId);
        $folderPath = null;
        foreach ($files as $entryFile) {
            $folderPath = $entryFile->getFilePath();
            try {
                $userFolder = $this->rootFolder->getUserFolder($uid);
                $file = $userFolder->get($entryFile->getFilePath());
                $file->delete();
            } catch (NotFoundException | NotPermittedException $e) {
                $this->logger->warning('[NextDiary] Could not delete file: ' . $entryFile->getFilePath());
            }
        }
        $this->mapper->deleteByEntry($entryId);

        if ($folderPath !== null) {
            $this->cleanupEmptyFolder($uid, $folderPath);
        }
    }

    /**
     * Remove empty date folder after file deletion.
     */
    private function cleanupEmptyFolder(string $uid, string $filePath): void
    {
        try {
            $userFolder = $this->rootFolder->getUserFolder($uid);
            // filePath is like "NextDiary/2026-02-13/file.jpg" — get parent folder
            $folderPath = dirname($filePath);
            $folder = $userFolder->get($folderPath);
            if ($folder instanceof Folder && count($folder->getDirectoryListing()) === 0) {
                $folder->delete();
            }
        } catch (\Exception $e) {
            // Silently ignore cleanup failures
        }
    }
}
