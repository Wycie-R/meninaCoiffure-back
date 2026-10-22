<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se dejó de calcular el traslado de las citas a domicilio:
     * una cita de 08:00 a 10:00 ocupa exactamente ese horario.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservas DROP CONSTRAINT IF EXISTS reservas_traslado_solo_domicilio');
        }

        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('minutos_traslado');
        });
    }

    /**
     * Igual que 2026_10_15_200100_add_minutos_traslado_to_reservas_table.
     */
    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutos_traslado')->default(0)->after('direccion_domicilio');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservas ADD CONSTRAINT reservas_traslado_solo_domicilio CHECK (minutos_traslado BETWEEN 0 AND 240 AND (a_domicilio = true OR minutos_traslado = 0))');
        }
    }
};
