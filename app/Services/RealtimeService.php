<?php

namespace App\Services;

use App\Events\RealtimeMessage;
use App\Http\Resources\RecordResource;
use App\Models\RealtimeConnection;
use App\Models\Record;

class RealtimeService
{
    public function __construct(
        protected FilterMatchingService $filterMatcher
    ) {
    }

    public function dispatchUpdates(Record $record, string $action): void
    {
        RealtimeConnection::query()
            ->where('collection_id', $record->collection_id)
            ->chunk(500, function ($connections) use ($record, $action) {
                foreach ($connections as $connection) {
                    if ($this->filterMatcher->match($record, $connection->filter)) {
                        RealtimeMessage::dispatch($connection->channel_name, [
                            'action' => $action,
                            'record' => (new RecordResource($record))->resolve()
                        ]);
                    }
                }
            });
    }
}
