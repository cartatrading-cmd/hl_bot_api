<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotConfig;
use App\Models\BotLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotLogController extends Controller
{
    /**
     * Ingest a batch of log entries sent by the Python bot.
     * Authenticated via Bearer token (BOT_API_TOKEN).
     * POST /api/bot/logs
     */
    public function ingest(Request $request): JsonResponse
    {
        $token = config('app.bot_api_token');

        if (! $token || $request->bearerToken() !== $token) {
            \Log::warning('bot_logs.ingest_unauthorized', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'bot_config_id' => ['required', 'integer', 'exists:bot_configs,id'],
            'logs'          => ['required', 'array', 'max:500'],
            'logs.*.level'     => ['required', 'string'],
            'logs.*.event'     => ['required', 'string'],
            'logs.*.timestamp' => ['required', 'string'],
            'logs.*.context'   => ['nullable', 'array'],
        ]);

        $rows = array_map(fn ($entry) => [
            'bot_config_id' => $validated['bot_config_id'],
            'level'         => $entry['level'],
            'event'         => $entry['event'],
            'context'       => isset($entry['context']) ? json_encode($entry['context']) : null,
            'logged_at'     => $entry['timestamp'],
        ], $validated['logs']);

        BotLog::insert($rows);

        return response()->json(['inserted' => count($rows)], 201);
    }

    /**
     * Fetch paginated logs for a specific bot config.
     * GET /api/bot-configs/{bot_config}/logs
     */
    public function index(Request $request, BotConfig $botConfig): JsonResponse
    {
        $query = BotLog::where('bot_config_id', $botConfig->id)
            ->orderByDesc('logged_at');

        if ($request->has('level')) {
            $query->where('level', $request->string('level'));
        }

        if ($request->has('search')) {
            $query->where('event', 'like', '%' . $request->string('search') . '%');
        }

        $logs = $query->paginate($request->integer('per_page', 100));

        return response()->json($logs);
    }

    /**
     * Delete all logs for a bot config.
     * DELETE /api/bot-configs/{bot_config}/logs
     */
    public function destroy(BotConfig $botConfig): JsonResponse
    {
        BotLog::where('bot_config_id', $botConfig->id)->delete();

        return response()->json(null, 204);
    }
}
