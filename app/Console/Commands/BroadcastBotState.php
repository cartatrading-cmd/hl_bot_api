<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\BotStateUpdated;
use App\Services\BotStateService;
use Illuminate\Console\Command;

class BroadcastBotState extends Command
{
    protected $signature   = 'bot:broadcast';
    protected $description = 'Broadcast bot state to WebSocket clients every second';

    public function handle(BotStateService $botState): void
    {
        $this->info('Broadcasting bot state — Ctrl+C to stop');

        while (true) {
            $state = $botState->getSnapshot();
            BotStateUpdated::dispatch($state);
            sleep(1);
        }
    }
}
