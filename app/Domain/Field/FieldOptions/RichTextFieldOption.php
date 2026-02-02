<?php

namespace App\Domain\Field\FieldOptions;

use App\Domain\Field\Contracts\CollectionFieldOption;

class RichTextFieldOption implements CollectionFieldOption
{
    public function __construct(
        public ?int $minLength = null,
        public ?int $maxLength = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            minLength: $data['minLength'] ?? null,
            maxLength: $data['maxLength'] ?? null,
        );
    }

    public function validate(): bool
    {
        return true;
    }

    public function getValidationRules(): array
    {
        return [
            'minLength' => ['nullable', 'integer', 'min:0', 'max:' . PHP_INT_MAX],
            'maxLength' => ['nullable', 'integer', 'min:1', 'max:' . PHP_INT_MAX],
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
