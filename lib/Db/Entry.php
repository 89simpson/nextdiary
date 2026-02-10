<?php

namespace OCA\NextDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use ReturnTypeWillChange;

class Entry extends Entity implements JsonSerializable
{

    protected $uid;
    protected $entryDate;
    protected $entryContent;
    protected $createdAt;
    protected $updatedAt;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('uid', 'string');
        $this->addType('entryDate', 'string');
        $this->addType('entryContent', 'string');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'entryDate' => $this->entryDate,
            'entryContent' => $this->entryContent,
            'createdAt' => $this->createdAt ? $this->createdAt->format('c') : null,
            'updatedAt' => $this->updatedAt ? $this->updatedAt->format('c') : null,
        ];
    }
}
