<?php

namespace App\Listeners;

use App\Events\DetalleReservaCreado;
use App\Models\ConsumoRecurso;

/**
 * HU-17 y HU-18: cada línea de servicio nace con el consumo estándar de su plantilla
 * (cantidad del pivot × cantidad de la línea). Luego la profesional lo ajusta a lo real.
 * Es síncrono a propósito: corre dentro de la transacción que crea la reserva.
 */
class PrecargarConsumoEstandar
{
    public function handle(DetalleReservaCreado $event): void
    {
        $detalle = $event->detalle;
        $servicio = $detalle->servicio;
        if (!$servicio) {
            return;
        }

        $cantidadLinea = max((int) $detalle->cantidad, 1);

        foreach ($servicio->recursos as $recurso) {
            ConsumoRecurso::updateOrCreate(
                ['detalle_reserva_id' => $detalle->id, 'recurso_id' => $recurso->id],
                ['cantidad_utilizada' => round((float) $recurso->pivot->cantidad_utilizada * $cantidadLinea, 2)],
            );
        }
    }
}
