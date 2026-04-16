<?php

declare(strict_types=1);

return [
    'state_file'    => env('HL_BOT_STATE_FILE', base_path('../hl_bot/state.json')),
    'audit_file'    => env('HL_BOT_AUDIT_FILE', base_path('../hl_bot/state_audit.jsonl')),
    'log_file'      => env('HL_BOT_LOG_FILE', base_path('../hl_bot/bot.log')),
    'vault_address' => env('HL_BOT_VAULT_ADDRESS', '0x5d7904A14e8B0C077Daa06581A58A18C1387A667'),
];
