<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un cierre puede durar varios días seguidos (vacaciones, un accidente): va de "fecha" a
     * "fecha_hasta". Sin fecha_hasta es un solo día, como hasta ahora, así los cierres ya
     * cargados no cambian.
     */
    public function up(): void
    {
        Schema::table('cierres_salon', function (Blueprint $table) {
            $table->date('fecha_hasta')->nullable()->after('fecha');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cierres_salon ADD CONSTRAINT cierres_salon_rango_valido CHECK (fecha_hasta IS NULL OR fecha_hasta >= fecha)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cierres_salon DROP CONSTRAINT IF EXISTS cierres_salon_rango_valido');
        }

        Schema::table('cierres_salon', function (Blueprint $table) {
            $table->dropColumn('fecha_hasta');
        });
    }
};
