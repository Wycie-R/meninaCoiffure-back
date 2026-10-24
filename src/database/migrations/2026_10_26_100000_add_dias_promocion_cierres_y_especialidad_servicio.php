<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tres cambios de agenda:
     * - dias_descuento: una promoción puede regir solo ciertos días de la semana ("los miércoles").
     *   Sin filas, rige todos los días (como hasta ahora).
     * - cierres_salon: un día puntual en que el salón no trabaja ("este miércoles no abrimos"),
     *   sin tocar el horario semanal.
     * - servicios.especialidad_id: la especialidad que pide un servicio, para avisar si se agenda
     *   con una profesional que no la tiene.
     */
    public function up(): void
    {
        Schema::create('dias_descuento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('descuento_id')->constrained('descuentos')->cascadeOnDelete();
            // ISO: 1 = lunes … 7 = domingo, igual que horarios_atencion.dia_semana
            $table->unsignedTinyInteger('dia_semana');
            $table->timestamps();

            $table->unique(['descuento_id', 'dia_semana']);
        });

        Schema::create('cierres_salon', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('motivo')->nullable();
            $table->timestamps();
        });

        Schema::table('servicios', function (Blueprint $table) {
            $table->foreignId('especialidad_id')->nullable()->after('categoria_id')->constrained('especialidades')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE dias_descuento ADD CONSTRAINT dias_descuento_dia_valido CHECK (dia_semana BETWEEN 1 AND 7)');
        }

        // Bases ya cargadas: cada servicio del catálogo queda con su especialidad
        $especialidadDeServicio = [
            'Corte de dama' => 'Peluquería',
            'Corte de caballero' => 'Peluquería',
            'Corte de niños' => 'Peluquería',
            'Lavado, secado y modelado' => 'Peluquería',
            'Nutrición e hidratación capilar' => 'Peluquería',
            'Coloración completa' => 'Colorimetría',
            'Mechas y reflejos' => 'Colorimetría',
            'Balayage' => 'Colorimetría',
            'Peinado para eventos' => 'Peinados para eventos',
            'Maquillaje social' => 'Maquillaje',
            'Depilación facial' => 'Estética',
            'Depilación corporal' => 'Estética',
            'Limpieza facial e hidratación' => 'Estética',
        ];

        foreach ($especialidadDeServicio as $servicio => $especialidad) {
            $especialidadId = DB::table('especialidades')->where('nombre', $especialidad)->value('id');
            if ($especialidadId === null) {
                continue;
            }

            DB::table('servicios')
                ->where('nombre', $servicio)
                ->whereNull('especialidad_id')
                ->update(['especialidad_id' => $especialidadId]);
        }
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('especialidad_id');
        });

        Schema::dropIfExists('cierres_salon');
        Schema::dropIfExists('dias_descuento');
    }
};
