<?php

namespace App\Services\IndexStrategies;

use App\Models\Collection;
use App\Exceptions\IndexOperationException;
use Illuminate\Support\Facades\DB;

class PostgresIndexStrategy implements IndexStrategy
{
    public function createIndex(Collection $collection, string $fieldName, bool $unique = false): void
    {
        try {
            $prefix = $unique ? 'uq' : 'idx';
            $indexName = "{$prefix}_{$collection->id}_{$fieldName}";

            $sql = "
                CREATE " . ($unique ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS {$indexName}
                ON records ((data->>'{$fieldName}'));
            ";

            DB::statement($sql);

            DB::table('collection_indexes')->updateOrInsert(
                ['collection_id' => $collection->id, 'field_name' => $fieldName],
                ['index_name' => $indexName, 'unique' => $unique]
            );
        } catch (\Exception $e) {
            throw new IndexOperationException("Failed to create PostgreSQL index for field '{$fieldName}': " . $e->getMessage(), 0, $e);
        }
    }

    public function dropIndex(Collection $collection, string $fieldName): void
    {
        try {
            $indexEntry = DB::table('collection_indexes')
                ->where('collection_id', $collection->id)
                ->where('field_name', $fieldName)
                ->first();

            if ($indexEntry) {
                $indexName = $indexEntry->index_name;

                $sql = "DROP INDEX IF EXISTS {$indexName}";
                DB::statement($sql);

                DB::table('collection_indexes')
                    ->where('collection_id', $collection->id)
                    ->where('field_name', $fieldName)
                    ->delete();
            }
        } catch (\Exception $e) {
            throw new IndexOperationException("Failed to drop PostgreSQL index for field '{$fieldName}': " . $e->getMessage(), 0, $e);
        }
    }
}
