<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn('enable_reconciler_resize');
            // Downsize = reduce over-exposed positions when TVL drops. Default true (safety).
            $table->boolean('enable_reconciler_downsize')->default(true)->after('leverage_multiplier');
            // Upsize = increase under-exposed positions. Default false (preserves avg price).
            $table->boolean('enable_reconciler_upsize')->default(false)->after('enable_reconciler_downsize');
        });
    }

    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn(['enable_reconciler_downsize', 'enable_reconciler_upsize']);
            $table->boolean('enable_reconciler_resize')->default(false);
        });
    }
};
