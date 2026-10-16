<?php

namespace App\Listeners;

use App\Events\ReservaCompletada;
use App\Models\HistorialVisita;

/**
 * HU-13: al completar una reserva, la atención queda en el historial del cliente.
 * Es síncrono a propósito: corre dentro de la transacción que completa la reserva,
 * así que si falla, la reserva tampoco queda completada.
 */
class RegistrarHistorialVisita
{
    public function handle(ReservaCompletada $evento): void
    {
        // reserva_id es único: si ya había un registro (por ejemplo, cargado a mano), se actualiza
        $visita = HistorialVisita::firstOrNew(['reserva_id' => $evento->reserva->id]);

        $visita->fecha_realizada = now()->toDateString();

        // Sin notas nuevas no se pisan las que ya estuvieran cargadas
        if ($evento->notas !== null || !$visita->exists) {
            $visita->notas = $evento->notas;
        }

        $visita->save();
    }
}
