<?php

namespace Tests\Feature;

use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\Models\CollectionField;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordApiTest extends TestCase
{
    use RefreshDatabase;

    protected $project;

    protected $collection;

    protected $userCollection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create(['name' => 'Test Project']);

        // Create posts collection
        $this->collection = Collection::create([
            'project_id' => $this->project->id,
            'name'       => 'posts',
            'type'       => CollectionType::Base,
            'api_rules'  => [
                'list'   => '',
                'view'   => '',
                'create' => '',
                'update' => '@request.auth.id = user_id',
                'delete' => '@request.auth.id = user_id',
            ],
        ]);

        // Add fields to posts
        CollectionField::create([
            'collection_id' => $this->collection->id,
            'name'          => 'title',
            'type'          => FieldType::Text,
            'order'         => 1,
            'options'       => [],
        ]);
        CollectionField::create([
            'collection_id' => $this->collection->id,
            'name'          => 'content',
            'type'          => FieldType::Text,
            'order'         => 2,
            'options'       => [],
        ]);
        CollectionField::create([
            'collection_id' => $this->collection->id,
            'name'          => 'user_id',
            'type'          => FieldType::Text,
            'order'         => 3,
            'options'       => [],
        ]);

        // Create users collection for auth
        $this->userCollection = Collection::create([
            'project_id' => $this->project->id,
            'name'       => 'users',
            'type'       => CollectionType::Auth,
            'api_rules'  => [
                'authenticate' => '',
                'list'         => '',
                'view'         => '@request.auth.id = id',
                'update'       => '@request.auth.id = id',
                'delete'       => '@request.auth.id = id',
            ],
        ]);

        // Auth fields are created by booted() if not present, but let's be explicit if needed.
        // Actually booted() handles it? No, booted() handles api_rules and options defaults.
        // Handlers usually handle fields.
    }

    public function test_can_list_posts()
    {
        Record::create([
            'collection_id' => $this->collection->id,
            'data'          => ['title' => 'Post 1', 'content' => 'Content 1', 'user_id' => 'user1'],
        ]);

        $response = $this->getJson('/api/collections/posts/records');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_create_post()
    {
        $response = $this->postJson('/api/collections/posts/records', [
            'title'   => 'New Post',
            'content' => 'New Content',
            'user_id' => 'user1',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('records', [
            'collection_id' => $this->collection->id,
        ]);
    }

    public function test_can_view_post()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => ['title' => 'Post 1', 'content' => 'Content 1', 'user_id' => 'user1'],
        ]);

        $response = $this->getJson("/api/collections/posts/records/{$record->documentId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Post 1');
    }

    public function test_cannot_update_post_if_not_owner()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => ['title' => 'Post 1', 'content' => 'Content 1', 'user_id' => 'user1'],
        ]);

        // No auth header = no owner
        $response = $this->putJson("/api/collections/posts/records/{$record->documentId}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_post_if_not_owner()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => ['title' => 'Post 1', 'content' => 'Content 1', 'user_id' => 'user1'],
        ]);

        $response = $this->deleteJson("/api/collections/posts/records/{$record->documentId}");

        $response->assertStatus(403);
    }
}
