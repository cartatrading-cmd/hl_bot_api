<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();

            // Wallets
            $table->string('master_wallet');
            $table->text('private_key_encrypted');   // encrypted at rest via Laravel encrypt()
            $table->string('vault_address');

            // Network
            $table->enum('network', ['testnet', 'mainnet'])->default('mainnet');

            // Coin filters
            $table->json('allowed_coins')->default('[]');
            $table->json('not_allowed_coins')->default('[]');

            // Sizing
            $table->float('user_ratio_multiplier')->default(1.0);
            $table->float('leverage_multiplier')->default(1.0);
            $table->boolean('enable_reconciler_resize')->default(false);

            // Safety
            $table->boolean('dry_run')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_configs');
    }
};
