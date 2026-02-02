<?php

namespace App\Domain\Collection\Handlers;

use App\Domain\Collection\Contracts\CollectionTypeHandler;
use App\Domain\Collection\Enums\CollectionType;

class CollectionTypeHandlerResolver
{
    public static function resolve(CollectionType $type): ?CollectionTypeHandler
    {
        return match ($type) {
            CollectionType::Auth => app(AuthCollectionHandler::class),
            default              => null,
        };
    }
}
