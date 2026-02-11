<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\Entity;

class EntryMedication extends Entity
{
    protected $entryId;
    protected $medicationId;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('entryId', 'integer');
        $this->addType('medicationId', 'integer');
    }
}
