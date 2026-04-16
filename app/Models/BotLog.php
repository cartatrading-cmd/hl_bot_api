<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bot_config_id',
        'level',
        'event',
        'context',
        'logged_at',
    ];

    protected $casts = [
        'context'   => 'array',
        'logged_at' => 'datetime',
    ];

    public function botConfig(): BelongsTo
    {
        return $this->belongsTo(BotConfig::class);
    }
}
