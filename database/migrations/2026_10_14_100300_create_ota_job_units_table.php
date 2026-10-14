<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ota_job_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ota_job_id')->constrained('ota_jobs')->cascadeOnDelete();
            $table->string('imei', 32);
            $table->string('site')->nullable();
            $table->string('note')->nullable();
            $table->string('version_before', 32)->nullable();
            $table->string('state', 16)->default('QUEUED');
            $table->string('reason', 64)->nullable();
            $table->string('detail')->nullable();
            $table->string('unit_ts', 32)->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->boolean('version_differs')->default(false);
            $table->boolean('confirmed_by_telemetry')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_update_at')->nullable();
            $table->timestamps();

            // not unique: duplicate IMEIs in the upload are kept as INVALID rows
            $table->index(['ota_job_id', 'imei']);
            $table->index(['imei', 'state']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ota_job_units');
    }
};
