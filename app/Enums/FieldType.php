<?php

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Number = 'number';
    case Bool = 'boolean';
    case Date = 'date';
    case Timestamp = 'timestamp';
    case File = 'file';
}
