<?php

namespace App\Domain\Collection\Services\IndexStrategies;

use App\Domain\Collection\Contracts\IndexStrategy;
use App\Domain\Collection\Exceptions\IndexOperationException;
use App\Domain\Collection\Models\Collection;
use App\Support\Helper;
use Illuminate\Support\Facades\DB;

class MysqlIndexStrategy implements IndexStrategy
{
    private function executeIndexSql($collection, $fieldNames, $indexName, $unique)
    {
        $alterParts = [];
        foreach ($fieldNames as $name) {
            $vCol = Helper::generateVirtualColumnName($collection, $name);

            // Check if column exists in the physical schema
            $exists = \count(DB::select("SHOW COLUMNS FROM records LIKE '{$vCol}'")) > 0;

            if (! $exists) {
                $alterParts[] = "ADD COLUMN `{$vCol}` VARCHAR(50)
                    GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$name}\"'))) " . (config('velo.sql_generated_column_strategy') === 'STORED' ? 'STORED' : 'VIRTUAL');
            }
        }

        if (! empty($alterParts)) {
            $type = $unique ? 'UNIQUE ' : '';
            $cols = implode('`, `', array_map(fn ($n) => Helper::generateVirtualColumnName($collection, $n), $fieldNames));

            $sql = 'ALTER TABLE records ' . implode(', ', $alterParts) . ", ADD {$type} INDEX `{$indexName}` (`{$cols}`)";
            DB::statement($sql);
        }
    }

    public function createIndex(Collection $collection, array $fieldNames, bool $unique = false): void
    {
        $indexName = Helper::generateIndexName($collection, implode('_', $fieldNames), $unique);
        $virtualColNames = array_map(fn ($name) => Helper::generateVirtualColumnName($collection, $name), $fieldNames);

        try {
            self::executeIndexSql($collection, $fieldNames, $indexName, $unique);

            DB::table('collection_indexes')->updateOrInsert(
                ['collection_id' => $collection->id, 'index_name' => $indexName],
                [
                    'field_names' => json_encode($fieldNames),
                    'created_at'  => now(),
                ],
            );

            $collection->fields()->whereIn('name', $fieldNames)->update([
                'indexed' => true,
                'unique'  => $unique,
            ]);
        } catch (\Exception $e) {
            foreach ($virtualColNames as $col) {
                try {
                    DB::statement("ALTER TABLE records DROP COLUMN IF EXISTS `{$col}`");
                } catch (\Exception $cleanupError) {
                    \Log::error('Index Cleanup Failed: ' . $cleanupError->getMessage());
                }
            }

            $fnms = implode(', ', $fieldNames);
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), '1062')) {
                throw new IndexOperationException("Cannot create unique index: Duplicate values exist in '{$fnms}' field.", 1062);
            }

            if ($e->getCode() === '42000' && str_contains($e->getMessage(), '1059')) {
                throw new IndexOperationException('The index name is too long. Try selecting fewer columns for this composite index.');
            }

            throw new IndexOperationException('Schema sync failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function dropIndex(Collection $collection, array $fieldNames): void
    {
        $indexName = Helper::generateIndexName($collection, implode('_', $fieldNames), false);
        $uniqueIndexName = Helper::generateIndexName($collection, implode('_', $fieldNames), true);

        $indexEntry = DB::table('collection_indexes')
            ->where('collection_id', $collection->id)
            ->whereIn('index_name', [$indexName, $uniqueIndexName])
            ->first();

        if (! $indexEntry) {
            return;
        }

        $errors = [];

        try {
            DB::statement("ALTER TABLE records DROP INDEX `{$indexEntry->index_name}`");
        } catch (\Exception $e) {
            $errors[] = 'Failed to drop index: ' . $e->getMessage();
            \Log::warning("Could not drop index {$indexEntry->index_name}: " . $e->getMessage());
        }

        // Drop virtual columns (only if not used by other indexes)
        try {
            foreach ($fieldNames as $name) {
                $vCol = Helper::generateVirtualColumnName($collection, $name);

                // Check if this virtual column is used by other indexes
                $otherIndexesUsingColumn = DB::table('collection_indexes')
                    ->where('collection_id', $collection->id)
                    ->where('id', '!=', $indexEntry->id)
                    ->get()
                    ->filter(function ($index) use ($name) {
                        $fields = json_decode($index->field_names, true);

                        return in_array($name, $fields);
                    });

                if ($otherIndexesUsingColumn->isEmpty()) {
                    try {
                        DB::statement("ALTER TABLE records DROP COLUMN `{$vCol}`");
                    } catch (\Exception $e) {
                        \Log::warning("Could not drop virtual column {$vCol}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Failed to drop virtual columns: ' . $e->getMessage();
            \Log::warning('Error while dropping virtual columns: ' . $e->getMessage());
        }

        // Always remove from tracking table, even if DDL operations failed
        try {
            DB::table('collection_indexes')
                ->where('id', $indexEntry->id)
                ->delete();
        } catch (\Exception $e) {
            $errors[] = 'Failed to delete tracking entry: ' . $e->getMessage();
            throw new IndexOperationException('Failed to remove index from tracking table: ' . $e->getMessage(), 0, $e);
        }

        // Sync field states after dropping index
        try {
            $isUniqueIndex = str_starts_with($indexEntry->index_name, 'uq_');

            foreach ($fieldNames as $fieldName) {
                // Check if this field is still used by other indexes
                $otherIndexesUsingField = DB::table('collection_indexes')
                    ->where('collection_id', $collection->id)
                    ->where('id', '!=', $indexEntry->id)
                    ->get()
                    ->filter(function ($index) use ($fieldName) {
                        $fields = json_decode($index->field_names, true);

                        return in_array($fieldName, $fields);
                    });

                // If no other indexes use this field, reset required and unique
                if ($otherIndexesUsingField->isEmpty()) {
                    $collection->fields()->where('name', $fieldName)->update([
                        'indexed' => false,
                        'unique'  => false,
                    ]);
                } elseif ($isUniqueIndex) {
                    // If this was a unique index but field is still used by other (non-unique) indexes
                    $hasOtherUniqueIndex = $otherIndexesUsingField->contains(function ($index) {
                        return str_starts_with($index->index_name, 'uq_');
                    });

                    if (! $hasOtherUniqueIndex) {
                        $collection->fields()->where('name', $fieldName)->update([
                            'unique' => false,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Failed to sync field states: ' . $e->getMessage();
            \Log::warning('Could not sync field states: ' . $e->getMessage());
        }

        if (! empty($errors)) {
            \Log::warning("Index drop completed with warnings for fields '" . implode(', ', $fieldNames) . "': " . implode('; ', $errors));
        }
    }

    public function hasIndex(Collection $collection, array $fieldNames, bool $unique = false): bool
    {
        $indexName = Helper::generateIndexName($collection, implode('_', $fieldNames), $unique);

        $existsInSchema = collect(DB::select('SHOW INDEX FROM records WHERE Key_name = ?', [$indexName]))->isNotEmpty();

        $existsInTracking = DB::table('collection_indexes')
            ->where('collection_id', $collection->id)
            ->where('index_name', $indexName)
            ->exists();

        return $existsInSchema && $existsInTracking;
    }
}
