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
        $tables = [
            'inverters',
            'inverter_details',
            'inverter_faults',
            'inverter_status',
            'plant_infos',
            'qbits_daily_generations',
            'solar_power_logs',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'server_flag')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->tinyInteger('server_flag')
                        ->default(0)
                        ->comment('0 - Aotai, 1 - adralabs');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'inverters',
            'inverter_details',
            'inverter_faults',
            'inverter_status',
            'plant_infos',
            'qbits_daily_generations',
            'solar_power_logs',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'server_flag')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('server_flag');
                });
            }
        }
    }
};
