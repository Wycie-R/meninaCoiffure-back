<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-14: vuelve el tiempo de traslado de las citas a domicilio. Un viaje largo ocupa la agenda
     * de la profesional antes y después de la cita, así no se le asigna otra en el camino.
     * Solo a domicilio (en el salón vale 0) y como máximo 4 horas.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutos_traslado')->default(0)->after('direccion_domicilio');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservas ADD CONSTRAINT reservas_traslado_solo_domicilio CHECK (minutos_traslado BETWEEN 0 AND 240 AND (a_domicilio = true OR minutos_traslado = 0))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservas DROP CONSTRAINT IF EXISTS reservas_traslado_solo_domicilio');
        }

        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('minutos_traslado');
        });
    }
};
