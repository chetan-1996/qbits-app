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
            $table->boolean('watchlist_flag')->default(false)->after('panel_watt_peak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plant_infos', function (Blueprint $table) {
            $table->dropColumn('watchlist_flag');
        });
    }
};
