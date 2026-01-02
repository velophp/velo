<?php

namespace App\Services\IndexStrategies;

use App\Models\Collection;

interface IndexStrategy
{
    public function createIndex(Collection $collection, string $fieldName, bool $unique = false): void;
    public function dropIndex(Collection $collection, string $fieldName): void;
}
