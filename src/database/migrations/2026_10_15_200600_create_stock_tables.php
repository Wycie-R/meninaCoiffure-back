<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-15 a HU-18: Inventario de insumos, movimientos, consumo estándar por servicio
     * (plantilla) y consumo real por atención.
     */
    public function up(): void
    {
        Schema::create('stock_recursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->decimal('cantidad_actual', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurso_id')->constrained('stock_recursos')->cascadeOnDelete();
            $table->string('tipo'); // entrada, salida
            $table->decimal('cantidad', 12, 2);
            $table->timestamp('fecha')->useCurrent();
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index(['recurso_id', 'fecha']);
        });

        Schema::create('servicio_recurso', function (Blueprint $table) {
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('recurso_id')->constrained('stock_recursos')->cascadeOnDelete();
            $table->decimal('cantidad_utilizada', 12, 2);
            $table->primary(['servicio_id', 'recurso_id']);
        });

        Schema::create('detalle_reserva_recurso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detalle_reserva_id')->constrained('detalle_reserva')->cascadeOnDelete();
            $table->foreignId('recurso_id')->constrained('stock_recursos');
            $table->decimal('cantidad_utilizada', 12, 2);
            $table->timestamps();

            $table->unique(['detalle_reserva_id', 'recurso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_reserva_recurso');
        Schema::dropIfExists('servicio_recurso');
        Schema::dropIfExists('movimientos_stock');
        Schema::dropIfExists('stock_recursos');
    }
};
