<?php

namespace App\Services;

use App\Enums\FieldType;
use App\Models\Collection;

class RecordRulesCompiler
{
    public function __construct(
        protected Collection $collection
    ) {}

    public function getRules(): array
    {
        $rules = [];

        foreach ($this->collection->fields as $field) {
            if (in_array($field->name, ['created', 'updated'])) {
                continue;
            }

            $fieldRules = [];

            if ($field->name === 'id') {
                $fieldRules[] = 'nullable';
            } elseif ($field->required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($field->type === FieldType::Email) {
                $fieldRules[] = 'email';
            }

            if ($field->type === FieldType::Number) {
                $fieldRules[] = 'numeric';
            }

            if ($field->type === FieldType::Bool) {
                $fieldRules[] = 'boolean';
            }

            $rules['form.' . $field->name] = $fieldRules;
        }

        return $rules;
    }
}
