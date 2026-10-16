<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correr el seeder de prueba sobre una base sin categorías duplicaba al personal
     * (Carla y Diana aparecían tres veces). Se fusionan los duplicados por correo en el
     * registro más antiguo y se impide que vuelva a pasar con un índice único.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('personal')->whereNotNull('email')->update(['email' => DB::raw('LOWER(email)')]);

            $duplicados = DB::table('personal')
                ->select('email', DB::raw('MIN(id) as conservar'))
                ->whereNotNull('email')
                ->groupBy('email')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicados as $grupo) {
                $sobrantes = DB::table('personal')
                    ->where('email', $grupo->email)
                    ->where('id', '!=', $grupo->conservar)
                    ->pluck('id');

                DB::table('reservas')->whereIn('personal_id', $sobrantes)->update(['personal_id' => $grupo->conservar]);
                DB::table('users')->whereIn('personal_id', $sobrantes)->update(['personal_id' => $grupo->conservar]);

                // Los turnos de los duplicados son copias: se conservan solo si el registro original no tiene
                if (DB::table('turnos_personal')->where('personal_id', $grupo->conservar)->exists()) {
                    DB::table('turnos_personal')->whereIn('personal_id', $sobrantes)->delete();
                } else {
                    $primero = $sobrantes->first();
                    DB::table('turnos_personal')->where('personal_id', $primero)->update(['personal_id' => $grupo->conservar]);
                    DB::table('turnos_personal')->whereIn('personal_id', $sobrantes)->delete();
                }

                DB::table('personal')->whereIn('id', $sobrantes)->delete();
            }
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->unique('email', 'personal_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropUnique('personal_email_unique');
        });
    }
};
