<?php

namespace App\Enums;

enum CollectionType: string
{
    case Base = 'Base';
    case Auth = 'Auth';
    case View = 'View';

    public static function toOptions(): array
    {
        return array_map(
            fn ($case) => [
                'id' => $case->value,
                'name' => $case->name,
            ],
            self::cases(),
        );
    }
}
