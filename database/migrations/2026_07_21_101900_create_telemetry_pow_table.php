<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_pow', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable()->index();
            $table->string('collector_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('atun');
            $table->string('atpd');
            $table->decimal('pow', 10, 4)->nullable();
            $table->dateTime('record_datetime')->nullable()->index();
            $table->timestamps();

            $table->index(['collector_id', 'record_datetime']);
            $table->index(['plant_id', 'record_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_pow');
    }
};
