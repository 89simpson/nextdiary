<?php

namespace OCA\NextDiary\Db;

use OCP\AppFramework\Db\Entity;

class EntrySymptom extends Entity
{
    protected $entryId;
    protected $symptomId;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('entryId', 'integer');
        $this->addType('symptomId', 'integer');
    }
}
