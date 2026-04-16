<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BotConfig;
use RuntimeException;

class BotProcessService
{
    private string $botDir;
    private string $pythonBin;

    public function __construct()
    {
        $this->botDir    = env('BOT_DIR', PHP_OS_FAMILY === 'Windows' ? 'C:\\hl_bot' : '/opt/hl_bot');
        $this->pythonBin = env('PYTHON_BIN', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
    }

    /**
     * Start the Python bot for the given config.
     * All settings are injected as environment variables — no CLI args needed.
     * Returns the PID of the launched process.
     */
    public function start(BotConfig $config): int
    {
        if ($config->pid && $this->isRunning($config->pid)) {
            throw new RuntimeException("Le bot est déjà en cours d'exécution (PID {$config->pid}).");
        }

        $log = storage_path("logs/bot_{$config->id}.log");

        $pid = PHP_OS_FAMILY === 'Windows'
            ? $this->startWindows($config, $log)
            : $this->startUnix($config, $log);

        if (! $pid) {
            throw new RuntimeException('Le processus Python n\'a pas démarré (PID = 0). Vérifiez que Python est dans le PATH.');
        }

        return $pid;
    }

    /**
     * Stop a running bot process.
     *
     * Windows: SIGTERM cannot be delivered to hidden processes launched via
     * Start-Process -WindowStyle Hidden.  Instead we write a sentinel file
     * that the Python bot polls every second; when detected it triggers its
     * own graceful shutdown (cancel orders, persist state).  We then wait up
     * to 10 s for the process to exit on its own before force-killing.
     *
     * Linux/macOS: standard SIGTERM — Python's signal handler runs cleanup.
     */
    public function stop(int $pid, int $botConfigId = 0): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Step 1 — write sentinel so the Python bot exits gracefully.
            if ($botConfigId) {
                $sentinel = $this->botDir . DIRECTORY_SEPARATOR . "stop_{$botConfigId}.flag";
                file_put_contents($sentinel, (string) time());
            }

            // Step 2 — wait up to 10 s for the process to exit on its own.
            $deadline = time() + 10;
            while (time() < $deadline && $this->isRunning($pid)) {
                sleep(1);
            }

            // Step 3 — force-kill if still alive.
            if ($this->isRunning($pid)) {
                exec("taskkill /F /T /PID {$pid} 2>&1");
            }
        } else {
            exec("kill -SIGTERM {$pid} 2>&1");
        }
    }

    /**
     * Emergency stop: write a sentinel that triggers vault position closure,
     * then wait up to 30 s for the bot to exit cleanly before force-killing.
     */
    public function emergencyStop(BotConfig $config): void
    {
        if ($config->pid) {
            $sentinel = $this->botDir . DIRECTORY_SEPARATOR . "emergency_{$config->id}.flag";
            file_put_contents($sentinel, (string) time());

            $deadline = time() + 30;
            while (time() < $deadline && $this->isRunning($config->pid)) {
                sleep(1);
            }

            if ($this->isRunning($config->pid)) {
                if (PHP_OS_FAMILY === 'Windows') {
                    exec("taskkill /F /T /PID {$config->pid} 2>&1");
                } else {
                    exec("kill -SIGKILL {$config->pid} 2>&1");
                }
            }
        }
    }

    public function isRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq {$pid}\" /NH 2>&1", $output);
            foreach ($output as $line) {
                if (str_contains($line, (string) $pid)) {
                    return true;
                }
            }
            return false;
        }

        return file_exists("/proc/{$pid}");
    }

    // -----------------------------------------------------------------------
    // Private — platform-specific launchers
    // -----------------------------------------------------------------------

    private function startWindows(BotConfig $config, string $log): int
    {
        $envBlock = $this->buildPsEnvBlock($config);

        $psScript = implode(' ', [
            $envBlock,
            "\$p = Start-Process",
            "-FilePath python",
            "-ArgumentList '-m','hl_bot.main'",
            "-WorkingDirectory '" . $this->botDir . "'",
            "-PassThru",
            "-WindowStyle Hidden",
            ";",
            "Write-Output \$p.Id",
        ]);

        $cmd = 'powershell -NoProfile -NonInteractive -Command "' . str_replace('"', '\\"', $psScript) . '"';

        return (int) trim((string) shell_exec($cmd));
    }

    private function startUnix(BotConfig $config, string $log): int
    {
        $envPairs = [];
        foreach ($this->buildEnvArray($config) as $key => $value) {
            $envPairs[] = sprintf('%s=%s', $key, escapeshellarg((string) $value));
        }

        $cmd = sprintf(
            '%s nohup %s -m hl_bot.main >> %s 2>&1 & echo $!',
            implode(' ', $envPairs),
            escapeshellarg($this->pythonBin),
            escapeshellarg($log),
        );

        $pid = (int) trim((string) shell_exec($cmd));

        if (! $pid) {
            throw new RuntimeException('nohup n\'a pas retourné de PID. Vérifiez que Python est dans le PATH.');
        }

        return $pid;
    }

    // -----------------------------------------------------------------------
    // Private — env builders (all config injected as env vars)
    // -----------------------------------------------------------------------

    private function buildEnvVars(BotConfig $config): array
    {
        return [
            // Wallet / credentials
            'MASTER_WALLET'              => $config->master_wallet,
            'MASTER_OR_VAULT_PRIVATE_KEY'=> $config->private_key,
            'MASTER_OR_VAULT_ADDRESS'    => $config->vault_address,
            // Network
            'NETWORK'                    => $config->network,
            // Sizing
            'USER_RATIO_MULTIPLIER'      => (string) $config->user_ratio_multiplier,
            'LEVERAGE_MULTIPLIER'        => (string) $config->leverage_multiplier,
            // Coin filters — passed as JSON arrays so pydantic-settings v2 can parse them
            // directly as list[str] without hitting its JSON-decode step on bare strings.
            'ALLOWED_COINS'              => json_encode($config->allowed_coins ?? []),
            'NOT_ALLOWED_COINS'          => json_encode($config->not_allowed_coins ?? []),
            // Reconciler
            'ENABLE_RECONCILER_DOWNSIZE' => $config->enable_reconciler_downsize ? 'true' : 'false',
            'ENABLE_RECONCILER_UPSIZE'   => $config->enable_reconciler_upsize ? 'true' : 'false',
            // Safety
            'DRY_RUN'                    => $config->dry_run ? 'true' : 'false',
            // API ingestion (logs + state)
            'BOT_CONFIG_ID'              => (string) $config->id,
            'BOT_API_LOG_URL'            => rtrim(config('app.url'), '/') . '/api/bot/logs',
            'BOT_API_STATE_URL'          => rtrim(config('app.url'), '/') . '/api/bot/state',
            'BOT_API_TOKEN'              => config('app.bot_api_token'),
            // Per-bot files — unique per bot so sibling bots on the same vault
            // never overwrite each other's state or logs.
            'LOG_FILE'                   => $this->botDir . DIRECTORY_SEPARATOR . "bot_{$config->id}.log",
            'STATE_FILE'                 => $this->botDir . DIRECTORY_SEPARATOR . "state_{$config->id}.json",
            'AUDIT_FILE'                 => $this->botDir . DIRECTORY_SEPARATOR . "state_{$config->id}_audit.jsonl",
        ];
    }

    private function buildPsEnvBlock(BotConfig $config): string
    {
        $parts = [];
        foreach ($this->buildEnvVars($config) as $key => $value) {
            $escaped = str_replace("'", "''", (string) $value);
            $parts[] = "\$env:{$key}='{$escaped}'";
        }

        return implode('; ', $parts) . ';';
    }

    private function buildEnvArray(BotConfig $config): array
    {
        return array_merge($_ENV, $this->buildEnvVars($config));
    }
}
