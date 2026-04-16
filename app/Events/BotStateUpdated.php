<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BotStateUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $state) {}

    public function broadcastOn(): array
    {
        return [new Channel('bot-state')];
    }

    public function broadcastAs(): string
    {
        return 'state.updated';
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }
}
