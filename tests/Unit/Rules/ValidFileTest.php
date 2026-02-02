<?php

namespace Tests\Unit\Rules;

use App\Delivery\Rules\ValidFile;
use App\Domain\Field\FieldOptions\FileFieldOption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidFileTest extends TestCase
{
    protected function validate($value, FileFieldOption $options)
    {
        $rule = new ValidFile($options);
        $validator = Validator::make(['file' => $value], ['file' => [$rule]]);

        return $validator->passes();
    }

    public function test_validates_existing_file_array()
    {
        $options = new FileFieldOption();
        $this->assertTrue($this->validate(['uuid' => 'some-uuid', 'url' => 'some-url'], $options));
        $this->assertFalse($this->validate(['wrong' => 'structure'], $options));
    }

    public function test_validates_uploaded_file()
    {
        $options = new FileFieldOption(allowedMimeTypes: ['image/jpeg'], maxSize: 1024 * 1024); // 1MB

        $file = UploadedFile::fake()->image('test.jpg')->size(500); // 500KB
        $this->assertTrue($this->validate($file, $options));

        $fileTooBig = UploadedFile::fake()->image('big.jpg')->size(2000); // 2MB
        $this->assertFalse($this->validate($fileTooBig, $options));

        $wrongMime = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');
        $this->assertFalse($this->validate($wrongMime, $options));
    }

    public function test_validates_temp_file_path()
    {
        Storage::fake('local');

        $options = new FileFieldOption(allowedMimeTypes: ['text/plain'], maxSize: 1024);

        $path = 'tmp/test.txt';
        Storage::disk('local')->put($path, 'content');

        $this->assertTrue($this->validate($path, $options));

        // Test missing file
        $this->assertFalse($this->validate('tmp/missing.txt', $options));

        // Test wrong mime override (using image fake on disk is hard, text file is text/plain)
        $optionsStrict = new FileFieldOption(allowedMimeTypes: ['image/jpeg']);
        $this->assertFalse($this->validate($path, $optionsStrict));
    }
}
