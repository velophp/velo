<?php

namespace App\Models;

use App\Enums\FieldType;
use Illuminate\Support\Str;
use App\Events\CollectionUpdated;
use App\Services\RealtimeService;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\InvalidRecordException;
use App\Collections\Handlers\CollectionTypeHandlerResolver;

class Record extends Model
{
    protected $fillable = ['collection_id', 'data'];

    protected function casts(): array
    {
        return [
            'data' => \Illuminate\Database\Eloquent\Casts\AsCollection::class,
        ];
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public static function defaultValueFor(FieldType $type)
    {
        return match ($type) {
            FieldType::Text => '',
            FieldType::Number => 0,
            FieldType::Bool => false,
            default => null,
        };
    }

    protected static function booted(): void
    {
        static::saving(function (Record $record) {
            if (! $record->relationLoaded('collection')) {
                $record->load('collection.fields');
            }

            $fields = $record->collection->fields->keyBy('name');
            $fieldNames = $fields->keys()->sort()->values()->toArray();

            if (! $record->data->has('id') || empty($record->data->get('id'))) {
                $min = $fields['id']->options->minLength ?? 16;
                $max = $fields['id']->options->maxLength ?? 16;
                $length = random_int($min, $max);
                $record->data->put('id', Str::random($length));
            }

            // Prevent data loss on update
            if ($record->exists) {
                $originalData = $record->getOriginal('data');

                $originalData = $originalData instanceof \Illuminate\Support\Collection
                    ? $originalData->toArray()
                    : $originalData;

                foreach ($fieldNames as $fieldName) {
                    if (! $record->data->has($fieldName) && isset($originalData[$fieldName])) {
                        $record->data->put($fieldName, $originalData[$fieldName]);
                    }
                }
            }

            // Validate structure
            $dataKeys = $record->data->keys()->sort()->values()->toArray();
            $missingFields = array_diff($fieldNames, $dataKeys);

            foreach ($missingFields as $field) {
                $record->data->put($field, self::defaultValueFor($fields[$field]->type));
            }

            app(\App\Collections\Handlers\BaseCollectionHandler::class)->beforeSave($record);

            $handler = CollectionTypeHandlerResolver::resolve($record->collection->type);
            if ($handler) {
                $handler->beforeSave($record);
            }

            $dataKeys = $record->data->keys()->sort()->values()->toArray();
            $missingFields = array_diff($fieldNames, $dataKeys);

            if (! empty($missingFields)) {
                throw new InvalidRecordException('Record structure mismatch. Missing required fields: '.implode(', ', $missingFields).'. Expected all fields: '.implode(', ', $fieldNames));
            }
        });

        static::deleting(function (Record $record) {
            try {
                \DB::beginTransaction();
                app(\App\Collections\Handlers\BaseCollectionHandler::class)->beforeDelete($record);
                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }
        });

        static::saved(function (Record $record) {
            $action = $record->wasRecentlyCreated ? 'create' : 'update';
            app(RealtimeService::class)->dispatchUpdates($record, $action);
        });

        static::deleted(function (Record $record) {
            app(RealtimeService::class)->dispatchUpdates($record, 'delete');
        });
    }
}
