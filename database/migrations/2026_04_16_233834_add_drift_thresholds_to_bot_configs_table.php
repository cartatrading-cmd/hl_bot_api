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
            // Relative drift threshold: fraction of the proportional target ratio.
            // Both max_drift_pct AND max_drift_usd must be exceeded simultaneously
            // before the reconciler freezes or triggers a correction order.
            $table->float('max_drift_pct')->default(0.05)->after('enable_reconciler_upsize');
            // Absolute USD drift threshold: minimum USD gap required for a coin to be
            // considered drifted. Prevents tiny positions from triggering false freezes.
            $table->float('max_drift_usd')->default(500.0)->after('max_drift_pct');
        });
    }

    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn(['max_drift_pct', 'max_drift_usd']);
        });
    }
};