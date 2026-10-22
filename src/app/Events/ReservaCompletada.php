<?php

namespace App\Events;

use App\Models\Reserva;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite cuando una reserva pasa a completada, dentro de la misma transacción.
 * El listener registra la visita en el historial del cliente (HU-13).
 */
class ReservaCompletada
{
    use Dispatchable;

    public function __construct(
        public Reserva $reserva,
        public ?string $notas = null,
    ) {}
}
