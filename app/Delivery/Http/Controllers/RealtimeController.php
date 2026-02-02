<?php

namespace App\Delivery\Http\Controllers;

use App\Delivery\Models\RealtimeConnection;
use App\Delivery\Services\EvaluateRuleExpression;
use App\Domain\Collection\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RealtimeController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'collection' => 'required|string',
            'filter'     => 'nullable|string',
            'socket_id'  => 'nullable|string',
        ]);

        $collection = Collection::where('name', $validated['collection'])->orWhere('id', $validated['collection'])->first();
        if (! $collection) {
            throw ValidationException::withMessages([
                'collection' => 'Collection not found',
            ]);
        }

        $listRule = $collection->api_rules['list'] ?? 'SUPERUSER_ONLY';
        $allowPublic = app(EvaluateRuleExpression::class)
            ->forExpression($listRule)
            ->allowsGuest();

        if (! $allowPublic && ! $request->user()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $filter = $validated['filter'] ?? '';
        $channelName = (string) Str::uuid();
        $recordId = $request->user()?->meta?->_id;

        RealtimeConnection::create([
            'project_id'    => $collection->project_id,
            'collection_id' => $collection->id,
            'record_id'     => $recordId,
            'socket_id'     => $validated['socket_id'] ?? null,
            'channel_name'  => $channelName,
            'filter'        => $filter,
            'is_public'     => $allowPublic,
            'last_seen_at'  => now(),
        ]);

        // Hook: realtime.connecting
        \App\Domain\Hooks\Facades\Hooks::trigger('realtime.connecting', [
            'collection'   => $collection,
            'record_id'    => $recordId,
            'channel_name' => $channelName,
            'filter'       => $filter,
            'socket_id'    => $validated['socket_id'] ?? null,
        ]);

        $prefix = config('velo.realtime_channel_prefix');
        $channelName = $prefix . $channelName;

        return response()->json([
            'channel_name' => $channelName,
            'is_public'    => $allowPublic,
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
