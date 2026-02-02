<?php

namespace App\Domain\Field\FieldOptions;

use App\Domain\Field\Contracts\CollectionFieldOption;

class DatetimeFieldOption implements CollectionFieldOption
{
    public function __construct(
        public ?string $minDate = null,
        public ?string $maxDate = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'minDate' => $this->minDate,
            'maxDate' => $this->maxDate,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            minDate: $data['minDate'] ?? null,
            maxDate: $data['maxDate'] ?? null,
        );
    }

    public function validate(): bool
    {
        // Validate date strings if provided
        if ($this->minDate !== null && ! \in_array($this->minDate, ['now', 'today'])) {
            if (strtotime($this->minDate) === false) {
                return false;
            }
        }

        if ($this->maxDate !== null && ! \in_array($this->maxDate, ['now', 'today'])) {
            if (strtotime($this->maxDate) === false) {
                return false;
            }
        }

        return true;
    }

    public function getValidationRules(): array
    {
        return [
            'minDate' => ['nullable', 'string'],
            'maxDate' => ['nullable', 'string'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
