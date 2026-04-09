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
        Schema::table('solar_power_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('atun')->nullable()->after('user_id');
            $table->string('atpd')->nullable()->after('atun');

            // optional index (if filtering needed)
            $table->index(['atun', 'atpd']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solar_power_logs', function (Blueprint $table) {
            $table->dropIndex(['atun', 'atpd']);
            $table->dropColumn(['user_id', 'atun', 'atpd']);
        });
    }
};
