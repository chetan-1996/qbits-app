<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ota_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('otaid', 32)->unique();
            $table->foreignId('firmware_id')->nullable()->constrained('firmwares')->nullOnDelete();
            $table->string('target_version', 32)->nullable();
            $table->string('url', 511)->nullable();
            $table->boolean('force')->default(false);
            $table->string('status', 16)->default('draft'); // draft|sending|paused|stopped|completed
            $table->unsignedInteger('send_gap_seconds')->default(10);
            $table->unsignedInteger('max_concurrent')->default(10);
            $table->string('created_by_role', 32)->nullable();
            $table->string('created_by_ip', 45)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ota_jobs');
    }
};
