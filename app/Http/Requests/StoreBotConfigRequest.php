<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBotConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PATCH') || $this->isMethod('PUT');
        $nameUnique = Rule::unique('bot_configs', 'name')
            ->when($isUpdate, fn ($rule) => $rule->ignore($this->route('bot_config')));

        return [
            'name'                     => ['required', 'string', 'max:64', $nameUnique],
            'description'              => ['nullable', 'string', 'max:255'],
            'master_wallet'            => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'private_key'              => [$isUpdate ? 'sometimes' : 'required', 'string', 'regex:/^0x[0-9a-fA-F]{64}$/'],
            'vault_address'            => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'network'                  => ['required', 'in:testnet,mainnet'],
            'allowed_coins'            => ['nullable', 'array'],
            'allowed_coins.*'          => ['string', 'max:20'],
            'not_allowed_coins'        => ['nullable', 'array'],
            'not_allowed_coins.*'      => ['string', 'max:20'],
            'user_ratio_multiplier'    => ['numeric', 'min:0.01', 'max:100'],
            'leverage_multiplier'      => ['numeric', 'min:0.1', 'max:10'],
            'enable_reconciler_downsize' => ['boolean'],
            'enable_reconciler_upsize'   => ['boolean'],
            'max_drift_pct'              => ['numeric', 'min:0.01', 'max:1.0'],
            'max_drift_usd'              => ['numeric', 'min:0'],
            'dry_run'                    => ['boolean'],
        ];
    }
}
