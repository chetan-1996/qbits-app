<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // telemetry_raw was created outside migrations on production;
        // create it here when missing so a fresh schema migrates cleanly.
        if (!Schema::hasTable('telemetry_raw')) {
            Schema::create('telemetry_raw', function (Blueprint $table) {
                $table->id();
                $table->string('collector_id', 64);
                $table->string('inverter_id', 64);
                $table->json('payload');
                $table->timestamp('created_at')->useCurrent();
                $table->index(['collector_id', 'created_at'], 'idx_raw_collector_time');
            });
        }

        if (!Schema::hasColumn('telemetry_raw', 'processed_at')) {
            Schema::table('telemetry_raw', function (Blueprint $table) {
                $table->timestamp('processed_at')->nullable()->index()->after('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });
    }
};
