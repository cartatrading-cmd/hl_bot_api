<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BotConfig;
use App\Models\BotState;
use App\Models\BotStateAudit;
use Illuminate\Support\Facades\Http;

class BotStateService
{
    /**
     * Get the latest state snapshot for a given bot config.
     */
    public function getSnapshot(BotConfig $botConfig): array
    {
        $state = BotState::where('bot_config_id', $botConfig->id)->first();

        if (! $state) {
            return $this->emptySnapshot();
        }

        return [
            'timestamp'        => $state->snapshot_at?->getTimestampMs(),
            'master_positions' => $state->master_positions ?? [],
            'vault_positions'  => $state->vault_positions ?? [],
            'owned_coins'      => $state->owned_coins ?? [],
            'updated_at'       => $state->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the audit history for a given bot config (newest first).
     */
    public function getAuditHistory(BotConfig $botConfig, int $limit = 50): array
    {
        return BotStateAudit::where('bot_config_id', $botConfig->id)
            ->orderByDesc('created_at')
            ->limit(min($limit, 200))
            ->get(['snapshot_at', 'master_positions', 'vault_positions', 'owned_coins', 'created_at'])
            ->toArray();
    }

    /**
     * Fetch on-chain equity for a given vault/wallet address via Hyperliquid API.
     */
    public function getEquity(string $address): array
    {
        try {
            $response = Http::post(
                'https://api.hyperliquid.xyz/info',
                ['type' => 'clearinghouseState', 'user' => $address]
            );

            $data    = $response->json();
            $summary = $data['marginSummary'] ?? [];

            return [
                'account_value'     => (float) ($summary['accountValue'] ?? 0),
                'total_margin_used' => (float) ($summary['totalMarginUsed'] ?? 0),
                'available_margin'  => (float) ($summary['accountValue'] ?? 0) - (float) ($summary['totalMarginUsed'] ?? 0),
                'withdrawable'      => (float) ($data['withdrawable'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['account_value' => 0, 'total_margin_used' => 0, 'available_margin' => 0, 'withdrawable' => 0];
        }
    }

    private function emptySnapshot(): array
    {
        return [
            'timestamp'        => null,
            'master_positions' => [],
            'vault_positions'  => [],
            'owned_coins'      => [],
            'updated_at'       => null,
        ];
    }
}
