<?php

namespace OCA\NextDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use ReturnTypeWillChange;

class Symptom extends Entity implements JsonSerializable
{
    protected $uid;
    protected $symptomName;
    protected $category;
    protected $createdAt;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('uid', 'string');
        $this->addType('symptomName', 'string');
        $this->addType('category', 'string');
        $this->addType('createdAt', 'datetime');
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->symptomName,
            'category' => $this->category,
        ];
    }
}
