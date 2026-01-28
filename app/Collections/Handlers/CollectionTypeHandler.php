<?php

namespace App\Collections\Handlers;

use App\Models\Record;

interface CollectionTypeHandler
{
    public function beforeSave(Record &$record): void;

    public function beforeDelete(Record &$record): void;

    public function onRetrieved(Record &$record): void;
}
