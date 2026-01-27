<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandleFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_from_uploaded_file(): void
    {
        Storage::fake('public');
        
        $user = \App\Models\User::factory()->create();
        $collection = \App\Models\Collection::create([
            'name' => 'Test Collection',
            'type' => \App\Enums\CollectionType::Base,
            'project_id' => \App\Models\Project::create(['name' => 'P'])->id
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $service = new \App\Services\HandleFileUpload();
        $fileObject = $service->forCollection($collection)
            ->fromUpload($file)
            ->save();

        $this->assertInstanceOf(\App\Entity\FileObject::class, $fileObject);
        $this->assertTrue(Storage::disk('public')->exists("collections/{$collection->id}/{$fileObject->uuid}.jpg"));
        $this->assertEquals('image/jpeg', $fileObject->mime_type);
        $this->assertEquals('jpg', $fileObject->extension);
        $this->assertTrue($fileObject->is_previewable);
    }

    public function test_can_save_from_tmp_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $collection = \App\Models\Collection::create([
            'name' => 'Test Collection',
            'type' => \App\Enums\CollectionType::Base,
            'project_id' => \App\Models\Project::create(['name' => 'P'])->id
        ]);

        $tmpPath = 'tmp/test.txt';
        Storage::disk('local')->put($tmpPath, 'content');

        $service = new \App\Services\HandleFileUpload();
        $fileObject = $service->forCollection($collection)
            ->fromTmp($tmpPath)
            ->save();

        $this->assertInstanceOf(\App\Entity\FileObject::class, $fileObject);
        $this->assertTrue(Storage::disk('public')->exists("collections/{$collection->id}/{$fileObject->uuid}.txt"));
        $this->assertFalse($fileObject->is_previewable); // txt is not previewable by default logic
    }

    public function test_can_save_many(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $collection = \App\Models\Collection::create([
            'name' => 'Test Collection',
            'type' => \App\Enums\CollectionType::Base,
            'project_id' => \App\Models\Project::create(['name' => 'P'])->id
        ]);

        $file1 = UploadedFile::fake()->image('img.png');
        $tmpPath = 'tmp/doc.pdf';
        Storage::disk('local')->put($tmpPath, 'pdf content');

        $service = new \App\Services\HandleFileUpload();
        $results = $service->forCollection($collection)
            ->saveMany([$file1, $tmpPath]);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(\App\Entity\FileObject::class, $results[0]);
        $this->assertInstanceOf(\App\Entity\FileObject::class, $results[1]);
        
        $this->assertEquals('png', $results[0]->extension);
        $this->assertEquals('pdf', $results[1]->extension);
    }
}
