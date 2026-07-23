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
            if (!Schema::hasColumn('plant_infos', 'last_tkwh')) {
                $table->decimal('last_tkwh', 10, 2)->nullable()->after('current_year');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plant_infos', function (Blueprint $table) {
            $table->dropColumn('last_tkwh');
        });
    }
};
