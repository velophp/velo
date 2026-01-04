<?php

namespace App\Models;

use App\Exceptions\InvalidRecordException;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Str;

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

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Record $record) {
            if (!$record->relationLoaded('collection')) {
                $record->load('collection.fields');
            }

            $fields = $record->collection->fields->keyBy('name');
            $fieldNames = $fields->pluck('name')->sort()->values()->toArray();
            $data = $record->data;

            if (!$data->has('id') || empty($data->get('id'))) {
                $data->put('id', Str::random(16));
            }

            if ($record->exists) {
                $originalData = $record->getOriginal('data');
                if ($originalData instanceof Illuminate\Support\Collection) {
                    $originalData = $originalData->toArray();
                }
                
                if (\in_array('created', $fieldNames) && isset($originalData['created'])) {
                    $data->put('created', $originalData['created']);
                }
                
                foreach ($fieldNames as $fieldName) {
                    if (!$data->has($fieldName) && isset($originalData[$fieldName])) {
                        $data->put($fieldName, $originalData[$fieldName]);
                    }
                }
            }

            if (!$record->exists && \in_array('created', $fieldNames)) {
                if (!$data->has('created') || empty($data->get('created'))) {
                    $data->put('created', now()->toIso8601String());
                }
            }

            if (\in_array('updated', $fieldNames)) {
                $data->put('updated', now()->toIso8601String());
            }

            $record->data = $data;

            // Validate structure
            $dataKeys = $data->keys()->sort()->values()->toArray();
            $missingFields = [];

            foreach ($fieldNames as $requiredField) {
                if (!in_array($requiredField, $dataKeys)) {
                    $missingFields[] = $requiredField;
                }
            }

            if (\count($missingFields) > 0) {
                throw new InvalidRecordException("Record structure mismatch. Missing required fields: " . implode(', ', $missingFields) . ". Expected all fields: " . implode(', ', $fieldNames));
            }
        });
    }
}