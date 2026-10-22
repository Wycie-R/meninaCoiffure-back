<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regla 2: toda cita debe caber en el horario del salón, salvo que la dueña
     * autorice atenderla fuera de horario (por ejemplo, un sábado o a la noche).
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->boolean('fuera_de_horario')->default(false)->after('direccion_domicilio');
        });

        // Las reservas ya cargadas que no caben en el horario del salón (las de domicilio,
        // que antes no lo respetaban) se marcan como fuera de horario para seguir siendo válidas
        $hayHorario = DB::table('horarios_atencion')->exists();

        if ($hayHorario && DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE reservas r SET fuera_de_horario = TRUE
                WHERE NOT EXISTS (
                    SELECT 1 FROM horarios_atencion h
                    WHERE h.dia_semana = EXTRACT(ISODOW FROM r.fecha)
                      AND h.hora_apertura <= r.hora_inicio
                      AND h.hora_cierre >= r.hora_fin
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('fuera_de_horario');
        });
    }
};
