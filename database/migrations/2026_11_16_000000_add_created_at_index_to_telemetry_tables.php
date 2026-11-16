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
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->index('created_at', 'idx_telemetry_raw_created_at');
        });

        Schema::table('telemetry_heartbeat', function (Blueprint $table) {
            $table->index('created_at', 'idx_telemetry_heartbeat_created_at');
        });

        Schema::table('modbus_write_logs', function (Blueprint $table) {
            $table->index('created_at', 'idx_modbus_write_logs_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropIndex('idx_telemetry_raw_created_at');
        });

        Schema::table('telemetry_heartbeat', function (Blueprint $table) {
            $table->dropIndex('idx_telemetry_heartbeat_created_at');
        });

        Schema::table('modbus_write_logs', function (Blueprint $table) {
            $table->dropIndex('idx_modbus_write_logs_created_at');
        });
    }
};
