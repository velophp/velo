<?php

namespace App\Models;

use App\Exceptions\InvalidRecordException;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

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

            $fields = $record->collection->fields->pluck('name')->sort()->values()->toArray();
            $data = $record->data;

            if (!$data->has('id') || empty($data->get('id'))) {
                $data->put('id', \Illuminate\Support\Str::random(16));
            }

            if (!$record->exists && $data->has('created')) {
                $data->put('created', now()->utc()->toIso8601String());
            }

            if ($data->has('updated')) {
                $data->put('updated', now()->utc()->toIso8601String());
            }

            $record->data = $data;

            $dataKeys = $data->keys()->sort()->values()->toArray();

            if ($dataKeys !== $fields) {
                throw new InvalidRecordException("Record structure mismatch. Expected fields: " . implode(', ', $fields) . ". Got: " . implode(', ', $dataKeys));
            }
        });
    }
}