<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-19, HU-20 y HU-21: Métodos de pago y registro de ingresos (una reserva puede
     * tener varios pagos: seña + pago final, o pago dividido entre métodos).
     */
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('pagos_ingresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas');
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            $table->decimal('monto', 12, 2);
            $table->timestamp('fecha')->useCurrent();
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_ingresos');
        Schema::dropIfExists('metodos_pago');
    }
};
