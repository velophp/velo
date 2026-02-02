<?php

namespace App\Domain\Collection\Services\IndexStrategies;

use App\Domain\Collection\Contracts\IndexStrategy;
use App\Domain\Collection\Exceptions\IndexOperationException;
use App\Domain\Collection\Models\Collection;
use App\Support\Helper;
use Illuminate\Support\Facades\DB;

class PostgresIndexStrategy implements IndexStrategy
{
    public function createIndex(Collection $collection, string $fieldName, bool $unique = false): void
    {
        try {
            $indexName = Helper::generateIndexName($collection, $fieldName, $unique);

            $sql = '
                CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS {$indexName}
                ON records ((data->>'{$fieldName}'));
            ";

            DB::statement($sql);

            DB::table('collection_indexes')->updateOrInsert(
                ['collection_id' => $collection->id, 'field_name' => $fieldName],
                ['index_name' => $indexName, 'unique' => $unique],
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

    public function hasIndex(string $indexName): bool
    {
        throw new \Exception('Not implemented.');
    }
}
