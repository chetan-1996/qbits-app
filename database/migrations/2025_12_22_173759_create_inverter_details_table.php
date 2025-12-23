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
        Schema::create('inverter_details', function (Blueprint $table) {
            // $table->id();
            $table->unsignedBigInteger('inverterId')->primary();
            $table->dateTime('recordTime')->nullable();
            // $table->string('recordTime', 50)->nullable();
            $table->integer('inverterState')->nullable();
            $table->integer('onlineHours')->nullable();
            $table->integer('onlineMinutes')->nullable();
            $table->integer('onlineSeconds')->nullable();

            $table->integer('alarmInfor1')->nullable();
            $table->integer('alarmInfor2')->nullable();
            $table->integer('alarmInfor3')->nullable();
            $table->integer('alarmInfor4')->nullable();
            $table->integer('alarmInfor5')->nullable();
            $table->integer('alarmInfor6')->nullable();
            $table->integer('alarmInfor7')->nullable();
            $table->integer('alarmInfory1')->nullable();
            $table->integer('alarmInfory2')->nullable();
            $table->integer('alarmInfory3')->nullable();
            $table->integer('alarmInfory4')->nullable();
            $table->integer('alarmInfory5')->nullable();
            $table->integer('alarmInfory6')->nullable();
            $table->integer('alarmInfory7')->nullable();

            $table->float('dayMpp')->nullable();
            $table->float('mpptVoltage')->nullable();
            $table->float('acVoltage')->nullable();
            $table->float('acBvoltage')->nullable();
            $table->float('acCvoltage')->nullable();
            $table->float('acCurrent')->nullable();
            $table->float('acBcurrent')->nullable();
            $table->float('acCcurrent')->nullable();
            $table->float('acFrequency')->nullable();
            $table->float('acMomentaryPower')->nullable();
            $table->float('reactivePower')->nullable();

            $table->float('dcMomentaryPower')->nullable();
            $table->float('dcMomentaryPower2')->nullable();
            $table->float('dcMomentaryPower3')->nullable();
            $table->float('dcMomentaryPower4')->nullable();
            $table->float('dcMomentaryPower5')->nullable();
            $table->float('dcMomentaryPower6')->nullable();

            $table->float('dcCurrent')->nullable();
            $table->float('dcVoltage')->nullable();
            $table->float('dcCurrent2')->nullable();
            $table->float('dcVoltage2')->nullable();
            $table->float('dcCurrent3')->nullable();
            $table->float('dcVoltage3')->nullable();
            $table->float('dcCurrent4')->nullable();
            $table->float('dcVoltage4')->nullable();
            $table->float('dcCurrent5')->nullable();
            $table->float('dcVoltage5')->nullable();
            $table->float('dcCurrent6')->nullable();
            $table->float('dcVoltage6')->nullable();
            $table->float('dcVoltageMuxian')->nullable();
            $table->float('dcVoltageMuxian2')->nullable();

            $table->float('dayPowerHigh')->nullable();
            $table->float('dayPowerLower')->nullable();
            $table->float('totalPowerHigh')->nullable();
            $table->float('totalPowerLower')->nullable();

            $table->float('temperature')->nullable();
            $table->float('temperaturedc')->nullable();
            $table->float('powerFactor')->nullable();
            $table->float('co2')->nullable();
            $table->float('iv')->nullable();
            $table->float('angui')->nullable();

            $table->unsignedBigInteger('plantId')->index();
            $table->integer('keepLive')->nullable();
            $table->integer('signal')->nullable();

            $table->float('powerSet')->nullable();
            $table->float('repowerSet')->nullable();
            $table->integer('protocolV')->nullable();
            $table->integer('deviceType')->nullable();
            $table->bigInteger('currentSwitch')->nullable();

            $table->float('current1')->nullable();
            $table->float('current2')->nullable();
            $table->float('current3')->nullable();
            $table->float('current4')->nullable();
            $table->float('current5')->nullable();
            $table->float('current6')->nullable();
            $table->float('current7')->nullable();
            $table->float('current8')->nullable();
            $table->float('current9')->nullable();
            $table->float('current10')->nullable();
            $table->float('current11')->nullable();
            $table->float('current12')->nullable();
            $table->float('current13')->nullable();
            $table->float('current14')->nullable();
            $table->float('current15')->nullable();
            $table->float('current16')->nullable();

            $table->string('inverterSn', 50)->nullable();
            $table->string('inverterNo', 50)->nullable();
            $table->integer('outputStatus')->nullable();

            $table->float('meterPower')->nullable();
            $table->float('meterTotal')->nullable();
            $table->float('meterRetotal')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inverter_details');
    }
};
