<?php

namespace App\Domain\Field\Models;

use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Casts\FieldOptionCast;
use App\Domain\Field\Enums\FieldType;
use App\Support\Helper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionField extends Model
{
    protected $fillable = ['collection_id', 'order', 'name', 'type', 'rules', 'unique', 'required', 'indexed', 'locked', 'options', 'hidden'];

    protected function casts(): array
    {
        return [
            'type'             => FieldType::class,
            'options'          => FieldOptionCast::class,
            'relation_options' => 'array',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function getIcon()
    {
        return Helper::getFieldTypeIcon($this->name, $this->type);
    }

    public static function createBaseFrom($fields): array
    {
        $fields = [
            [
                'name'     => 'id',
                'type'     => FieldType::Text,
                'unique'   => true,
                'required' => true,
                'locked'   => true,
                'options'  => [],
            ],

            ...$fields,

            [
                'name'     => 'created',
                'type'     => FieldType::Datetime,
                'unique'   => false,
                'required' => false,
                'locked'   => false,
                'options'  => [],
            ],
            [
                'name'     => 'updated',
                'type'     => FieldType::Datetime,
                'unique'   => false,
                'required' => false,
                'locked'   => false,
                'options'  => [],
            ],
        ];

        return $fields;
    }

    public static function createAuthFrom($fields): array
    {
        $fields = [
            [
                'name'     => 'id',
                'type'     => FieldType::Text,
                'unique'   => true,
                'required' => true,
                'locked'   => true,
                'options'  => [],
            ],
            [
                'name'     => 'email',
                'type'     => FieldType::Email,
                'unique'   => true,
                'required' => true,
                'locked'   => true,
                'options'  => [],
            ],
            [
                'name'     => 'verified',
                'type'     => FieldType::Bool,
                'unique'   => false,
                'required' => false,
                'locked'   => true,
                'options'  => [],
            ],
            [
                'name'     => 'password',
                'type'     => FieldType::Text,
                'unique'   => false,
                'required' => true,
                'locked'   => true,
                'hidden'   => true,
                'options'  => [],
            ],

            ...$fields,

            [
                'name'     => 'created',
                'type'     => FieldType::Datetime,
                'unique'   => false,
                'required' => false,
                'locked'   => true,
                'options'  => [],
            ],
            [
                'name'     => 'updated',
                'type'     => FieldType::Datetime,
                'unique'   => false,
                'required' => false,
                'locked'   => true,
                'options'  => [],
            ],
        ];

        return $fields;
    }

    protected static function booted()
    {
        static::saving(function (CollectionField $field) {
            if ($field->collection->type === CollectionType::Auth && $field->name === 'password') {
                $field->hidden = true;
            }

            if ($field->order === null) {
                $maxOrder = static::where('collection_id', $field->collection_id)->max('order');
                $field->order = ($maxOrder ?? -1) + 1;
            }

            if ($field->type === FieldType::Relation) {
                $field->indexed = true;
            }
        });
    }
}
