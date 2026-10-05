<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmwares', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32)->unique();
            $table->string('filename', 128);
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64);
            $table->text('notes')->nullable();
            $table->boolean('is_backup')->default(false);
            $table->string('uploaded_by_role', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmwares');
    }
};
