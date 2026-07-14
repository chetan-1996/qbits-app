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
        Schema::create('dongles', function (Blueprint $table) {
            $table->id();
            $table->string('dongle_id', 32)->unique();
            $table->string('imei', 32)->unique();
            $table->string('imsi', 32)->unique();
            $table->string('sim_num', 64)->unique();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dongles');
    }
};
