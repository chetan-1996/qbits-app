<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_daily_tkwh', function (Blueprint $table) {
            $table->id();
            $table->string('collector_id')->index();
            $table->date('record_date')->index();
            $table->decimal('tkwh', 10, 4)->nullable();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('atun');
            $table->string('atpd');
            $table->timestamps();

            $table->unique(['collector_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_daily_tkwh');
    }
};
