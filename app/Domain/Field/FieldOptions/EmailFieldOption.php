<?php

namespace App\Domain\Field\FieldOptions;

use App\Delivery\Rules\ValidDomain;
use App\Domain\Field\Contracts\CollectionFieldOption;

class EmailFieldOption implements CollectionFieldOption
{
    public function __construct(
        public ?array $allowedDomains = [], // e.g., ['gmail.com', 'company.com']
        public ?array $blockedDomains = [],
    ) {
    }

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
            'allowedDomains'   => ['array'],
            'allowedDomains.*' => ['string', new ValidDomain()],
            'blockedDomains'   => ['array'],
            'blockedDomains.*' => ['string', new ValidDomain()],
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
