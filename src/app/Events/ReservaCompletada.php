<?php

namespace App\Events;

use App\Models\Reserva;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite cuando una reserva pasa a completada, dentro de la misma transacción.
 * Los listeners registran la visita en el historial del cliente (HU-13)
 * y descuentan del inventario los insumos consumidos (HU-16 y HU-18).
 */
class ReservaCompletada
{
    use Dispatchable;

    public function __construct(
        public Reserva $reserva,
        public ?string $notas = null,
    ) {}
}
