<?php

namespace App\Models;

use App\Enums\CollectionType;
use App\Models\CollectionField;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';

    protected $fillable = ['project_id', 'name', 'type'];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class
        ];
    }

    public function fields()
    {
        return $this->hasMany(CollectionField::class);
    }
}
