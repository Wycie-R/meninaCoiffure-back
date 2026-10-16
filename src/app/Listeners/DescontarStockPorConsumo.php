<?php

namespace App\Listeners;

use App\Events\ReservaCompletada;
use App\Services\InventarioService;

/**
 * HU-16 y HU-18: al completar una atención se descuentan del inventario los insumos
 * realmente consumidos. Síncrono: si algo falla, se revierte junto con el cambio de estado.
 */
class DescontarStockPorConsumo
{
    public function __construct(
        protected InventarioService $inventario,
    ) {}

    public function handle(ReservaCompletada $event): void
    {
        $this->inventario->descontarConsumosDeReserva($event->reserva);
    }
}
