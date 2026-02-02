<?php

namespace App\Delivery\Entity;

class FileObject implements \JsonSerializable
{
    public function __construct(
        public string $uuid,
        public string $url,
        public bool $is_previewable,
        public string $mime_type,
        public string $extension,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['uuid'],
            $data['url'],
            $data['is_previewable'],
            $data['mime_type'],
            $data['extension'],
        );
    }

    public function toArray(): array
    {
        return [
            'uuid'           => $this->uuid,
            'url'            => $this->url,
            'is_previewable' => $this->is_previewable,
            'mime_type'      => $this->mime_type,
            'extension'      => $this->extension,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
