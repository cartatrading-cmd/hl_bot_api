<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_config_id')
                  ->constrained('bot_configs')
                  ->cascadeOnDelete();
            $table->string('level', 16)->index();
            $table->string('event')->index();
            $table->json('context')->nullable();   // all extra fields from structlog
            $table->timestamp('logged_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_logs');
    }
};
