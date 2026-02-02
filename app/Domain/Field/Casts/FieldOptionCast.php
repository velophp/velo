<?php

namespace App\Domain\Field\Casts;

use App\Domain\Field\Contracts\CollectionFieldOption;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\FieldOptions\BoolFieldOption;
use App\Domain\Field\FieldOptions\DatetimeFieldOption;
use App\Domain\Field\FieldOptions\EmailFieldOption;
use App\Domain\Field\FieldOptions\FileFieldOption;
use App\Domain\Field\FieldOptions\NumberFieldOption;
use App\Domain\Field\FieldOptions\RelationFieldOption;
use App\Domain\Field\FieldOptions\RichTextFieldOption;
use App\Domain\Field\FieldOptions\TextFieldOption;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class FieldOptionCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CollectionFieldOption
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode($value, true);

        if (! \is_array($data)) {
            return null;
        }

        // Get the field type from the model
        $fieldType = $attributes['type'] ?? null;

        if (! $fieldType) {
            return null;
        }

        // Convert string to FieldType enum if necessary
        if (\is_string($fieldType)) {
            $fieldType = FieldType::from($fieldType);
        }

        // Map field type to option class
        $optionClass = $this->getOptionClass($fieldType);

        if (! $optionClass) {
            return null;
        }

        return $optionClass::fromArray($data);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CollectionFieldOption) {
            return json_encode($value->toArray());
        }

        if (\is_array($value)) {
            // Get the field type to instantiate the correct class
            // Check model first (for existing records), then attributes (for new records), then original attributes
            $fieldType = $model->type ?? $attributes['type'] ?? $model->getOriginal('type') ?? null;

            if (! $fieldType) {
                // If we still don't have a type, just store the JSON without validation
                return json_encode($value);
            }

            // Convert string to FieldType enum if necessary
            if (\is_string($fieldType)) {
                $fieldType = FieldType::from($fieldType);
            }

            $optionClass = $this->getOptionClass($fieldType);

            if (! $optionClass) {
                // If no option class found, just store the JSON
                return json_encode($value);
            }

            $instance = $optionClass::fromArray($value);

            return json_encode($instance->toArray());
        }

        throw new \InvalidArgumentException('Options must be an array or CollectionFieldOption instance');
    }

    /**
     * Get the option class for a given field type.
     */
    protected function getOptionClass(FieldType $fieldType): ?string
    {
        return match ($fieldType) {
            FieldType::Relation => RelationFieldOption::class,
            FieldType::Text     => TextFieldOption::class,
            FieldType::Email    => EmailFieldOption::class,
            FieldType::Number   => NumberFieldOption::class,
            FieldType::Bool     => BoolFieldOption::class,
            FieldType::Datetime => DatetimeFieldOption::class,
            FieldType::File     => FileFieldOption::class,
            FieldType::RichText => RichTextFieldOption::class,
            default             => throw new \Exception("Field Type {$fieldType->name} is not supported."),
        };
    }
}
