<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La clienta pide una cita y la dueña la convierte en reserva (o la rechaza con un motivo).
     */
    public function up(): void
    {
        Schema::create('solicitudes_cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios');
            $table->date('fecha_preferida');
            $table->string('franja', 20)->default('indistinto');
            $table->boolean('a_domicilio')->default(false);
            $table->string('direccion_domicilio')->nullable();
            $table->text('comentario')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('motivo_rechazo')->nullable();
            $table->foreignId('reserva_id')->nullable()->unique()->constrained('reservas')->nullOnDelete();
            $table->timestamps();

            // La bandeja de la dueña filtra por estado y ordena por fecha
            $table->index(['estado', 'fecha_preferida']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE solicitudes_cita ADD CONSTRAINT solicitudes_cita_estado_valido CHECK (estado IN ('pendiente', 'aceptada', 'rechazada', 'cancelada'))");
            DB::statement("ALTER TABLE solicitudes_cita ADD CONSTRAINT solicitudes_cita_franja_valida CHECK (franja IN ('manana', 'tarde', 'indistinto'))");
            DB::statement('ALTER TABLE solicitudes_cita ADD CONSTRAINT solicitudes_cita_domicilio_con_direccion CHECK (a_domicilio = false OR direccion_domicilio IS NOT NULL)');
            // Una solicitud aceptada siempre apunta a la reserva que generó
            DB::statement("ALTER TABLE solicitudes_cita ADD CONSTRAINT solicitudes_cita_aceptada_con_reserva CHECK (estado <> 'aceptada' OR reserva_id IS NOT NULL)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cita');
    }
};
