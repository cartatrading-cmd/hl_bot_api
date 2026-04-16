<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBotConfigRequest;
use App\Http\Resources\BotConfigResource;
use App\Models\BotConfig;
use App\Services\BotProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BotConfigController extends Controller
{
    public function __construct(private readonly BotProcessService $process) {}

    public function index(): AnonymousResourceCollection
    {
        $configs = BotConfig::orderBy('name')->get();

        // Reconcile is_active against the real process state.
        // If the process died unexpectedly (is_active=true but PID gone), restart it.
        foreach ($configs as $config) {
            if ($config->is_active && $config->pid && ! $this->process->isRunning($config->pid)) {
                try {
                    $pid = $this->process->start($config);
                    $config->update(['pid' => $pid]);
                } catch (\RuntimeException $e) {
                    // Could not restart — mark inactive so the UI shows the real state.
                    $config->update(['is_active' => false, 'pid' => null]);
                }
            }
        }

        return BotConfigResource::collection($configs->fresh());
    }

    public function store(StoreBotConfigRequest $request): BotConfigResource
    {
        $data = $request->validated();

        $config = new BotConfig($data);

        if (isset($data['private_key'])) {
            $config->private_key = $data['private_key'];
        }

        $config->save();

        return new BotConfigResource($config);
    }

    public function show(BotConfig $botConfig): BotConfigResource
    {
        return new BotConfigResource($botConfig);
    }

    public function update(StoreBotConfigRequest $request, BotConfig $botConfig): BotConfigResource
    {
        $data = $request->validated();

        if (isset($data['private_key'])) {
            $botConfig->private_key = $data['private_key'];
            unset($data['private_key']);
        }

        $botConfig->fill($data)->save();

        return new BotConfigResource($botConfig);
    }

    public function start(BotConfig $botConfig): BotConfigResource|JsonResponse
    {
        if ($botConfig->is_active && $botConfig->pid && $this->process->isRunning($botConfig->pid)) {
            return response()->json(['message' => 'Le bot est déjà actif.'], 409);
        }

        try {
            $pid = $this->process->start($botConfig);
            $botConfig->update(['is_active' => true, 'pid' => $pid]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BotConfigResource($botConfig->fresh());
    }

    public function stop(BotConfig $botConfig): BotConfigResource|JsonResponse
    {
        if (! $botConfig->is_active) {
            return response()->json(['message' => 'Le bot n\'est pas actif.'], 409);
        }

        if ($botConfig->pid) {
            $this->process->stop($botConfig->pid, $botConfig->id);
        }

        $botConfig->update(['is_active' => false, 'pid' => null]);

        return new BotConfigResource($botConfig->fresh());
    }

    public function emergencyStop(BotConfig $botConfig): BotConfigResource|JsonResponse
    {
        if (! $botConfig->is_active) {
            return response()->json(['message' => 'Le bot n\'est pas actif.'], 409);
        }

        $this->process->emergencyStop($botConfig);
        $botConfig->update(['is_active' => false, 'pid' => null]);

        return new BotConfigResource($botConfig->fresh());
    }

    public function command(BotConfig $botConfig): JsonResponse
    {
        // Returns the command that would be run — useful for debugging.
        $reflection = new \ReflectionClass($this->process);

        $psEnv  = $reflection->getMethod('buildPsEnvBlock')->invoke($this->process, $botConfig);
        $psArgs = $reflection->getMethod('buildPsArgList')->invoke($this->process, $botConfig);
        $shell  = $reflection->getMethod('buildShellArgs')->invoke($this->process, $botConfig);

        return response()->json([
            'windows_ps_args' => $psArgs,
            'unix_shell'      => 'python ' . $shell,
            'env_keys'        => array_keys(array_filter(
                ['MASTER_OR_VAULT_PRIVATE_KEY' => true, 'MASTER_OR_VAULT_ADDRESS' => true,
                 'NETWORK' => true, 'ENABLE_RECONCILER_DOWNSIZE' => true, 'ENABLE_RECONCILER_UPSIZE' => true, 'DRY_RUN' => true]
            )),
        ]);
    }

    public function destroy(BotConfig $botConfig): JsonResponse
    {
        // Stop the process first if running.
        if ($botConfig->is_active && $botConfig->pid) {
            $this->process->stop($botConfig->pid, $botConfig->id);
        }

        $botConfig->delete();

        return response()->json(null, 204);
    }
}
