<?php

use App\Delivery\Models\RealtimeConnection;
use Illuminate\Support\Facades\Broadcast;

$prefix = config('velo.realtime_channel_prefix');
Broadcast::channel($prefix . '{channelName}', function ($user, $channelName) {
    \Log::info('Realtime connect.', [
        'user'        => $user,
        'channelName' => $channelName,
    ]);

    return RealtimeConnection::where('channel_name', $channelName)
        ->where('record_id', $user->meta?->_id)
        ->exists();
});
