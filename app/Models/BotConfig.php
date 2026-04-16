<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class BotConfig extends Model
{
    protected $fillable = [
        'name',
        'description',
        'master_wallet',
        'private_key_encrypted',
        'vault_address',
        'network',
        'allowed_coins',
        'not_allowed_coins',
        'user_ratio_multiplier',
        'leverage_multiplier',
        'enable_reconciler_downsize',
        'enable_reconciler_upsize',
        'max_drift_pct',
        'max_drift_usd',
        'dry_run',
        'is_active',
        'pid',
    ];

    protected $casts = [
        'allowed_coins'            => 'array',
        'not_allowed_coins'        => 'array',
        'user_ratio_multiplier'    => 'float',
        'leverage_multiplier'      => 'float',
        'enable_reconciler_downsize' => 'boolean',
        'enable_reconciler_upsize'   => 'boolean',
        'max_drift_pct'              => 'float',
        'max_drift_usd'              => 'float',
        'dry_run'                    => 'boolean',
        'is_active'                => 'boolean',
        'pid'                      => 'integer',
    ];

    protected $hidden = [
        'private_key_encrypted',
    ];

    public function setPrivateKeyAttribute(string $value): void
    {
        $this->attributes['private_key_encrypted'] = Crypt::encryptString($value);
    }

    public function getPrivateKeyAttribute(): string
    {
        return Crypt::decryptString($this->attributes['private_key_encrypted']);
    }
}
