<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $channelName,
        public array $payload
    ) {}

    public function broadcastOn(): array
    {
        $prefix = config('larabase.realtime_channel_prefix');
        
        return [
            new Channel($prefix.$this->channelName),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastAs()
    {
        return 'server.message';
    }
}
