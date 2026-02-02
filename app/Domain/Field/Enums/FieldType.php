<?php

namespace App\Domain\Field\Enums;

enum FieldType: string
{
    case Relation = 'relation';
    case Text = 'text';
    case Email = 'email';
    case Number = 'number';
    case Bool = 'boolean';
    case Datetime = 'datetime';
    case RichText = 'richtext';
    case File = 'file';

    public static function toArray(): array
    {
        return array_map(
            fn ($case) => [
                'id'   => $case->value,
                'name' => $case->name,
            ],
            self::cases(),
        );
    }
}
