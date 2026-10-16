<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-08: Horario de atención del local (puede haber más de una franja por día).
     */
    public function up(): void
    {
        Schema::create('horarios_atencion', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('dia_semana'); // 1=Lunes a 7=Domingo
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->timestamps();

            $table->index('dia_semana');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_atencion');
    }
};
