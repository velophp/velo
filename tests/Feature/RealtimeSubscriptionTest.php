<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Project;
use App\Models\RealtimeConnection;
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
            'name' => 'posts', 
            'type' => \App\Enums\CollectionType::Base
        ]);

        $response = $this->postJson(route('realtime.subscribe'), [
            'collection_id' => $collection->id,
            'filters' => ['status' => 'active'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['channel_name']);

        $channelName = $response->json('channel_name');

        $this->assertDatabaseHas('realtime_connections', [
            'collection_id' => $collection->id,
            'channel_name' => $channelName,
            'filters' => json_encode(['status' => 'active']),
        ]);
    }

    public function test_can_ping_connection()
    {
        $project = Project::create(['name' => 'Test Project']);
        $collection = Collection::create([
            'project_id' => $project->id, 
            'name' => 'posts', 
            'type' => \App\Enums\CollectionType::Base
        ]);
        
        // Seed connection
        $connection = RealtimeConnection::create([
            'project_id' => $project->id,
            'collection_id' => $collection->id,
            'channel_name' => 'test-uuid',
            'last_seen_at' => now()->subMinutes(10),
        ]);

        $response = $this->postJson(route('realtime.ping'), [
            'channel_name' => 'test-uuid',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('realtime_connections', [
            'id' => $connection->id,
            'last_seen_at' => now(), // Should be approximately now
        ]);
        
        $connection->refresh();
        $this->assertTrue($connection->last_seen_at->gt(now()->subMinute()));
    }
}
