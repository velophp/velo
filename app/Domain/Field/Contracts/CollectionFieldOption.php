<?php

namespace App\Domain\Field\Contracts;

interface CollectionFieldOption
{
    /**
     * Convert the option instance to an array.
     */
    public function toArray(): array;

    /**
     * Create an instance from an array.
     */
    public static function fromArray(array $data): static;

    /**
     * @deprecated use getValidationRules
     */
    public function validate(): bool;

    public function getValidationRules(): array;

    public function getValidationMessages(): array;
}
