<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inverter_faults', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('inverter_id');
            $table->bigInteger('plant_id');
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('itype')->default(0);

            $table->string('inverter_sn')->nullable();

            $table->dateTime('stime')->nullable();
            $table->dateTime('etime')->nullable();

            $table->json('meta')->nullable();
            $table->json('message_cn')->nullable();
            $table->json('message_en')->nullable();

            $table->timestamps();

            // HIGH PERFORMANCE INDEXES
            $table->index(['inverter_id', 'stime']);
            $table->index('plant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inverter_faults');
    }
};
