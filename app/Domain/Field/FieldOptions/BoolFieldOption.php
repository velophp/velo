<?php

namespace App\Domain\Field\FieldOptions;

use App\Domain\Field\Contracts\CollectionFieldOption;

class BoolFieldOption implements CollectionFieldOption
{
    public function __construct()
    {
    }

    public function toArray(): array
    {
        return [];
    }

    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function validate(): bool
    {
        return true;
    }

    public function getValidationRules(): array
    {
        return [];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
