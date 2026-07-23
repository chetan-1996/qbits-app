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
            if (!Schema::hasColumn('plant_infos', 'current_month')) {
                $table->string('current_month', 7)->nullable()->after('year_power');
            }
            if (!Schema::hasColumn('plant_infos', 'current_year')) {
                $table->string('current_year', 4)->nullable()->after('current_month');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plant_infos', function (Blueprint $table) {
            $table->dropColumn(['current_month', 'current_year']);
        });
    }
};
