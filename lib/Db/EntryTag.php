<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\Entity;

class EntryTag extends Entity
{
    protected $entryId;
    protected $tagId;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('entryId', 'integer');
        $this->addType('tagId', 'integer');
    }
}
