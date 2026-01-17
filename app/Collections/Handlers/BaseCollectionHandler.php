<?php

namespace App\Collections\Handlers;

use App\Enums\FieldType;
use App\Exceptions\InvalidRecordException;
use App\Models\CollectionField;
use App\Models\Record;
use App\Models\RecordIndex;

use Illuminate\Support\Collection;

class BaseCollectionHandler implements CollectionTypeHandler
{
    public function beforeSave(Record &$record): void
    {
        $fields = $record->collection->fields->keyBy('name');
        $data = $record->data;

        if (! $record->exists && $fields->has('created')) {
            if (! $data->has('created') || ! filled($data->get('created'))) {
                $data->put('created', now()->toIso8601String());
            }
        }

        $textPatternFields = $fields->filter(fn ($field) => $field->type === FieldType::Text && ! empty($field->options->autoGeneratePattern ?? null));
        foreach ($textPatternFields as $field) {
            if (! filled($data->get($field->name))) {
                $data->put($field->name, fake(config('app.locale'))->regexify($field->options->autoGeneratePattern));
            }
        }

        if ($fields->has('updated')) {
            $data->put('updated', now()->toIso8601String());
        }

        // preserve created on update
        if ($record->exists && $fields->has('created')) {
            $originalData = $record->getOriginal('data');

            $originalData = $originalData instanceof Collection
                ? $originalData->toArray()
                : $originalData;

            if (isset($originalData['created'])) {
                $data->put('created', $originalData['created']);
            }
        }

        $record->data = $data;

        $this->syncIndexes($record);
    }

    private function syncIndexes(Record $record): void
    {
        \DB::beginTransaction();

        $relationFields = $record->collection->fields()->where('type', FieldType::Relation)->get();
        $data = $record->data;
        $recordId = $data->get('id');

        // Delete existing indexes for this record
        RecordIndex::where('collection_id', $record->collection_id)
            ->where('record_id', $recordId)
            ->delete();

        $indexToInsert = [];

        foreach ($relationFields as $field) {
            $value = $data->get($field->name);

            if ($value === null || $value === '') {
                continue;
            }

            if ($field->options?->multiple) {
                foreach ((array) $value as $val) {
                    $indexToInsert[] = $this->makeRecordIndexData($record, $field, $val);
                }

                continue;
            }

            $singleValue = is_array($value) ? ($value[0] ?? null) : $value;

            if ($singleValue === null || $singleValue === '') {
                continue;
            }

            $indexToInsert[] = $this->makeRecordIndexData($record, $field, $singleValue);
        }

        RecordIndex::insert($indexToInsert);

        \DB::commit();
    }

    private function makeRecordIndexData(Record $record, CollectionField $field, mixed $value): array
    {
        $indexData = [
            'collection_id' => $record->collection_id,
            'record_id' => $record->data->get('id'),
            'field' => $field->name,
            'value_string' => null,
            'value_number' => null,
            'value_datetime' => null,
        ];

        match ($field->type) {
            FieldType::Number => $indexData['value_number'] = (float) $value,
            FieldType::Datetime => $indexData['value_datetime'] = $value,
            FieldType::Bool => $indexData['value_number'] = (int) $value,
            default => $indexData['value_string'] = (string) $value,
        };

        return $indexData;
    }

    public function beforeDelete(Record &$record): void
    {
        $collection = $record->collection;
        $data = $record->data;
        $recordId = $data->get('id');

        // Find all record_indexes from OTHER collections where this record's ID is the value
        // (meaning other records reference this record via a relation field)
        $referencingIndexes = RecordIndex::where('value_string', $recordId)
            ->where('collection_id', '!=', $collection->id)
            ->get();

        // Group by collection_id and field to check cascadeDelete options
        $groupedByField = $referencingIndexes->groupBy(fn ($index) => $index->collection_id.'.'.$index->field);

        foreach ($groupedByField as $key => $indexes) {
            $firstIndex = $indexes->first();

            // Get the field definition to check cascadeDelete
            $field = CollectionField::where('collection_id', $firstIndex->collection_id)
                ->where('name', $firstIndex->field)
                ->first();

            if (! $field) {
                continue;
            }

            // If cascadeDelete is false, throw an exception
            if (! $field->options?->cascadeDelete) {
                throw new InvalidRecordException("Cannot delete record: it is referenced by {$indexes->count()} record(s) in field '{$field->name}' of collection '{$field->collection->name}'.");
            }

            // cascadeDelete is true - delete referencing records
            foreach ($indexes as $index) {
                $referencingRecord = Record::where('collection_id', $index->collection_id)
                    ->whereJsonContains('data->id', $index->record_id)
                    ->first();

                if ($referencingRecord) {
                    $referencingRecord->delete();
                }
            }
        }

        // Clean up all indexes where this record is the value (being referenced)
        RecordIndex::where('value_string', $recordId)->delete();

        // Clean up all indexes owned by this record
        $collection->recordIndexes()->where('record_id', $recordId)->delete();
    }
}
