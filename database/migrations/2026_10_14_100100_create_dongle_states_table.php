<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dongle_states', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 32)->unique();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('fw_ver', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dongle_states');
    }
};
