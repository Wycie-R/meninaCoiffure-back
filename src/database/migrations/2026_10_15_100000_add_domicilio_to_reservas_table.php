<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-14: Reservas a domicilio. La dirección vive en la reserva (no en el cliente)
     * porque cada visita puede ser en un lugar distinto.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->boolean('a_domicilio')->default(false)->after('estado');
            $table->string('direccion_domicilio', 255)->nullable()->after('a_domicilio');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservas ADD CONSTRAINT reservas_domicilio_con_direccion CHECK (a_domicilio = false OR direccion_domicilio IS NOT NULL)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservas DROP CONSTRAINT IF EXISTS reservas_domicilio_con_direccion');
        }

        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn(['a_domicilio', 'direccion_domicilio']);
        });
    }
};
