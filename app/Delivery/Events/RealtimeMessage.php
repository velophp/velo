<?php

namespace App\Delivery\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeMessage implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $channelName,
        public array $payload,
        public bool $isPublic = false,
    ) {
    }

    public function broadcastOn(): array
    {
        $prefix = config('velo.realtime_channel_prefix');
        $channelName = $prefix . $this->channelName;

        return [
            $this->isPublic ? new Channel($channelName) : new PrivateChannel($channelName),
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
