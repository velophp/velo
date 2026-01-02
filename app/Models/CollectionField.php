<?php

namespace App\Models;

use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;

class CollectionField extends Model
{
    protected $fillable = ['collection_id', 'name', 'type', 'rules', 'unique', 'required', 'indexed'];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class
        ];
    }
}
