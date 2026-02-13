<?php

namespace OCA\NextDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use ReturnTypeWillChange;

class EntryFile extends Entity implements JsonSerializable
{
    protected $entryId;
    protected $uid;
    protected $filePath;
    protected $originalName;
    protected $mimeType;
    protected $sizeBytes;
    protected $uploadedAt;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('entryId', 'integer');
        $this->addType('uid', 'string');
        $this->addType('filePath', 'string');
        $this->addType('originalName', 'string');
        $this->addType('mimeType', 'string');
        $this->addType('sizeBytes', 'integer');
        $this->addType('uploadedAt', 'datetime');
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'entryId' => $this->entryId,
            'filePath' => $this->filePath,
            'originalName' => $this->originalName,
            'mimeType' => $this->mimeType,
            'sizeBytes' => $this->sizeBytes,
            'uploadedAt' => $this->uploadedAt ? $this->uploadedAt->format('c') : null,
            'isImage' => str_starts_with($this->mimeType ?? '', 'image/'),
        ];
    }
}
