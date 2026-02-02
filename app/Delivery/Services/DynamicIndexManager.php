<?php

namespace App\Delivery\Services;

use App\Domain\Collection\Contracts\IndexStrategy;
use App\Domain\Collection\Models\Collection;
use App\Domain\Collection\Services\IndexStrategies\MysqlIndexStrategy;
use App\Domain\Collection\Services\IndexStrategies\PostgresIndexStrategy;
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
