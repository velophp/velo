<?php

namespace App\Domain\Collection\Contracts;

use App\Domain\Collection\Models\Collection;

interface IndexStrategy
{
    /**
     * Create an index on the database used. This method also updates the relevant collection fields’ unique and required properties.
     */
    public function createIndex(Collection $collection, array $fieldNames, bool $unique = false): void;

    /**
     * Drop an existing index on the database. This method also updates the relevant collection fields unique and required property.
     */
    public function dropIndex(Collection $collection, array $fieldNames): void;

    /**
     * Perform a full check of both the actual generated columns and the tracking metadata in the collection_indexes table.
     *
     * @return void
     */
    public function hasIndex(Collection $collection, array $fieldNames, bool $unique = false): bool;
}
