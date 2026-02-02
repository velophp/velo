<?php

namespace App\Domain\Field\FieldOptions;

use App\Domain\Field\Contracts\CollectionFieldOption;

class NumberFieldOption implements CollectionFieldOption
{
    public function __construct(
        public string|int|float|null $min = null,
        public string|int|float|null $max = null,
        public bool $allowDecimals = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'min'           => $this->min,
            'max'           => $this->max,
            'allowDecimals' => $this->allowDecimals,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            min: $data['min'] ?? null,
            max: $data['max'] ?? null,
            allowDecimals: $data['allowDecimals'] ?? false,
        );
    }

    public function validate(): bool
    {
        if ($this->min !== null && $this->max !== null) {
            if ($this->min > $this->max) {
                return false;
            }
        }

        return true;
    }

    public function getValidationRules(): array
    {
        return [
            'min'           => ['nullable', 'numeric'],
            'max'           => ['nullable', 'numeric'],
            'allowDecimals' => ['boolean'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
