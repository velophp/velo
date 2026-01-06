<?php

namespace App\Contracts;

interface CollectionFieldOption
{
    /**
     * Convert the option instance to an array.
     * 
     * @return array
     */
    public function toArray(): array;

    /**
     * Create an instance from an array.
     * 
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static;

    /**
     * @deprecated use getValidationRules
     * @return bool
     */
    public function validate(): bool;

    public function getValidationRules(): array;
    public function getValidationMessages(): array;
}
