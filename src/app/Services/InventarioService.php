<?php

namespace App\Services;

use App\Models\ConsumoRecurso;
use App\Models\MovimientoStock;
use App\Models\Reserva;
use App\Models\StockRecurso;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-15, HU-16 y HU-18: la existencia de un insumo solo cambia a través de movimientos,
 * así el historial siempre explica la cantidad actual.
 */
class InventarioService
{
    /**
     * Registra una entrada o salida y actualiza la existencia de forma atómica.
     * El recurso se bloquea hasta el fin de la transacción: dos movimientos simultáneos
     * (en nodos distintos) no pueden leer la misma existencia y pisarse.
     */
    public function registrarMovimiento(int $recursoId, string $tipo, float $cantidad, ?string $motivo = null, $fecha = null): MovimientoStock
    {
        return DB::transaction(function () use ($recursoId, $tipo, $cantidad, $motivo, $fecha) {
            $recurso = StockRecurso::lockForUpdate()->findOrFail($recursoId);
            $actual = (float) $recurso->cantidad_actual;

            // HU-16: una salida manual no puede dejar la existencia en negativo
            if ($tipo === 'salida' && $cantidad > $actual) {
                throw ValidationException::withMessages([
                    'cantidad' => ['No hay suficiente existencia: quedan ' . $this->formatear($actual, $recurso->unidad_medida) . '.'],
                ]);
            }

            $recurso->update([
                'cantidad_actual' => $tipo === 'entrada' ? $actual + $cantidad : $actual - $cantidad,
            ]);

            $movimiento = $recurso->movimientos()->create([
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'motivo' => $motivo,
                'fecha' => $fecha ?? now(),
            ]);

            return $movimiento->setRelation('recurso', $recurso);
        });
    }

    /**
     * HU-16 y HU-18: al completar una atención se descuenta lo que realmente se usó.
     * Completar nunca falla por el inventario: si la existencia registrada no alcanza
     * (por ejemplo, una compra que no se cargó), se descuenta lo que hay y el motivo lo aclara.
     */
    public function descontarConsumosDeReserva(Reserva $reserva): void
    {
        DB::transaction(function () use ($reserva) {
            $consumos = ConsumoRecurso::query()
                ->whereIn('detalle_reserva_id', $reserva->detalles()->select('id'))
                ->where('cantidad_utilizada', '>', 0)
                ->orderBy('recurso_id') // mismo orden de bloqueo siempre: evita interbloqueos
                ->orderBy('id')
                ->get();

            foreach ($consumos as $consumo) {
                $recurso = StockRecurso::lockForUpdate()->find($consumo->recurso_id);
                if (!$recurso) {
                    continue;
                }

                $usado = (float) $consumo->cantidad_utilizada;
                $actual = (float) $recurso->cantidad_actual;
                $descontado = min($usado, $actual);

                $motivo = "Consumo en reserva #{$reserva->id}";
                if ($descontado < $usado) {
                    $motivo .= ' (se usaron ' . $this->formatear($usado, $recurso->unidad_medida)
                        . '; existencia registrada insuficiente)';
                }

                $recurso->update(['cantidad_actual' => $actual - $descontado]);

                // El movimiento registra lo descontado, para que entradas menos salidas cuadre con la existencia
                $recurso->movimientos()->create([
                    'tipo' => 'salida',
                    'cantidad' => $descontado,
                    'motivo' => $motivo,
                    'fecha' => now(),
                ]);
            }
        });
    }

    /** 1200 ml, 12,5 g: formato local sin decimales sobrantes. */
    public function formatear(float $cantidad, ?string $unidad = null): string
    {
        $texto = number_format($cantidad, 2, ',', '.');
        $texto = rtrim(rtrim($texto, '0'), ',');

        return trim($texto . ' ' . ($unidad ?? ''));
    }
}
