<?php

namespace App\Domain\Field\Enums;

class FieldTypeMapper
{
    public static function fromSqlType(string $type): FieldType
    {
        $type = strtolower($type);

        return match (true) {
            // Relations (foreign keys)
            str_contains($type, 'foreign'),
            str_ends_with($type, '_id') => FieldType::Relation,

            // Email (best-effort guess)
            str_contains($type, 'email') => FieldType::Email,

            // Boolean
            in_array($type, [
                'bool', 'boolean',
            ]) => FieldType::Bool,

            // Numbers (int, bigint, decimal, float, etc)
            str_contains($type, 'int'),
            str_contains($type, 'decimal'),
            str_contains($type, 'numeric'),
            str_contains($type, 'float'),
            str_contains($type, 'double'),
            str_contains($type, 'real') => FieldType::Number,

            // Date & time
            str_contains($type, 'date'),
            str_contains($type, 'time'),
            str_contains($type, 'timestamp'),
            str_contains($type, 'datetime') => FieldType::Datetime,

            // Rich text / large text
            in_array($type, [
                'text', 'mediumtext', 'longtext',
                'json', 'jsonb',
            ]) => FieldType::RichText,

            // Files (heuristic)
            str_contains($type, 'blob'),
            str_contains($type, 'binary'),
            str_contains($type, 'bytea') => FieldType::File,

            // Default
            default => FieldType::Text,
        };
    }
}
