<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL no indexa las claves foráneas por sí solo: los saldos de cada reserva y los
     * reportes agregan líneas y pagos por reserva, así que se indexan esas columnas.
     */
    public function up(): void
    {
        Schema::table('detalle_reserva', function (Blueprint $table) {
            $table->index('reserva_id', 'detalle_reserva_reserva_index');
        });

        Schema::table('pagos_ingresos', function (Blueprint $table) {
            $table->index('reserva_id', 'pagos_ingresos_reserva_index');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_reserva', function (Blueprint $table) {
            $table->dropIndex('detalle_reserva_reserva_index');
        });

        Schema::table('pagos_ingresos', function (Blueprint $table) {
            $table->dropIndex('pagos_ingresos_reserva_index');
        });
    }
};
