<?php

namespace Tests\Feature;

use App\Delivery\Models\RealtimeConnection;
use App\Domain\Collection\Models\Collection;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_subscribe_to_collection()
    {
        $project = Project::create(['name' => 'Test Project']);
        $collection = Collection::create([
            'project_id' => $project->id,
            'name'       => 'posts',
            'type'       => \App\Domain\Collection\Enums\CollectionType::Base,
            'api_rules'  => ['list' => ''],
        ]);

        $response = $this->postJson(route('realtime.subscribe'), [
            'collection' => $collection->name,
            'filter'     => 'status=active',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['channel_name']);

        $this->assertDatabaseHas('realtime_connections', [
            'collection_id' => $collection->id,
            'filter'        => 'status=active',
        ]);
    }

    public function test_can_ping_connection()
    {
        $project = Project::create(['name' => 'Test Project']);
        $collection = Collection::create([
            'project_id' => $project->id,
            'name'       => 'posts',
            'type'       => \App\Domain\Collection\Enums\CollectionType::Base,
            'api_rules'  => ['list' => ''],
        ]);

        $uuid = \Illuminate\Support\Str::uuid()->toString();

        // Seed connection
        $connection = RealtimeConnection::create([
            'project_id'    => $project->id,
            'collection_id' => $collection->id,
            'channel_name'  => $uuid,
            'last_seen_at'  => now()->subMinutes(10),
        ]);

        $response = $this->postJson(route('realtime.ping'), [
            'channel_name' => $connection->channel_name,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('realtime_connections', [
            'id'           => $connection->id,
            'last_seen_at' => now(), // Should be approximately now
        ]);

        $connection->refresh();
        $this->assertTrue($connection->last_seen_at->gt(now()->subMinute()));
    }
}
