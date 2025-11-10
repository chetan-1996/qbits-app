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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->string('qq')->nullable();
            $table->string('email')->nullable();
            $table->string('collector')->nullable();
            $table->string('plant_name')->nullable();
            $table->string('inverter_type')->nullable();
            $table->string('city_name')->nullable();
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->string('parent')->nullable();
            $table->string('gmt')->nullable();
            $table->string('plant_type')->nullable();
            $table->string('iserial')->nullable();

            $table->boolean('whatsapp_notification_flag')->default(false);
            $table->boolean('inverter_fault_flag')->default(false);
            $table->boolean('daily_generation_report_flag')->default(false);
            $table->boolean('weekly_generation_report_flag')->default(false);
            $table->boolean('monthly_generation_report_flag')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
