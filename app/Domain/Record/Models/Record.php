<?php

namespace App\Domain\Record\Models;

use App\Delivery\Casts\AsSafeCollection;
use App\Delivery\Entity\SafeCollection;
use App\Delivery\Services\RealtimeService;
use App\Domain\Collection\Handlers\CollectionTypeHandlerResolver;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Project\Exceptions\InvalidRecordException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Record extends Model
{
    protected $fillable = ['collection_id', 'data'];

    protected function casts(): array
    {
        return [
            'data' => AsSafeCollection::class,
        ];
    }

    protected function documentId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->data->get('id'),
            set: function ($value) {
                $data = $this->data;
                $data->put('id', $value);

                return ['data' => $data];
            }
        );
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public static function defaultValueFor(FieldType $type): mixed
    {
        return match ($type) {
            FieldType::Text   => '',
            FieldType::Number => 0,
            FieldType::Bool   => false,
            default           => null,
        };
    }

    protected static function booted(): void
    {
        static::retrieved(function (Record $record) {
            app(\App\Domain\Collection\Handlers\BaseCollectionHandler::class)->onRetrieved($record);

            $handler = CollectionTypeHandlerResolver::resolve($record->collection->type);
            if ($handler) {
                $handler->onRetrieved($record);
            }

            // Hook: record.retrieved
            $data = $record->data->toArray();
            $data = \App\Domain\Hooks\Facades\Hooks::apply('record.retrieved', $data, [
                'collection' => $record->collection,
                'record_id'  => $record->id,
            ]);
            $record->data = new SafeCollection($data);
        });

        static::creating(function (Record $record) {
            // Hook: record.creating
            $data = $record->data->toArray();
            $data = \App\Domain\Hooks\Facades\Hooks::apply('record.creating', $data, [
                'collection' => $record->collection,
            ]);
            $record->data = new SafeCollection($data);
        });

        static::created(function (Record $record) {
            // Hook: record.created
            \App\Domain\Hooks\Facades\Hooks::trigger('record.created', [
                'collection' => $record->collection,
                'record'     => $record->data->toArray(),
                'record_id'  => $record->id,
            ]);
        });

        static::updating(function (Record $record) {
            // Hook: record.updating
            $data = $record->data->toArray();
            $data = \App\Domain\Hooks\Facades\Hooks::apply('record.updating', $data, [
                'collection'    => $record->collection,
                'record_id'     => $record->id,
                'original_data' => $record->getOriginal('data')->toArray(),
            ]);
            $record->data = new SafeCollection($data);
        });

        static::updated(function (Record $record) {
            // Hook: record.updated
            \App\Domain\Hooks\Facades\Hooks::trigger('record.updated', [
                'collection' => $record->collection,
                'record'     => $record->data->toArray(),
                'record_id'  => $record->id,
            ]);
        });

        static::deleting(function (Record $record) {
            try {
                DB::beginTransaction();

                // Hook: record.deleting
                \App\Domain\Hooks\Facades\Hooks::trigger('record.deleting', [
                    'collection' => $record->collection,
                    'record'     => $record->data->toArray(),
                    'record_id'  => $record->id,
                ]);

                app(\App\Domain\Collection\Handlers\BaseCollectionHandler::class)->beforeDelete($record);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        });

        static::deleted(function (Record $record) {
            // Hook: record.deleted
            \App\Domain\Hooks\Facades\Hooks::trigger('record.deleted', [
                'collection' => $record->collection,
                'record'     => $record->data->toArray(),
                'record_id'  => $record->id,
            ]);

            app(RealtimeService::class)->dispatchUpdates($record, 'deleted');
        });

        static::saving(function (Record $record) {
            if (! $record->relationLoaded('collection')) {
                $record->load('collection.fields');
            }

            $fields = $record->collection->fields->keyBy('name');
            $fieldNames = $fields->keys()->sort()->values()->toArray();

            if (empty($record->documentId)) {
                $min = $fields['id']->options->minLength ?? 16;
                $max = $fields['id']->options->maxLength ?? 16;
                $length = random_int($min, $max);
                $record->documentId = Str::random($length);
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

            app(\App\Domain\Collection\Handlers\BaseCollectionHandler::class)->beforeSave($record);

            $handler = CollectionTypeHandlerResolver::resolve($record->collection->type);
            if ($handler) {
                $handler->beforeSave($record);
            }

            $dataKeys = $record->data->keys()->sort()->values()->toArray();
            $missingFields = array_diff($fieldNames, $dataKeys);

            if (! empty($missingFields)) {
                throw new InvalidRecordException('Record structure mismatch. Missing required fields: ' . implode(', ', $missingFields) . '. Expected all fields: ' . implode(', ', $fieldNames));
            }
        });

        static::saved(function (Record $record) {
            $action = $record->wasRecentlyCreated ? 'created' : 'updated';
            app(RealtimeService::class)->dispatchUpdates($record, $action);
        });
    }
}
