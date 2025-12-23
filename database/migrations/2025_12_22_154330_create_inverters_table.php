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
        Schema::create('inverters', function (Blueprint $table) {
            $table->id(); // API ID

            $table->integer('inverter_no')->nullable();
            $table->integer('inverter_address')->nullable();
            $table->bigInteger('collector_address')->nullable();
            $table->string('model')->nullable();
            $table->integer('state')->nullable();
            $table->integer('control')->nullable();
            $table->integer('register0a')->nullable();
            $table->integer('register31')->nullable();
            $table->integer('register29')->nullable();
            $table->integer('register2a')->nullable();
            $table->string('remark1')->nullable();
            $table->string('remark2')->nullable();
            $table->string('remark3')->nullable();
            $table->string('remark4')->nullable();
            $table->integer('room_id')->nullable();
            $table->integer('plant_id')->index();
            $table->integer('timezone')->nullable();
            $table->string('record_time')->nullable();
            $table->integer('inverter_type')->nullable();
            $table->integer('load')->nullable();
            $table->integer('panel')->nullable();
            $table->integer('panel_num')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inverters');
    }
};
