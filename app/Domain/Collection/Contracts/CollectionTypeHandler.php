<?php

namespace App\Domain\Collection\Contracts;

use App\Domain\Record\Models\Record;

interface CollectionTypeHandler
{
    public function beforeSave(Record &$record): void;

    public function beforeDelete(Record &$record): void;

    public function onRetrieved(Record &$record): void;
}
