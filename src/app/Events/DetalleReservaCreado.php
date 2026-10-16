<?php

namespace App\Events;

use App\Models\DetalleReserva;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite al crear una línea de servicio en una reserva (al reservar o en el momento).
 * El listener de inventario precarga el consumo estándar de insumos (HU-17 y HU-18).
 */
class DetalleReservaCreado
{
    use Dispatchable;

    public function __construct(
        public DetalleReserva $detalle,
    ) {}
}
