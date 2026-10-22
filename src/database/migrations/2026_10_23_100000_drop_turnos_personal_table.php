<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las profesionales trabajan bajo demanda: ya no tienen turnos fijos.
     * El único horario que restringe las citas es el del salón (horarios_atencion).
     */
    public function up(): void
    {
        Schema::dropIfExists('turnos_personal');
    }

    /**
     * Misma estructura que 2026_09_02_191501_create_turnos_personal_table.
     */
    public function down(): void
    {
        Schema::create('turnos_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 1=Lunes a 7=Domingo
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();
        });
    }
};
