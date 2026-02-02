<?php

namespace Tests\Feature;

use App\Delivery\Entity\SafeCollection;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\Models\CollectionField;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HooksTest extends TestCase
{
    use RefreshDatabase;

    protected $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $project = Project::create(['name' => 'Test Project']);

        $collection = Collection::create([
            'project_id' => $project->id,
            'name'       => 'posts',
            'type'       => \App\Domain\Collection\Enums\CollectionType::Base,
            'api_rules'  => [
                'list'   => '',
                'view'   => '',
                'create' => '',
                'update' => '',
                'delete' => '',
            ],
        ]);

        $this->collection = $collection;

        $collection->fields()->createMany(CollectionField::createBaseFrom([
            [
                'name'    => 'title',
                'type'    => FieldType::Text,
                'options' => [],
            ],
            [
                'name'    => 'status',
                'type'    => FieldType::Text,
                'options' => [],
            ],
        ]));
    }

    public function test_record_creating_hook()
    {
        \App\Domain\Hooks\Facades\Hooks::on('record.creating', function ($data, $context) {
            $data['slug'] = 'slug-' . $data['title'];

            return $data;
        });

        $response = $this->postJson("/api/collections/{$this->collection->name}/records", [
            'title' => 'Hello World',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('records', [
            'collection_id' => $this->collection->id,
        ]);

        $record = Record::first();
        $this->assertEquals('slug-Hello World', $record->data['slug']);
    }

    public function test_record_created_hook()
    {
        $triggered = false;
        \App\Domain\Hooks\Facades\Hooks::on('record.created', function ($context) use (&$triggered) {
            $triggered = true;
            $this->assertEquals('Hello World', $context['record']['title']);
        });

        $response = $this->postJson("/api/collections/{$this->collection->name}/records", [
            'title' => 'Hello World',
        ]);

        if ($response->status() !== 200) {
            $response->dump();
        }

        $this->assertTrue($triggered);
    }

    public function test_record_retrieved_hook()
    {
        $record = $this->collection->recordRelation()->create([
            'data' => new SafeCollection(['title' => 'Hello World']),
        ]);

        \App\Domain\Hooks\Facades\Hooks::on('record.retrieved', function ($data, $context) {
            $data['title'] = 'Modified Title';

            return $data;
        });

        $response = $this->getJson("/api/collections/{$this->collection->name}/records/{$record->data->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Modified Title');
    }

    public function test_record_updating_hook()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => ['title' => 'Original'],
        ]);

        \App\Domain\Hooks\Facades\Hooks::on('record.updating', function ($data, $context) {
            $data['slug'] = 'updated-slug';

            return $data;
        });

        $response = $this->putJson("/api/collections/{$this->collection->name}/records/{$record->data->id}", [
            'title' => 'Updated',
        ]);

        $response->assertStatus(200);

        $record->refresh();
        $this->assertEquals('updated-slug', $record->data['slug']);
    }

    public function test_record_deleting_hook()
    {
        $record = $this->collection->recordRelation()->create([
            'data' => new SafeCollection(['title' => 'To Delete']),
        ]);

        $triggered = false;
        \App\Domain\Hooks\Facades\Hooks::on('record.deleting', function ($context) use (&$triggered) {
            $triggered = true;
        });

        $response = $this->deleteJson("/api/collections/{$this->collection->name}/records/{$record->data->id}");

        $this->assertTrue($triggered);
    }

    public function test_realtime_connecting_hook()
    {
        $triggered = false;
        \App\Domain\Hooks\Facades\Hooks::on('realtime.connecting', function ($context) use (&$triggered) {
            $triggered = true;
            $this->assertEquals('posts', $context['collection']->name);
        });

        $response = $this->postJson('/api/realtime/subscribe', [
            'collection' => 'posts',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($triggered);
    }
}
