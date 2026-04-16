<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotState;
use App\Models\BotStateAudit;
use App\Models\BotConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BotStateController extends Controller
{
    /**
     * Ingest a state snapshot sent by the Python bot.
     * Upserts bot_states (one row per bot) and appends to bot_state_audit.
     * Authenticated via Bearer token (BOT_API_TOKEN).
     * POST /api/bot/state
     */
    public function ingest(Request $request): JsonResponse
    {
        $token = config('app.bot_api_token');

        if (! $token || $request->bearerToken() !== $token) {
            \Log::warning('bot_state.ingest_unauthorized', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'bot_config_id'    => ['required', 'integer', 'exists:bot_configs,id'],
            'timestamp'        => ['required', 'integer'],
            'master_positions' => ['required', 'array'],
            'vault_positions'  => ['required', 'array'],
            'owned_coins'      => ['required', 'array'],
        ]);

        $snapshotAt = \Carbon\Carbon::createFromTimestampMs($validated['timestamp']);

        $payload = [
            'master_positions' => $validated['master_positions'],
            'vault_positions'  => $validated['vault_positions'],
            'owned_coins'      => $validated['owned_coins'],
            'snapshot_at'      => $snapshotAt,
        ];

        DB::transaction(function () use ($validated, $payload) {
            // Upsert: one row per bot, always up-to-date
            BotState::updateOrCreate(
                ['bot_config_id' => $validated['bot_config_id']],
                $payload
            );

            // Append-only audit trail
            BotStateAudit::create(array_merge(
                ['bot_config_id' => $validated['bot_config_id']],
                $payload
            ));
        });

        return response()->json(['ok' => true], 201);
    }

    /**
     * Get the latest state snapshot for a bot config.
     * GET /api/bot-configs/{bot_config}/state
     */
    public function show(BotConfig $botConfig): JsonResponse
    {
        $state = BotState::where('bot_config_id', $botConfig->id)->first();

        if (! $state) {
            return response()->json([
                'timestamp'        => null,
                'master_positions' => [],
                'vault_positions'  => [],
                'owned_coins'      => [],
            ]);
        }

        return response()->json([
            'timestamp'        => $state->snapshot_at?->getTimestampMs(),
            'master_positions' => $state->master_positions ?? [],
            'vault_positions'  => $state->vault_positions ?? [],
            'owned_coins'      => $state->owned_coins ?? [],
            'updated_at'       => $state->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Get the audit history for a bot config (paginated, newest first).
     * GET /api/bot-configs/{bot_config}/state/history
     */
    public function history(Request $request, BotConfig $botConfig): JsonResponse
    {
        $limit = $request->integer('limit', 50);

        $entries = BotStateAudit::where('bot_config_id', $botConfig->id)
            ->orderByDesc('created_at')
            ->limit(min($limit, 200))
            ->get(['snapshot_at', 'master_positions', 'vault_positions', 'owned_coins', 'created_at']);

        return response()->json($entries);
    }
}
