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
        Schema::create('inverter_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();

            // Store full inverter API response
            $table->json('data')->nullable();

            // Plant status summary - default 0
            $table->integer('all_plant')->default(0);
            $table->integer('normal_plant')->default(0);
            $table->integer('alarm_plant')->default(0);
            $table->integer('offline_plant')->default(0);

            // Login data used for API calls
            $table->string('atun')->nullable();
            $table->string('atpd')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inverter_status');
    }
};
