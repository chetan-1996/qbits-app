<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // inverters table
        Schema::table('inverters', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('atun')->nullable();
            $table->string('atpd')->nullable();
        });

        // inverter_details table
        Schema::table('inverter_details', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('atun')->nullable();
            $table->string('atpd')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inverters', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'atun', 'atpd']);
        });

        Schema::table('inverter_details', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'atun', 'atpd']);
        });
    }
};
