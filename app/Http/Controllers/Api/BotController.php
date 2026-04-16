<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotConfig;
use App\Services\BotStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotController extends Controller
{
    public function __construct(private BotStateService $botState) {}

    /**
     * Current snapshot for a bot config: master + vault positions + equity.
     * GET /api/bot-configs/{bot_config}/snapshot
     */
    public function snapshot(BotConfig $botConfig): JsonResponse
    {
        $snapshot = $this->botState->getSnapshot($botConfig);
        $equity   = $this->botState->getEquity($botConfig->vault_address);

        return response()->json(array_merge($snapshot, ['equity' => $equity]));
    }

    /**
     * Wallet equity data for a bot config's vault address.
     * GET /api/bot-configs/{bot_config}/wallet
     */
    public function wallet(BotConfig $botConfig): JsonResponse
    {
        return app(TraderController::class)->show($botConfig->vault_address);
    }

    /**
     * Last N audit events for a bot config.
     * GET /api/bot-configs/{bot_config}/history
     */
    public function history(Request $request, BotConfig $botConfig): JsonResponse
    {
        $limit  = min($request->integer('limit', 50), 200);
        $events = $this->botState->getAuditHistory($botConfig, $limit);

        return response()->json(['data' => $events]);
    }
}
