<?php

namespace OCA\NextDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use ReturnTypeWillChange;

class Tag extends Entity implements JsonSerializable
{
    protected $uid;
    protected $tagName;
    protected $createdAt;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('uid', 'string');
        $this->addType('tagName', 'string');
        $this->addType('createdAt', 'datetime');
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->tagName,
            'createdAt' => $this->createdAt ? $this->createdAt->format('c') : null,
        ];
    }
}
