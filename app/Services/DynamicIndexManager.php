<?php

namespace App\Services;

use App\Models\Collection;
use App\Services\IndexStrategies\IndexStrategy;
use App\Services\IndexStrategies\MysqlIndexStrategy;
use App\Services\IndexStrategies\PostgresIndexStrategy;
use Illuminate\Support\Facades\DB;

class DynamicIndexManager
{
    protected static function getStrategy(): IndexStrategy
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => new MysqlIndexStrategy(),
            'pgsql' => new PostgresIndexStrategy(),
            default => throw new \Exception("Unsupported database driver: {$driver}"),
        };
    }

    public static function createUniqueIndex(Collection $collection, string $fieldName)
    {
        self::getStrategy()->createIndex($collection, $fieldName, true);
    }

    public static function createIndex(Collection $collection, string $fieldName)
    {
        self::getStrategy()->createIndex($collection, $fieldName, false);
    }

    public static function dropIndex(Collection $collection, string $fieldName)
    {
        self::getStrategy()->dropIndex($collection, $fieldName);
    }
}
