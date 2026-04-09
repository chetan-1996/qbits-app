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
        Schema::create('solar_power_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->date('record_date');
            $table->time('record_time');
            $table->decimal('ac_momentary_power', 8, 4);
            $table->integer('irradiation')->default(0);
            $table->decimal('eday', 8, 2)->nullable(); // total energy for the day
            $table->timestamps();

            // Prevent duplicate: same plant + date + time slot
            $table->unique(['plant_id', 'record_date']);
            $table->index(['plant_id', 'record_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solar_power_logs');
    }
};
