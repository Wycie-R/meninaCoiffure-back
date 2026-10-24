<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una clienta puede pedir varios servicios en la misma cita (por ejemplo, corte y brushing).
     * El servicio único de solicitudes_cita pasa a una tabla de detalle, como detalle_reserva.
     */
    public function up(): void
    {
        Schema::create('detalle_solicitud_cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cita_id')->constrained('solicitudes_cita')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios');
            $table->timestamps();

            // El mismo servicio no se pide dos veces en una solicitud
            $table->unique(['solicitud_cita_id', 'servicio_id']);
        });

        // Las solicitudes que ya existían conservan su servicio
        $solicitudes = DB::table('solicitudes_cita')->select('id', 'servicio_id', 'created_at')->get();
        foreach ($solicitudes as $solicitud) {
            DB::table('detalle_solicitud_cita')->insert([
                'solicitud_cita_id' => $solicitud->id,
                'servicio_id' => $solicitud->servicio_id,
                'created_at' => $solicitud->created_at,
                'updated_at' => $solicitud->created_at,
            ]);
        }

        Schema::table('solicitudes_cita', function (Blueprint $table) {
            $table->dropConstrainedForeignId('servicio_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_cita', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable()->after('cliente_id')->constrained('servicios');
        });

        // Al volver a un solo servicio se conserva el primero de cada solicitud
        $detalles = DB::table('detalle_solicitud_cita')->orderBy('id')->get();
        $yaAsignadas = [];
        foreach ($detalles as $detalle) {
            if (isset($yaAsignadas[$detalle->solicitud_cita_id])) {
                continue;
            }
            DB::table('solicitudes_cita')->where('id', $detalle->solicitud_cita_id)->update(['servicio_id' => $detalle->servicio_id]);
            $yaAsignadas[$detalle->solicitud_cita_id] = true;
        }

        Schema::dropIfExists('detalle_solicitud_cita');
    }
};
