<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_config_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('master_positions')->default('{}');
            $table->json('vault_positions')->default('{}');
            $table->json('owned_coins')->default('[]');
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bot_state_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_config_id')->constrained()->cascadeOnDelete();
            $table->json('master_positions')->default('{}');
            $table->json('vault_positions')->default('{}');
            $table->json('owned_coins')->default('[]');
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['bot_config_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_state_audit');
        Schema::dropIfExists('bot_states');
    }
};
