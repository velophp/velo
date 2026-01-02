<?php

namespace App\Services\IndexStrategies;

use App\Models\Collection;
use App\Exceptions\IndexOperationException;
use Illuminate\Support\Facades\DB;

class MysqlIndexStrategy implements IndexStrategy
{
    public function createIndex(Collection $collection, string $fieldName, bool $unique = false): void
    {
        try {
            $prefix = $unique ? 'uq' : 'idx';
            $indexName = "{$prefix}_{$collection->id}_{$fieldName}";

            $sql = "
                ALTER TABLE records
                ADD COLUMN IF NOT EXISTS `{$fieldName}` VARCHAR(255)
                    GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$fieldName}\"'))) STORED,
                ADD " . ($unique ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS `{$indexName}` (`{$fieldName}`);
            ";

            DB::statement($sql);

            DB::table('collection_indexes')->updateOrInsert(
                ['collection_id' => $collection->id, 'field_name' => $fieldName],
                ['index_name' => $indexName, 'unique' => $unique]
            );
        } catch (\Exception $e) {
            throw new IndexOperationException("Failed to create MySQL index for field '{$fieldName}': " . $e->getMessage(), 0, $e);
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
                
                // Drop the index
                $sql = "ALTER TABLE records DROP INDEX `{$indexName}`";
                
                try {
                    DB::statement($sql);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }

                // Remove from tracking table
                DB::table('collection_indexes')
                    ->where('collection_id', $collection->id)
                    ->where('field_name', $fieldName)
                    ->delete();
            }
        } catch (\Exception $e) {
            throw new IndexOperationException("Failed to drop MySQL index for field '{$fieldName}': " . $e->getMessage(), 0, $e);
        }
    }
}
