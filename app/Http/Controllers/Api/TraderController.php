<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class TraderController extends Controller
{
    private const HL_API          = 'https://api.hyperliquid.xyz/info';
    private const HL_STATS_API    = 'https://stats-data.hyperliquid.xyz/Mainnet';
    private const TIMEOUT_S  = 10;

    public function leaderboard(): JsonResponse
    {
        ini_set('memory_limit', '512M');

        try {
            $response = Http::timeout(self::TIMEOUT_S)
                ->get(self::HL_STATS_API . '/leaderboard');

            if ($response->failed()) {
                return response()->json(['error' => 'API Hyperliquid indisponible'], 503);
            }

            $limit  = min(50, max(1, (int) request('limit', 20)));
            $window = in_array(request('window', 'day'), ['day', 'week', 'month', 'allTime'], true)
                ? request('window', 'day')
                : 'day';

            $rows = collect($response->json('leaderboardRows') ?? [])
                ->map(function ($row) use ($window) {
                    // windowPerformances = [["day", {pnl,roi,vlm}], ["week", ...], ...]
                    $entry = collect($row['windowPerformances'] ?? [])->firstWhere(0, $window);
                    $stats = $entry[1] ?? [];

                    return [
                        'address'       => $row['ethAddress'] ?? '',
                        'account_value' => (float) ($row['accountValue'] ?? 0),
                        'pnl'           => (float) ($stats['pnl'] ?? 0),
                        'roi'           => (float) ($stats['roi'] ?? 0),
                        'volume'        => (float) ($stats['vlm'] ?? 0),
                    ];
                })
                ->take($limit)
                ->values();

            return response()->json(['data' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Impossible de contacter l\'API Hyperliquid : ' . $e->getMessage()], 503);
        }
    }

    public function portfolioHistory(string $address): JsonResponse
    {
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            return response()->json(['error' => 'Adresse invalide'], 422);
        }

        try {
            $response = Http::timeout(self::TIMEOUT_S)
                ->post(self::HL_API, ['type' => 'portfolio', 'user' => $address]);

            if ($response->failed()) {
                return response()->json(['error' => 'API Hyperliquid indisponible'], 503);
            }

            // HL returns: [["day", {"accountValueHistory":[[ts,val],...], "pnlHistory":[[ts,pnl],...]}], ...]
            $data = collect($response->json() ?? [])
                ->mapWithKeys(function ($item) {
                    [$period, $history] = $item;
                    return [$period => [
                        'account_value' => collect($history['accountValueHistory'] ?? [])
                            ->map(fn ($p) => ['t' => (int) $p[0], 'v' => (float) $p[1]])
                            ->values(),
                        'pnl' => collect($history['pnlHistory'] ?? [])
                            ->map(fn ($p) => ['t' => (int) $p[0], 'v' => (float) $p[1]])
                            ->values(),
                    ]];
                });

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Impossible de contacter l\'API Hyperliquid : ' . $e->getMessage()], 503);
        }
    }

    public function show(string $address): JsonResponse
    {
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            return response()->json(['error' => 'Adresse invalide (format 0x + 40 caractères hex attendu)'], 422);
        }

        try {
            [$state, $fills] = $this->fetchAll($address);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Impossible de contacter l\'API Hyperliquid : ' . $e->getMessage()], 503);
        }

        $page    = max(1, (int) request('page', 1));
        $perPage = min(100, max(10, (int) request('per_page', 20)));

        $parsedFills = $this->parseFills($fills);
        $total       = count($parsedFills);
        $paginated   = array_slice($parsedFills, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'address'   => $address,
            'portfolio' => $this->parsePortfolio($state),
            'positions' => $this->parsePositions($state),
            'fills'     => [
                'data'         => $paginated,
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    private function fetchAll(string $address): array
    {
        $responses = Http::pool(fn ($pool) => [
            $pool->as('state')->timeout(self::TIMEOUT_S)
                ->post(self::HL_API, ['type' => 'clearinghouseState', 'user' => $address]),
            $pool->as('fills')->timeout(self::TIMEOUT_S)
                ->post(self::HL_API, ['type' => 'userFills', 'user' => $address]),
        ]);

        if ($responses['state']->failed()) {
            throw new \RuntimeException('clearinghouseState HTTP ' . $responses['state']->status());
        }

        return [
            $responses['state']->json() ?? [],
            $responses['fills']->successful() ? ($responses['fills']->json() ?? []) : [],
        ];
    }

    private function parsePortfolio(array $state): array
    {
        $s = $state['marginSummary'] ?? [];

        $accountValue    = (float) ($s['accountValue']    ?? 0);
        $totalMarginUsed = (float) ($s['totalMarginUsed'] ?? 0);

        return [
            'account_value'     => $accountValue,
            'total_ntl_pos'     => (float) ($s['totalNtlPos'] ?? 0),
            'total_margin_used' => $totalMarginUsed,
            'available_margin'  => $accountValue - $totalMarginUsed,
            'withdrawable'      => (float) ($state['withdrawable'] ?? 0),
        ];
    }

    private function parsePositions(array $state): array
    {
        return collect($state['assetPositions'] ?? [])
            ->map(fn ($item) => $item['position'] ?? null)
            ->filter(fn ($p) => $p !== null && (float) ($p['szi'] ?? 0) !== 0.0)
            ->map(function ($p) {
                $szi  = (float) $p['szi'];
                $size = abs($szi);

                return [
                    'coin'           => $p['coin'] ?? '',
                    'side'           => $szi > 0 ? 'long' : 'short',
                    'size'           => $size,
                    'entry_px'       => (float) ($p['entryPx'] ?? 0),
                    'unrealized_pnl' => (float) ($p['unrealizedPnl'] ?? 0),
                    'leverage'       => (int) ($p['leverage']['value'] ?? 1),
                    'liquidation_px' => isset($p['liquidationPx']) && $p['liquidationPx'] !== null
                        ? (float) $p['liquidationPx']
                        : null,
                    'position_value' => (float) ($p['positionValue'] ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    private function parseFills(array $fills): array
    {
        if (! is_array($fills) || empty($fills)) {
            return [];
        }

        return collect($fills)
            ->filter(fn ($f) => is_array($f) && isset($f['coin']))
            ->map(fn ($f) => [
                'time'     => (int) ($f['time'] ?? 0),
                'coin'     => $f['coin'] ?? '',
                'side'     => match ($f['side'] ?? '') {
                    'B'     => 'Buy',
                    'A'     => 'Sell',
                    default => $f['side'] ?? '—',
                },
                'size'     => (float) ($f['sz'] ?? 0),
                'price'    => (float) ($f['px'] ?? 0),
                'notional' => (float) ($f['sz'] ?? 0) * (float) ($f['px'] ?? 0),
                'pnl'      => (float) ($f['closedPnl'] ?? 0),
                'fee'      => (float) ($f['fee'] ?? 0),
            ])
            ->values()
            ->toArray();
    }
}
