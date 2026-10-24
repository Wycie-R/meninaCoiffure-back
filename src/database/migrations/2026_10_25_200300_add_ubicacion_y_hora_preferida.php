<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La ficha guarda la dirección y la ubicación de la casa de la clienta, para precargarlas
     * en cada cita a domicilio. El pedido guarda la hora que eligió la clienta, así la dueña
     * no tiene que volver a elegirla.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('direccion_domicilio')->nullable()->after('email');
            // Seis decimales: unos 10 cm de precisión, más que suficiente para encontrar una casa
            $table->decimal('latitud', 9, 6)->nullable()->after('direccion_domicilio');
            $table->decimal('longitud', 9, 6)->nullable()->after('latitud');
        });

        Schema::table('solicitudes_cita', function (Blueprint $table) {
            $table->time('hora_preferida')->nullable()->after('fecha_preferida');
            $table->boolean('fuera_de_horario')->default(false)->after('franja');
        });

        if (DB::getDriverName() === 'pgsql') {
            // La ubicación va completa o no va: una sola coordenada no sirve para llegar
            DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_ubicacion_completa CHECK ((latitud IS NULL) = (longitud IS NULL))');
            DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_ubicacion_valida CHECK (latitud BETWEEN -90 AND 90 AND longitud BETWEEN -180 AND 180)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_ubicacion_valida');
            DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_ubicacion_completa');
        }

        Schema::table('solicitudes_cita', function (Blueprint $table) {
            $table->dropColumn(['hora_preferida', 'fuera_de_horario']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['direccion_domicilio', 'latitud', 'longitud']);
        });
    }
};
