<?php

namespace App\Domain\Field\FieldOptions;

use App\Domain\Field\Contracts\CollectionFieldOption;

class TextFieldOption implements CollectionFieldOption
{
    public function __construct(
        public string|int|null $minLength = null,
        public string|int|null $maxLength = null,
        public ?string $pattern = null,
        public ?string $autoGeneratePattern = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'minLength'           => $this->minLength,
            'maxLength'           => $this->maxLength,
            'pattern'             => $this->pattern,
            'autoGeneratePattern' => $this->autoGeneratePattern,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            minLength: $data['minLength'] ?? null,
            maxLength: $data['maxLength'] ?? null,
            pattern: $data['pattern'] ?? null,
            autoGeneratePattern: $data['autoGeneratePattern'] ?? null,
        );
    }

    public function validate(): bool
    {
        return true;
    }

    public function getValidationRules(): array
    {
        return [
            'minLength'           => ['nullable', 'integer', 'min:0', 'max:' . PHP_INT_MAX],
            'maxLength'           => ['nullable', 'integer', 'min:1', 'max:' . PHP_INT_MAX],
            'pattern'             => ['nullable', 'string'],
            'autoGeneratePattern' => ['nullable', 'string'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'pattern' => 'it must be a string yoo...',
        ];
    }
}
