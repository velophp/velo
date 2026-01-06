<?php

namespace App\FieldOptions;

use App\Contracts\CollectionFieldOption;
use App\Rules\Domain;

class EmailFieldOption implements CollectionFieldOption
{
    public function __construct(
        public ?array $allowedDomains = [], // e.g., ['gmail.com', 'company.com']
        public ?array $blockedDomains = [],
    ) {}

    public function toArray(): array
    {
        return [
            'allowedDomains' => $this->allowedDomains,
            'blockedDomains' => $this->blockedDomains,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            allowedDomains: $data['allowedDomains'] ?? [],
            blockedDomains: $data['blockedDomains'] ?? [],
        );
    }

    public function validate(): bool
    {
        return true;
    }

    public function getValidationRules(): array
    {
        return [
            'allowedDomains' => ['array'],
            'allowedDomains.*' => ['string', new Domain],
            'blockedDomains' => ['array'],
            'blockedDomains.*' => ['string', new Domain],
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
