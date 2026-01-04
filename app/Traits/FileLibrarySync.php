<?php
# Source: MaryUI WithMediaSync

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Str;

trait FileLibrarySync
{
    // Remove media
    public function removeMedia(string $uuid, string $filesModelName, string $library, string $url): void
    {
        // Updates library
        $libraryCollection = data_get($this, $library);

        if ($libraryCollection instanceof \Illuminate\Support\Collection) {
            $libraryCollection = $libraryCollection->filter(fn($image) => $image['uuid'] != $uuid)->values();
            data_set($this, $library, $libraryCollection);
        }

        // Remove file from temporary storage if it exists
        $name = str($url)->after('preview-file/')->before('?expires')->toString();
        
        // Also try to extract filename from permanent storage URLs
        if (empty($name) || $name === $url) {
            $name = basename(parse_url($url, PHP_URL_PATH));
        }
        
        $files = data_get($this, $filesModelName);
        
        if (is_array($files)) {
            $filtered = [];
            foreach ($files as $key => $file) {
                if (is_object($file) && method_exists($file, 'getFilename')) {
                    if ($file->getFilename() !== $name) {
                        $filtered[] = $file;
                    }
                } else {
                    $filtered[] = $file;
                }
            }
            data_set($this, $filesModelName, $filtered);
        }
        
        // Delete from permanent storage if the file exists there
        if (str_contains($url, '/storage/')) {
            $path = str($url)->after('/storage/')->before('?')->toString();
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    // Set order
    public function refreshMediaOrder(array $order, string $library): void
    {
        $libraryCollection = data_get($this, $library);

        if ($libraryCollection instanceof \Illuminate\Support\Collection) {
            $libraryCollection = $libraryCollection->sortBy(function ($item) use ($order) {
                return array_search($item['uuid'], $order);
            });
            data_set($this, $library, $libraryCollection);
        }
    }

    // Bind temporary files with respective previews and replace existing ones, if necessary
    public function refreshMediaSources(string $filesModelName, string $library)
    {
        // New files area
        $files = data_get($this, $filesModelName);
        $newFiles = $files['*'] ?? [];

        foreach ($newFiles as $key => $file) {
            $libraryCollection = data_get($this, $library);
            
            if (!($libraryCollection instanceof \Illuminate\Support\Collection)) {
                $libraryCollection = collect($libraryCollection ?: []);
            }
            
            // Check if file is previewable (image)
            $isPreviewable = $this->isPreviewableFile($file);
            $url = $isPreviewable ? $file->temporaryUrl() : $file->getClientOriginalName();
            
            $libraryCollection = $libraryCollection->add([
                'uuid' => Str::uuid()->toString(), 
                'url' => $url,
                'is_previewable' => $isPreviewable,
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension()
            ]);
            data_set($this, $library, $libraryCollection);

            $key = $libraryCollection->keys()->last();
            
            data_set($this, "$filesModelName.$key", $file);
        }

        // Reset new files area
        $files = data_get($this, $filesModelName);
        if (isset($files['*'])) {
            unset($files['*']);
            data_set($this, $filesModelName, $files);
        }

        //Replace existing files
        $files = data_get($this, $filesModelName);
        
        if ($files) {
            foreach ($files as $key => $file) {
                $libraryCollection = data_get($this, $library);
                
                if ($libraryCollection instanceof \Illuminate\Support\Collection) {
                    $media = $libraryCollection->get($key);
                    if ($media) {
                        // Check if file is previewable (image)
                        $isPreviewable = $this->isPreviewableFile($file);
                        $media['url'] = $isPreviewable ? $file->temporaryUrl() : $file->getClientOriginalName();
                        $media['is_previewable'] = $isPreviewable;
                        $media['mime_type'] = $file->getMimeType();
                        $media['extension'] = $file->getClientOriginalExtension();
                        $libraryCollection = $libraryCollection->replace([$key => $media]);
                        data_set($this, $library, $libraryCollection);
                    }
                }
            }
        }

        $this->validateOnly($filesModelName . '.*');
    }

    // Storage files into permanent area and updates the target
    public function syncMedia(
        string $library = 'library',
        string $files = 'files',
        string $storage_subpath = '',
        ?array $existingLibrary = null,
        string $visibility = 'public',
        string $disk = 'public',
        ?Model $model = null,
        ?string $model_field = null
    ): \Illuminate\Support\Collection {
        // Store files
        $filesData = data_get($this, $files);
        
        if (is_array($filesData)) {
            foreach ($filesData as $index => $file) {
                $libraryCollection = data_get($this, $library);
                
                if (!($libraryCollection instanceof \Illuminate\Support\Collection)) {
                    $libraryCollection = collect($libraryCollection ?: []);
                }
                
                $media = $libraryCollection->get($index);
                $name = $this->getFileName($media);

                $storedPath = Storage::disk($disk)->putFileAs($storage_subpath, $file, $name, $visibility);
                $url = Storage::disk($disk)->url($storedPath);

                // Update library
                $media['url'] = $url . "?updated_at=" . time(); // cache busting mech
                $media['path'] = str($storage_subpath)->finish('/')->append($name)->toString();
                
                $libraryCollection = $libraryCollection->replace([$index => $media]);
                data_set($this, $library, $libraryCollection);
            }
        }

        // Delete removed files from library
        $libraryCollection = data_get($this, $library);
        
        if (!($libraryCollection instanceof \Illuminate\Support\Collection)) {
            $libraryCollection = collect($libraryCollection ?: []);
        }
        
        // Get existing library (from model or parameter)
        $existingData = null;
        if ($model && $model_field) {
            $existingData = $model->{$model_field};
        } elseif ($existingLibrary !== null) {
            $existingData = collect($existingLibrary);
        }
        
        $diffs = $existingData?->filter(fn($item) => $libraryCollection->doesntContain('uuid', $item['uuid'])) ?? [];

        foreach ($diffs as $diff) {
            if (isset($diff['path'])) {
                Storage::disk($disk)->delete($diff['path']);
            }
        }

        // Update model if provided
        if ($model && $model_field) {
            $model->update([$model_field => $libraryCollection]);
        }

        // Resets files
        data_set($this, $files, []);
        
        return $libraryCollection;
    }

    private function getFileName(?array $media): ?string
    {
        $name = $media['uuid'] ?? null;
        $extension = str($media['url'] ?? null)->afterLast('.')->before('?expires')->toString();

        return "$name.$extension";
    }

    private function isPreviewableFile($file): bool
    {
        if (!$file) return false;
        
        try {
            $mimeType = $file->getMimeType();
            $previewableMimes = [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
                'image/bmp',
            ];
            
            return in_array($mimeType, $previewableMimes);
        } catch (\Exception $e) {
            // If we can't determine mime type, check extension as fallback
            try {
                $extension = strtolower($file->getClientOriginalExtension());
                return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']);
            } catch (\Exception $e) {
                return false;
            }
        }
    }
}
