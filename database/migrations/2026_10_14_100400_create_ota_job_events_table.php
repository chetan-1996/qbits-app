<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ota_job_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ota_job_id')->constrained('ota_jobs')->cascadeOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->string('actor_ip', 45)->nullable();
            $table->string('event', 64);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ota_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ota_job_events');
    }
};
