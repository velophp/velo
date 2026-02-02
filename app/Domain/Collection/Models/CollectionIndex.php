<?php

namespace App\Domain\Collection\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionIndex extends Model
{
    protected $table = 'collection_indexes';

    protected function casts(): array
    {
        return [
            'field_names' => 'array',
        ];
    }
}
