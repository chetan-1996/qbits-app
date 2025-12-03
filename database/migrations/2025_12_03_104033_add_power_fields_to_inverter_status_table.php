<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inverter_status', function (Blueprint $table) {
            // precision/scale adjust kari shakay (12,3 = 999,999,999.999)
            $table->decimal('power', 12, 2)->default(0);
            $table->decimal('capacity', 12, 2)->default(0);
            $table->decimal('day_power', 12, 2)->default(0);
            $table->decimal('month_power', 12, 2)->default(0);
            $table->decimal('total_power', 16, 2)->default(0);

            // Jo specific order joiye hoy (MySQL only):
            // $table->decimal('power', 12, 3)->default(0)->after('some_existing_column');
        });
    }

    public function down(): void
    {
        Schema::table('inverter_status', function (Blueprint $table) {
            $table->dropColumn(['power', 'capacity', 'day_power', 'month_power', 'total_power']);
        });
    }
};
