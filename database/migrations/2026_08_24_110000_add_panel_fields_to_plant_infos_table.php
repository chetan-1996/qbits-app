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
        Schema::table('plant_infos', function (Blueprint $table) {
            $table->integer('no_of_panel')->nullable()->after('installer_phone');
            $table->integer('panel_watt_peak')->nullable()->after('no_of_panel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plant_infos', function (Blueprint $table) {
            $table->dropColumn(['no_of_panel', 'panel_watt_peak']);
        });
    }
};
