<?php

namespace App\Domain\Field\FieldOptions;

use App\Domain\Field\Contracts\CollectionFieldOption;

class FileFieldOption implements CollectionFieldOption
{
    public function __construct(
        public array $allowedMimeTypes = [], // e.g., ['image/jpeg', 'image/png']
        public ?int $maxSize = 10485760, // 10 MB in bytes
        public ?int $minSize = 0,
        public bool $multiple = false,
        public ?int $maxFiles = null, // if multiple is true
        public bool $generateThumbnail = false,
        public ?array $thumbnailSizes = null, // e.g., ['small' => [150, 150], 'medium' => [300, 300]]
    ) {
    }

    public function toArray(): array
    {
        return [
            'allowedMimeTypes'  => $this->allowedMimeTypes,
            'maxSize'           => $this->maxSize,
            'minSize'           => $this->minSize,
            'multiple'          => $this->multiple,
            'maxFiles'          => $this->maxFiles,
            'generateThumbnail' => $this->generateThumbnail,
            'thumbnailSizes'    => $this->thumbnailSizes,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            allowedMimeTypes: $data['allowedMimeTypes'] ?? [],
            maxSize: $data['maxSize'] ?? 10485760, // 10 MB in bytes
            minSize: $data['minSize'] ?? 0,
            multiple: $data['multiple'] ?? false,
            maxFiles: $data['maxFiles'] ?? null,
            generateThumbnail: $data['generateThumbnail'] ?? false,
            thumbnailSizes: $data['thumbnailSizes'] ?? null,
        );
    }

    public function validate(): bool
    {
        return true;
    }

    public function getValidationRules(): array
    {
        return [
            'allowedMimeTypes'   => ['array'],
            'allowedMimeTypes.*' => ['string'],
            'maxSize'            => ['nullable', 'integer', 'min:1', 'max:' . PHP_INT_MAX],
            'minSize'            => ['nullable', 'integer', 'min:0', 'max:' . PHP_INT_MAX],
            'multiple'           => ['boolean'],
            'maxFiles'           => ['nullable', 'integer', 'min:1'],
            'generateThumbnail'  => ['boolean'],
            'thumbnailSizes'     => ['nullable', 'array'],
            'thumbnailSizes.*'   => ['array', 'size:2'],
            'thumbnailSizes.*.*' => ['integer', 'min:1'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
