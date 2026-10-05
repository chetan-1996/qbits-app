<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ota_job_units', function (Blueprint $table) {
            // Version the unit actually reports running afterwards: to_ver on
            // SUCCESS, or the telemetry ---FWVER on the MO-28 backstop path.
            $table->string('version_after', 32)->nullable()->after('version_before');
        });
    }

    public function down(): void
    {
        Schema::table('ota_job_units', function (Blueprint $table) {
            $table->dropColumn('version_after');
        });
    }
};
