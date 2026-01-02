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

    public static function createAuthFrom($fields)
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
                'type' => FieldType::Password,
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

    public static function createFrom($fields)
    {

        $headers = [
            [
                'name' => 'id',
                'type' => FieldType::Number,
                'unique' => true,
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

        return $headers;
    }
}
