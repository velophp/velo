<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandleFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_from_uploaded_file(): void
    {
        Storage::fake('public');

        $user = \App\Delivery\Models\User::factory()->create();
        $collection = \App\Domain\Collection\Models\Collection::create([
            'name'       => 'Test Collection',
            'type'       => \App\Domain\Collection\Enums\CollectionType::Base,
            'project_id' => \App\Domain\Project\Models\Project::create(['name' => 'P'])->id,
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $service = new \App\Delivery\Services\HandleFileUpload();
        $fileObject = $service->forCollection($collection)
            ->fromUpload($file)
            ->save();

        $this->assertInstanceOf(\App\Delivery\Entity\FileObject::class, $fileObject);
        $this->assertTrue(Storage::disk('public')->exists("collections/{$collection->id}/{$fileObject->uuid}.jpg"));
        $this->assertEquals('image/jpeg', $fileObject->mime_type);
        $this->assertEquals('jpg', $fileObject->extension);
        $this->assertTrue($fileObject->is_previewable);
    }
}
