<?php

namespace Tests\Feature;

use App\Delivery\Events\RealtimeMessage;
use App\Delivery\Models\RealtimeConnection;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\Models\CollectionField;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_event_to_subscriber_with_matching_filter()
    {
        Event::fake([RealtimeMessage::class]);

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

        // 1. Subscribe to 'status=active'
        $connection = RealtimeConnection::create([
            'project_id'    => $project->id,
            'collection_id' => $collection->id,
            'channel_name'  => 'uuid-active-sub',
            'filter'        => 'status = active',
            'last_seen_at'  => now(),
        ]);

        // 2. Create a matching record
        $record = new Record();
        $record->collection_id = $collection->id;
        $record->data = collect(['status' => 'active', 'title' => 'Hello World']);
        $record->save();

        // 3. Assert Event Dispatched
        Event::assertDispatched(RealtimeMessage::class, function ($event) use ($connection) {
            return $event->channelName === $connection->channel_name
                && $event->payload['action'] === 'created'
                && $event->payload['record']['status'] === 'active';
        });
    }

    public function test_does_not_dispatch_event_if_filter_mismatch()
    {
        Event::fake([RealtimeMessage::class]);

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

        // 1. Subscribe to 'status=active'
        // 1. Subscribe to 'status=active'
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        $connection = RealtimeConnection::create([
            'project_id'    => $project->id,
            'collection_id' => $collection->id,
            'channel_name'  => $uuid,
            'filter'        => 'status = active',
            'last_seen_at'  => now(),
        ]);

        // 2. Create a NON-matching record (status=draft)
        $record = new Record();
        $record->collection_id = $collection->id;
        $record->data = collect(['status' => 'draft', 'title' => 'WIP']);
        $record->save();

        // 3. Assert Event NOT Dispatched
        Event::assertNotDispatched(RealtimeMessage::class);
    }
}
