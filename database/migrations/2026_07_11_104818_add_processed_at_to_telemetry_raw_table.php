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
            $table->timestamp('processed_at')->nullable()->index()->after('created_at');
        });
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
