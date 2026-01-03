<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Models\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionField extends Model
{
    protected $fillable = ['collection_id', 'name', 'type', 'rules', 'unique', 'required', 'indexed'];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public static function createAuthFrom($fields): array
    {
        $fields = [
            [
                'name' => 'id',
                'type' => FieldType::Text,
                'unique' => true,
                'required' => true,
            ],
            [
                'name' => 'email',
                'type' => FieldType::Email,
                'unique' => true,
                'required' => true,
            ],
            [
                'name' => 'verified',
                'type' => FieldType::Bool,
                'unique' => false,
                'required' => false,
            ],
            [
                'name' => 'password',
                'type' => FieldType::Text,
                'unique' => false,
                'required' => true,
            ],

            ...$fields,

            [
                'name' => 'created',
                'type' => FieldType::Timestamp,
                'unique' => false,
                'required' => false,
            ],
            [
                'name' => 'updated',
                'type' => FieldType::Timestamp,
                'unique' => false,
                'required' => false,
            ],
        ];

        return $fields;
    }
}
