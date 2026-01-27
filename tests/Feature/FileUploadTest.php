<?php

namespace Tests\Feature;

use App\Enums\CollectionType;
use App\Models\Collection;
use App\Models\Record;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Enums\FieldType;
use App\Models\CollectionField;
use App\Models\Project;
use App\Models\User;

class FileUploadTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    
    public function test_file_upload_process_and_persistence()
    {
        Storage::fake('local');
        Storage::fake('public');

        // 1. Setup Collection and User
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'name' => 'Test'
        ]);

        $collection = Collection::create([
            'project_id' => $project->id,
            'name' => 'File Test Collection',
            'type' => CollectionType::Base,
        ]);

        $collection->fields()->createMany(CollectionField::createBaseFrom([
            [
                'name' => 'document',
                'type' => FieldType::File,
                'order' => 1,
                'required' => false,
                'options' => []
            ]
        ]));

        // 2. Test Temporary Upload (FileUploadController)
        $file = UploadedFile::fake()->create('test-document.pdf', 100);
        
        $response = $this->postJson(route('uploads.process'), [
            'document' => $file
        ]);

        $response->assertStatus(200);
        $tempPath = $response->content(); // The controller returns the path string

        Storage::disk('local')->assertExists($tempPath);

        // 3. Test Persistence (BaseCollectionHandler via Record creation)
        $recordData = [
            'collection_id' => $collection->id,
            'data' => [
                'document' => [$tempPath] // Frontend sends array of temp paths
            ]
        ];

        $record = Record::create($recordData);
        $recordId = $record->documentId;

        // Verify file was moved
        // $expectedPath = "collections/{$collection->id}/{$recordId}/test-document.pdf";
        
        $files = $record->fresh()->data->get('document');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);

        $fileObject = $files[0];
        // It's serialized to array in DB
        $this->assertIsArray($fileObject);
        $this->assertArrayHasKey('uuid', $fileObject);
        $this->assertArrayHasKey('url', $fileObject);

        $expectedPath = "collections/{$collection->id}/{$fileObject['uuid']}.pdf";
        
        // Ensure the file is actually there using Storage facade which works with faked storage
        Storage::disk('public')->assertExists($expectedPath);
    }
}
