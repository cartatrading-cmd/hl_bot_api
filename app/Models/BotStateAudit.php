<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotStateAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'bot_state_audit';

    protected $fillable = [
        'bot_config_id',
        'master_positions',
        'vault_positions',
        'owned_coins',
        'snapshot_at',
    ];

    protected $casts = [
        'master_positions' => 'array',
        'vault_positions'  => 'array',
        'owned_coins'      => 'array',
        'snapshot_at'      => 'datetime',
    ];

    public function botConfig(): BelongsTo
    {
        return $this->belongsTo(BotConfig::class);
    }
}
