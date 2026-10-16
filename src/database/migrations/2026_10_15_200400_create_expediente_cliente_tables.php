<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-05 y HU-13: Expediente del cliente (preferencias e historial de visitas).
     */
    public function up(): void
    {
        Schema::create('preferencias_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('categoria')->nullable(); // alergia, color, profesional_preferido, producto, otro
            $table->text('descripcion');
            $table->timestamps();
        });

        Schema::create('historial_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->text('notas')->nullable();
            $table->date('fecha_realizada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_visitas');
        Schema::dropIfExists('preferencias_cliente');
    }
};
