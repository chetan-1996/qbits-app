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
        Schema::create('modbus_write_logs', function (Blueprint $table) {
            $table->id();
            $table->string('collector_id', 64)->index();
            $table->string('topic', 255)->nullable();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['collector_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modbus_write_logs');
    }
};
