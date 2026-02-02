<?php

namespace App\Domain\Record\Services;

use App\Delivery\Rules\AllowedEmailDomains;
use App\Delivery\Rules\BlockedEmailDomains;
use App\Delivery\Rules\RecordExists;
use App\Delivery\Rules\ValidFile;
use App\Domain\Collection\Contracts\IndexStrategy;
use App\Domain\Collection\Models\Collection;
use App\Domain\Collection\Services\IndexStrategies\MysqlIndexStrategy;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\FieldOptions\DatetimeFieldOption;
use App\Domain\Field\FieldOptions\EmailFieldOption;
use App\Domain\Field\FieldOptions\FileFieldOption;
use App\Domain\Field\FieldOptions\NumberFieldOption;
use App\Domain\Field\FieldOptions\RelationFieldOption;
use App\Domain\Field\FieldOptions\TextFieldOption;
use App\Domain\Field\Models\CollectionField;
use App\Support\Helper;
use Closure;
use Illuminate\Validation\Rule;

class RecordRulesCompiler
{
    public IndexStrategy $indexStrategy;

    public function __construct(
        protected Collection $collection,
        private ?string $ignoreId = null,
        private ?Closure $nullableWhenFn = null,
        private ?array $formObject = null,
    ) {
        $this->indexStrategy = new MysqlIndexStrategy();
    }

    public function forCollection(Collection $collection): self
    {
        $this->collection = $collection;

        return $this;
    }

    public function using(IndexStrategy $strategy): self
    {
        $this->indexStrategy = $strategy;

        return $this;
    }

    public function ignoreId(?string $id): self
    {
        $this->ignoreId = $id;

        return $this;
    }

    public function nullableWhen(Closure $when): self
    {
        $this->nullableWhenFn = $when;

        return $this;
    }

    public function withForm(array $form): self
    {
        $this->formObject = $form;

        return $this;
    }

    /**
     * Returns a laravel style rules for each fields.
     *
     * @return string[][]
     */
    public function compile(string $prefix = ''): array
    {
        // validate required state
        $this->assertReady();

        return $this->getRules($prefix);
    }

    protected function assertReady(): void
    {
        if (! isset($this->collection)) {
            throw new \LogicException('Collection not set');
        }

        if (! isset($this->indexStrategy)) {
            throw new \LogicException('Index strategy not set');
        }
    }

    /**
     * Returns a laravel style rules for each fields.
     *
     * @return string[][]
     */
    protected function getRules(string $prefix = ''): array
    {
        $rules = [];

        foreach ($this->collection->fields as $field) {
            if (\in_array($field->name, ['created', 'updated'])) {
                continue;
            }

            $fieldRules = $this->compileFieldRules($field);

            // Handle nested array rules (e.g., for relation fields)
            if (isset($fieldRules['*'])) {
                $nestedRules = $fieldRules['*'];
                unset($fieldRules['*']);
                $rules[$prefix . $field->name] = $fieldRules;
                $rules[$prefix . $field->name . '.*'] = $nestedRules;
            } else {
                $rules[$prefix . $field->name] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Compile validation rules for a single field.
     *
     * @return array<mixed|string|\Illuminate\Validation\Rules\In>
     */
    protected function compileFieldRules(CollectionField $field): array
    {
        $collection = $field->collection;
        $fieldRules = [];

        // Basic required/nullable rules
        if ($field->name === 'id' || ($this->nullableWhenFn && ($this->nullableWhenFn)())) {
            $fieldRules[] = 'nullable';
        } elseif ($field->required) {
            $fieldRules[] = 'required';
        } else {
            $fieldRules[] = 'nullable';
        }

        if ($field->unique) {
            $indexes = \DB::table('collection_indexes')
                ->where('collection_id', $collection->id)
                ->whereJsonContains('field_names', $field->name)
                ->where('index_name', 'like', 'uq_%')
                ->get();

            foreach ($indexes as $index) {
                $fields = json_decode($index->field_names);

                $virtualCol = Helper::generateVirtualColumnName($collection, $field->name);

                $rule = Rule::unique('records', $virtualCol)
                    ->where('collection_id', $collection->id);

                foreach ($fields as $otherField) {
                    if ($otherField === $field->name) {
                        continue;
                    }
                    $otherVirtualCol = Helper::generateVirtualColumnName($collection, $otherField);
                    $rule->where($otherVirtualCol, $this->formObject[$otherField]);
                }

                if ($this->ignoreId != null) {
                    $rule->ignore($this->ignoreId, 'data->>id');
                }

                $fieldRules[] = $rule;
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
     * Get basic type validation rules.
     *
     * @return string[]
     */
    protected function getTypeRules(CollectionField $field): array
    {
        return match ($field->type) {
            FieldType::Email    => ['email'],
            FieldType::Number   => ['numeric'],
            FieldType::Bool     => ['boolean'],
            FieldType::Datetime => ['date'],
            FieldType::File     => [],
            FieldType::Text     => ['string'],
            default             => [],
        };
    }

    /**
     * Get validation rules from field options.
     *
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
                    $rules[] = "regex:/{$options->pattern}/";
                }
                break;

            case $options instanceof EmailFieldOption:
                if ($options->allowedDomains !== null && ! empty($options->allowedDomains)) {
                    $rules[] = 'email:rfc,dns,filter';
                    $rules[] = new AllowedEmailDomains($options->allowedDomains);
                }
                if ($options->blockedDomains !== null && ! empty($options->blockedDomains)) {
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
                if (! $options->allowDecimals) {
                    $rules[] = 'integer';
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
                $isMultiple = $options->multiple || ($options->maxFiles && $options->maxFiles > 1);

                if ($isMultiple) {
                    $rules[] = 'array';

                    if ($field->required) {
                        $rules[] = 'min:1';
                    }

                    if ($options->maxFiles) {
                        $rules[] = "max:{$options->maxFiles}";
                    }

                    $rules['*'] = ['nullable', new ValidFile($options)];
                } else {
                    $rules[] = new ValidFile($options);
                }
                break;

            case $options instanceof RelationFieldOption:
                if ($options->multiple) {
                    $rules[] = 'array';

                    if ($options->minSelect !== null) {
                        $rules[] = "min:{$options->minSelect}";
                    }

                    if ($options->maxSelect !== null) {
                        $rules[] = "max:{$options->maxSelect}";
                    }

                    if ($options->collection !== null) {
                        $rules['*'] = [
                            new RecordExists($options->collection),
                        ];
                    }
                } else {
                    if ($options->collection !== null) {
                        $rules[] = new RecordExists($options->collection);
                    }
                }
                break;
        }

        return $rules;
    }
}
