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
            $table->unsignedInteger('pid')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn('pid');
        });
    }
};
