<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\RealtimeConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RealtimeController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'collection' => 'required|string',
            'filter' => 'nullable|string',
            'socket_id' => 'nullable|string',
        ]);

        $collection = Collection::where('name', $validated['collection'])->orWhere('id', $validated['collection'])->first();
        if (!$collection) throw ValidationException::withMessages([
            'collection' => 'Collection not found',
        ]);

        // @TODO implement authorizatio check

        $channelName = (string) Str::uuid();

        $recordId = null;

        RealtimeConnection::create([
            'project_id' => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id' => $recordId,
            'socket_id' => $validated['socket_id'] ?? null,
            'channel_name' => $channelName,
            'filter' => $validated['filter'],
            'last_seen_at' => now(),
        ]);

        $prefix = config('larabase.realtime_channel_prefix');

        return response()->json([
            'channel_name' => $prefix.$channelName,
        ]);
    }

    public function ping(Request $request)
    {
        $validated = $request->validate([
            'channel_name' => 'required|uuid',
        ]);

        RealtimeConnection::where('channel_name', $validated['channel_name'])
            ->update(['last_seen_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
