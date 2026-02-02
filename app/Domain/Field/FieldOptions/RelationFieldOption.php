<?php

namespace App\Domain\Field\FieldOptions;

use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Contracts\CollectionFieldOption;

class RelationFieldOption implements CollectionFieldOption
{
    public function __construct(
        public string $collection,
        public bool $cascadeDelete,
        public bool $multiple,
        public int|string|null $minSelect,
        public int|string|null $maxSelect,
    ) {
    }

    public function toArray(): array
    {
        return [
            'collection'    => $this->collection,
            'multiple'      => $this->multiple,
            'cascadeDelete' => $this->cascadeDelete,
            'minSelect'     => $this->minSelect,
            'maxSelect'     => $this->maxSelect,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            collection: $data['collection'] ?? '',
            multiple: $data['multiple'] ?? false,
            cascadeDelete: $data['cascadeDelete'] ?? false,
            minSelect: $data['minSelect'] ?? null,
            maxSelect: $data['maxSelect'] ?? null,
        );
    }

    public function validate(): bool
    {
        return true;
    }

    public function getValidationRules(): array
    {
        return [
            'collection'    => 'required|string|in:' . Collection::pluck('id')->implode(','),
            'multiple'      => 'boolean',
            'cascadeDelete' => 'boolean',
            'minSelect'     => ['nullable', 'integer'],
            'maxSelect'     => ['nullable', 'integer'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
