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
            $table->string('azimuth')->nullable()->after('plantstate');
            $table->string('tilt')->nullable()->after('azimuth');
            $table->date('on_grid_date')->nullable()->after('tilt');
            $table->string('owner_phone')->nullable()->after('on_grid_date');
            $table->string('admin_phone')->nullable()->after('owner_phone');
            $table->string('installer_phone')->nullable()->after('admin_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plant_infos', function (Blueprint $table) {
            $table->dropColumn(['azimuth', 'tilt', 'on_grid_date', 'owner_phone', 'admin_phone', 'installer_phone']);
        });
    }
};
