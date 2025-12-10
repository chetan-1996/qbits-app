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
        Schema::create('plant_infos', function (Blueprint $table) {
            $table->id();
            // Main plant details
            $table->integer('plant_no')->nullable();
            $table->string('plant_name')->nullable();

            // Boolean flags
            $table->boolean('is_room')->default(0);
            $table->boolean('is_enviroment')->default(0);
            $table->boolean('is_blackflow')->default(0);

            // Pricing & Occupancy
            $table->decimal('elec_subsidy_price', 10, 2)->default(0);
            $table->decimal('internet_power_price', 10, 2)->default(0);
            $table->decimal('own_power_price', 10, 2)->default(0);
            $table->decimal('internet_power_occupy', 10, 2)->default(0);
            $table->decimal('own_power_occupy', 10, 2)->default(0);

            // Remarks
            $table->string('remark1')->nullable();
            $table->string('remark2')->nullable();
            $table->string('remark3')->nullable();
            $table->string('remark4')->nullable();
            $table->string('remark5')->nullable();
            $table->string('remark6')->nullable();

            // User
            $table->string('plant_user')->nullable();

            // Power details
            $table->decimal('acpower', 10, 2)->nullable();
            $table->decimal('eday', 10, 2)->nullable();
            $table->decimal('etot', 10, 2)->nullable();

            // Status fields
            $table->integer('plantstate')->default(0);
            $table->integer('planttype')->default(0);

            // Record date
            $table->date('record_time')->nullable();

            // Capacity
            $table->decimal('capacity', 10, 2)->nullable();
            $table->decimal('capacitybattery', 10, 2)->nullable();

            // Location fields
            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();

            // Additional info
            $table->decimal('month_power', 10, 2)->nullable();
            $table->decimal('year_power', 10, 2)->nullable();
            $table->decimal('power_rate', 10, 2)->nullable();
            $table->decimal('kpi', 10, 2)->nullable();
            $table->string('date')->nullable();
            $table->boolean('watch')->default(false);
            $table->timestamp('time')->nullable();

            // Foreign key (optional)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('atun')->nullable();
            $table->string('atpd')->nullable();
            $table->json('full_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_infos');
    }
};
