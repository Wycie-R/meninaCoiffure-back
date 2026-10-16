<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-14: tiempo de traslado de una cita a domicilio. El profesional queda ocupado
     * esos minutos antes y después de la cita, así la agenda no le asigna otra en el camino.
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
