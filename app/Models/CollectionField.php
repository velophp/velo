<?php

namespace App\Models;

use App\Casts\FieldOptionCast;
use App\Enums\FieldType;
use App\Helper;
use App\Models\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionField extends Model
{
    protected $fillable = ['collection_id', 'order', 'name', 'type', 'rules', 'unique', 'required', 'indexed', 'locked', 'options', 'hidden'];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'options' => FieldOptionCast::class,
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function getIcon() {
        return Helper::getFieldTypeIcon($this->name, $this->type);
    }

    public static function createAuthFrom($fields): array
    {
        $fields = [
            [
                'name' => 'id',
                'type' => FieldType::Text,
                'unique' => true,
                'required' => true,
                'locked' => true,
                'options' => [],
            ],
            [
                'name' => 'email',
                'type' => FieldType::Email,
                'unique' => true,
                'required' => true,
                'locked' => true,
                'options' => [],
            ],
            [
                'name' => 'verified',
                'type' => FieldType::Bool,
                'unique' => false,
                'required' => false,
                'locked' => true,
                'options' => [],
            ],
            [
                'name' => 'password',
                'type' => FieldType::Text,
                'unique' => false,
                'required' => true,
                'locked' => true,
                'options' => [],
            ],

            ...$fields,

            [
                'name' => 'created',
                'type' => FieldType::Datetime,
                'unique' => false,
                'required' => false,
                'locked' => true,
                'options' => [],
            ],
            [
                'name' => 'updated',
                'type' => FieldType::Datetime,
                'unique' => false,
                'required' => false,
                'locked' => true,
                'options' => [],
            ],
        ];

        return $fields;
    }

    protected static function booted()
    {
        static::saving(function (CollectionField $field) {
            if ($field->order === null) {
                $maxOrder = static::where('collection_id', $field->collection_id)->max('order');
                $field->order = ($maxOrder ?? -1) + 1;
            }
        });
    }
}
