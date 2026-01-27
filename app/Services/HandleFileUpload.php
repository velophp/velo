<?php

namespace App\Services;

use App\Entity\FileObject;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HandleFileUpload
{
    protected \App\Models\Collection $collection;

    protected Filesystem $storage;

    protected UploadedFile|null $fromUpload = null;
    protected string|null  $fromTmp = null;

    public function __construct()
    {
        $this->storage = Storage::disk("public");
    }

    public function setDisk(Filesystem $disk): self
    {
        $this->storage = $disk;
        return $this;
    }

    public function forCollection(\App\Models\Collection $collection): self
    {
        $this->collection = $collection;
        return $this;
    }

    public function fromUpload(UploadedFile $file): self
    {
        $this->fromUpload = $file;
        $this->fromTmp = null;
        return $this;
    }

    public function fromTmp(string $path): self
    {
        $this->fromTmp = $path;
        $this->fromUpload = null;
        return $this;
    }

    public function save(): FileObject|null
    {
        if (!$this->fromUpload && !$this->fromTmp) {
            throw new \RuntimeException('No file source provided.');
        }

        $uuid = Str::uuid()->toString();
        
        if ($this->fromUpload) {
            $extension = $this->fromUpload->getClientOriginalExtension();
            $mimeType = $this->fromUpload->getMimeType();
            $sourceContent = $this->fromUpload->get();
        } else {
            if (!Storage::disk('local')->exists($this->fromTmp)) {
                return null;
            }

            $extension = pathinfo($this->fromTmp, PATHINFO_EXTENSION);
            $mimeType = Storage::mimeType($this->fromTmp);
            $sourceContent = Storage::get($this->fromTmp);
        }

        $path = "collections/{$this->collection->id}/{$uuid}.{$extension}";
        $this->storage->put($path, $sourceContent);
        $url = "storage/".$path;

        $isPreviewable = Str::startsWith($mimeType, 'image/');

        return new FileObject(
            uuid: $uuid,
            url: $url,
            is_previewable: $isPreviewable,
            mime_type: $mimeType,
            extension: $extension
        );
    }

    /**
     * @param array<UploadedFile|string> $files
     * @return array<FileObject>
     */
    public function saveMany(array $files): array
    {
        $results = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $this->fromUpload($file);
            } else {
                $this->fromTmp($file);
            }
            $results[] = $this->save();
        }
        return $results;
    }
}
