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
        Schema::create('telemetry_heartbeat', function (Blueprint $table) {
            $table->id();
            $table->string('collector_id', 64);
            $table->string('inverter_id', 64);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            // Indexes (IMPORTANT for performance)
            $table->index(['collector_id', 'created_at'], 'idx_collector_time');
            $table->index(['inverter_id', 'created_at'], 'idx_inverter_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetry_heartbeat');
    }
};
