<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permisos que la dueña habilita o quita a cada empleada desde Configuración.
     * Por defecto todos valen true: así las cuentas existentes conservan lo que podían hacer.
     * La administración no depende de estas columnas (siempre puede todo).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('puede_crear_reservas')->default(true);
            $table->boolean('puede_cambiar_estado')->default(true);
            $table->boolean('puede_reservar_fuera_de_horario')->default(true);
            $table->boolean('puede_ver_clientes')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'puede_crear_reservas',
                'puede_cambiar_estado',
                'puede_reservar_fuera_de_horario',
                'puede_ver_clientes',
            ]);
        });
    }
};
