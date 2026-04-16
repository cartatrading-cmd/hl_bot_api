<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'name'                     => $this->name,
            'description'              => $this->description,
            'master_wallet'            => $this->master_wallet,
            'vault_address'            => $this->vault_address,
            'network'                  => $this->network,
            'allowed_coins'            => $this->allowed_coins,
            'not_allowed_coins'        => $this->not_allowed_coins,
            'user_ratio_multiplier'    => $this->user_ratio_multiplier,
            'leverage_multiplier'      => $this->leverage_multiplier,
            'enable_reconciler_downsize' => $this->enable_reconciler_downsize,
            'enable_reconciler_upsize'   => $this->enable_reconciler_upsize,
            'dry_run'                  => $this->dry_run,
            'is_active'                => $this->is_active,
            'pid'                      => $this->pid,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
