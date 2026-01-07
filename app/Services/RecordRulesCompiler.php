<?php

namespace App\Services;

use App\Contracts\IndexStrategy;
use App\Enums\FieldType;
use App\FieldOptions\DatetimeFieldOption;
use App\FieldOptions\EmailFieldOption;
use App\FieldOptions\FileFieldOption;
use App\FieldOptions\NumberFieldOption;
use App\FieldOptions\TextFieldOption;
use App\Helper;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Rules\AllowedEmailDomains;
use App\Rules\BlockedEmailDomains;
use Illuminate\Validation\Rule;

class RecordRulesCompiler
{
    public function __construct(
        protected Collection $collection,
        private IndexStrategy $indexManager,
        private ?string $ignoreId = null,
    ) {}

    /**
     * Returns a laravel style rules for each fields
     * @return string[][]
     */
    public function getRules(string $prefix = ''): array
    {
        $rules = [];

        foreach ($this->collection->fields as $field) {
            if (\in_array($field->name, ['created', 'updated'])) {
                continue;
            }

            $fieldRules = $this->compileFieldRules($field);

            if ($field->type === FieldType::File) {
                // @TODO: Implement File type validation rules
                continue;
            }

            $rules[$prefix . $field->name] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Compile validation rules for a single field
     * @param CollectionField $field
     * @return array<mixed|string|\Illuminate\Validation\Rules\In>
     */
    protected function compileFieldRules(CollectionField $field): array
    {
        $collection = $field->collection;
        $fieldRules = [];

        // Basic required/nullable rules
        if ($field->name === 'id') {
            $fieldRules[] = 'nullable';
        } elseif ($field->required && $field->type !== FieldType::File) {
            $fieldRules[] = 'required';
        } elseif ($field->required && $field->type === FieldType::File) {
            $fieldRules[] = 'required';
            $fieldRules[] = 'min:1';
        } else {
            $fieldRules[] = 'nullable';
        }

        if ($field->unique) {
            $virtualCol = Helper::generateVirtualColumnName($collection, $field->name);
            $indexName = Helper::generateIndexName($collection, $field->name, true);

            if ($this->indexManager->hasIndex($indexName)) {
                $fieldRules[] = Rule::unique('records', $virtualCol)
                    ->where('collection_id', $collection->id)
                    ->ignore($this->ignoreId);
            } else {
                // Slow fallback for non-indexed fields
                \Log::alert('Unique index not found. Reverting to fallback.', [
                    'collection' => $collection->name,
                    'field' => $field->name
                ]);

                $fieldRules[] = Rule::unique('records', "data->>{$field->name}")
                    ->where('collection_id', $collection->id)
                    ->ignore($this->ignoreId);
            }
        }

        // Type-specific rules
        $fieldRules = [...$fieldRules, ...$this->getTypeRules($field)];

        // Option-specific rules
        if ($field->options) {
            $fieldRules = [...$fieldRules, ...$this->getOptionRules($field)];
        }

        return $fieldRules;
    }

    /**
     * Get basic type validation rules
     * @param CollectionField $field
     * @return string[]
     */
    protected function getTypeRules(CollectionField $field): array
    {
        return match ($field->type) {
            FieldType::Email => ['email'],
            FieldType::Number => ['numeric'],
            FieldType::Bool => ['boolean'],
            FieldType::Datetime => ['date'],
            FieldType::File => ['image'],
            FieldType::Text => ['string'],
            default => [],
        };
    }

    /**
     * Get validation rules from field options
     * @param CollectionField $field
     * @return array<string|\Illuminate\Validation\Rules\In>
     */
    protected function getOptionRules(CollectionField $field): array
    {
        $rules = [];
        $options = $field->options;

        switch (true) {
            case $options instanceof TextFieldOption:
                if ($options->minLength !== null) {
                    $rules[] = "min:{$options->minLength}";
                }
                if ($options->maxLength !== null) {
                    $rules[] = "max:{$options->maxLength}";
                }
                if ($options->pattern !== null) {
                    $rules[] = "regex:{$options->pattern}";
                }
                break;

            case $options instanceof EmailFieldOption:
                if ($options->allowedDomains !== null && !empty($options->allowedDomains)) {
                    $rules[] = "email:rfc,dns,filter";
                    $rules[] = new AllowedEmailDomains($options->allowedDomains);
                }
                if ($options->blockedDomains !== null && !empty($options->blockedDomains)) {
                    $rules[] = new BlockedEmailDomains($options->blockedDomains);
                }
                break;

            case $options instanceof NumberFieldOption:
                if ($options->min !== null) {
                    $rules[] = "min:{$options->min}";
                }
                if ($options->max !== null) {
                    $rules[] = "max:{$options->max}";
                }
                if (!$options->allowDecimals) {
                    $rules[] = 'integer';
                    $rules[] = 'integer,decimal:0,2';
                }
                break;

            case $options instanceof DatetimeFieldOption:
                if ($options->minDate !== null) {
                    $rules[] = "after_or_equal:{$options->minDate}";
                }
                if ($options->maxDate !== null) {
                    $rules[] = "before_or_equal:{$options->maxDate}";
                }
                break;

            case $options instanceof FileFieldOption:
                if ($options->allowedMimeTypes !== null && !empty($options->allowedMimeTypes)) {
                    $mimes = implode(',', $options->allowedMimeTypes);
                    $rules[] = "mimetypes:{$mimes}";
                }
                if ($options->maxSize !== null) {
                    // Convert bytes to kilobytes for Laravel validation
                    $maxKb = ceil($options->maxSize / 1024);
                    $rules[] = "max:{$maxKb}";
                }
                if ($options->minSize !== null) {
                    $minKb = floor($options->minSize / 1024);
                    $rules[] = "min:{$minKb}";
                }
                break;
        }

        return $rules;
    }
}
