<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('dry_run');
        });
    }

    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
