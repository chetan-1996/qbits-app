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
        Schema::create('channel_partners', function (Blueprint $table) {
            $table->id();

            $table->string('photo');

            $table->string('name');
            $table->string('company_name');
            $table->string('designation');

            $table->string('mobile',20)->unique();
            $table->string('whatsapp_no',20);

            $table->text('address');
            $table->string('state');
            $table->string('city');

            $table->decimal('latitude',10,7);
            $table->decimal('longitude',10,7);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_partners');
    }
};
