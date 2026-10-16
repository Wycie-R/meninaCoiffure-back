<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para la validación de solapamiento (personal + fecha) y los filtros por fecha de la agenda.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->index(['personal_id', 'fecha'], 'reservas_personal_fecha_index');
            $table->index('fecha', 'reservas_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropIndex('reservas_personal_fecha_index');
            $table->dropIndex('reservas_fecha_index');
        });
    }
};
