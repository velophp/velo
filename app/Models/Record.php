<?php

namespace App\Models;

use Illuminate\Support\Str;
use App\Enums\CollectionType;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\InvalidRecordException;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use App\Collections\Handlers\CollectionTypeHandlerResolver;

class Record extends Model
{
    protected $fillable = ['collection_id', 'data'];

    protected function casts(): array
    {
        return [
            'data' => AsCollection::class
        ];
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Record $record) {
            if (!$record->relationLoaded('collection')) {
                $record->load('collection.fields');
            }

            $fields = $record->collection->fields->keyBy('name');
            $fieldNames = $fields->keys()->sort()->values()->toArray();
            $data = $record->data;

            if (!$data->has('id') || empty($data->get('id'))) {
                $min = $fields['id']->options->minLength ?? 16;
                $max = $fields['id']->options->maxLength ?? 16;
                $length = random_int($min, $max);
                $data->put('id', Str::random($length));
            }

            app(\App\Collections\Handlers\BaseCollectionHandler::class)->beforeSave($record);

            // Type-specific rules
            $handler = CollectionTypeHandlerResolver::resolve($record->collection->type);
            if ($handler) {
                $handler->beforeSave($record);
            }

            // Prevent data loss on update
            if ($record->exists) {
                $originalData = $record->getOriginal('data');

                $originalData = $originalData instanceof \Illuminate\Support\Collection
                    ? $originalData->toArray()
                    : $originalData;

                foreach ($fieldNames as $fieldName) {
                    if (!$data->has($fieldName) && isset($originalData[$fieldName])) {
                        $data->put($fieldName, $originalData[$fieldName]);
                    }
                }
            }

            // Clean unwanted keys
            $data = $data->only($fieldNames);

            $record->data = $data;

            // Validate structure
            $dataKeys = $data->keys()->sort()->values()->toArray();
            $missingFields = array_diff($fieldNames, $dataKeys);

            if (!empty($missingFields)) {
                throw new InvalidRecordException(
                    "Record structure mismatch. Missing required fields: " .
                    implode(', ', $missingFields) .
                    ". Expected all fields: " .
                    implode(', ', $fieldNames)
                );
            }
        });
    }
}