<?php

namespace App\Delivery\Rules;

use App\Domain\Field\FieldOptions\FileFieldOption;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ValidFile implements ValidationRule
{
    public function __construct(
        protected FileFieldOption $options
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        if (is_array($value)) {
            if (isset($value['uuid'])) {
                return;
            }

            $fail('The :attribute contains invalid file data.');

            return;
        }

        if ($value instanceof UploadedFile) {
            $this->validateUploadedFile($value, $fail);

            return;
        }

        if (is_string($value)) {
            if (Str::startsWith($value, 'tmp/')) {
                $this->validateTempFile($value, $fail);

                return;
            }

            $fail('The :attribute contains an invalid file reference.');
        }
    }

    protected function validateUploadedFile(UploadedFile $file, Closure $fail): void
    {
        $rules = [];

        if (! empty($this->options->allowedMimeTypes)) {
            $rules[] = 'mimetypes:' . implode(',', $this->options->allowedMimeTypes);
        }

        if ($this->options->maxSize) {
            $rules[] = 'max:' . ceil($this->options->maxSize / 1024);
        }

        if ($this->options->minSize) {
            $rules[] = 'min:' . floor($this->options->minSize / 1024);
        }

        $validator = Validator::make(['file' => $file], ['file' => $rules]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $fail($message);
            }
        }
    }

    protected function validateTempFile(string $path, Closure $fail): void
    {
        if (! Storage::disk('local')->exists($path)) {
            $fail('The temporary file does not exist or has expired.');

            return;
        }

        if (! empty($this->options->allowedMimeTypes)) {
            $mime = Storage::disk('local')->mimeType($path);
            if (! in_array($mime, $this->options->allowedMimeTypes)) {
                $fail('The file must be a file of type: ' . implode(', ', $this->options->allowedMimeTypes) . '.');
            }
        }

        $size = Storage::disk('local')->size($path);

        if ($this->options->maxSize && $size > $this->options->maxSize) {
            $maxKb = ceil($this->options->maxSize / 1024);
            $fail("The file may not be greater than {$maxKb} kilobytes.");
        }

        if ($this->options->minSize && $size < $this->options->minSize) {
            $minKb = floor($this->options->minSize / 1024);
            $fail("The file must be at least {$minKb} kilobytes.");
        }
    }
}
