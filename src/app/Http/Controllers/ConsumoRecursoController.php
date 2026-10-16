<?php

namespace App\Http\Controllers;

use App\Models\ConsumoRecurso;
use App\Models\DetalleReserva;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-18: Consumo real de insumos por atención. Nace con la plantilla del servicio
 * y la profesional lo ajusta antes de completar; al completar se descuenta del stock.
 */
class ConsumoRecursoController extends Controller
{
    public function index(Request $request, Reserva $reserva): JsonResponse
    {
        $this->autorizarReserva($request, $reserva);

        return response()->json([
            'data' => $this->consumosDeReserva($reserva),
        ]);
    }

    /**
     * Reemplaza los consumos de una línea de servicio.
     */
    public function update(Request $request, DetalleReserva $detalle): JsonResponse
    {
        $this->autorizarReserva($request, $detalle->reserva);

        $validated = $request->validate([
            'consumos' => ['present', 'array'],
            'consumos.*.recurso_id' => ['required', 'integer', 'distinct', 'exists:stock_recursos,id'],
            'consumos.*.cantidad_utilizada' => ['required', 'numeric', 'min:0'],
        ], [
            'consumos.present' => 'Envía la lista de insumos consumidos (puede estar vacía).',
            'consumos.array' => 'Los consumos deben enviarse en una lista.',
            'consumos.*.recurso_id.required' => 'Cada consumo debe indicar su recurso_id.',
            'consumos.*.recurso_id.distinct' => 'Un insumo no puede repetirse en los consumos de la línea.',
            'consumos.*.recurso_id.exists' => 'El insumo seleccionado no existe.',
            'consumos.*.cantidad_utilizada.required' => 'Indica la cantidad utilizada de cada insumo.',
            'consumos.*.cantidad_utilizada.numeric' => 'La cantidad utilizada debe ser un número.',
            'consumos.*.cantidad_utilizada.min' => 'La cantidad utilizada no puede ser negativa.',
        ]);

        DB::transaction(function () use ($detalle, $validated) {
            // Se bloquea la reserva: el ajuste no puede cruzarse con el paso a completada,
            // que es cuando el consumo se descuenta del stock
            $reserva = Reserva::lockForUpdate()->findOrFail($detalle->reserva_id);

            if (in_array($reserva->estado, Reserva::ESTADOS_FINALES, true)) {
                throw ValidationException::withMessages([
                    'estado' => ["La reserva está {$this->estadoLegible($reserva->estado)}: su consumo de insumos ya no se puede modificar."],
                ]);
            }

            $detalle->consumos()->delete();

            foreach ($validated['consumos'] as $item) {
                $detalle->consumos()->create([
                    'recurso_id' => (int) $item['recurso_id'],
                    'cantidad_utilizada' => $item['cantidad_utilizada'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Consumo de insumos actualizado exitosamente.',
            'data' => $this->lineaConConsumos($detalle->fresh(['servicio', 'consumos.recurso'])),
        ]);
    }

    protected function consumosDeReserva(Reserva $reserva): array
    {
        return $reserva->detalles()
            ->with(['servicio', 'consumos.recurso'])
            ->orderBy('id')
            ->get()
            ->map(fn (DetalleReserva $detalle) => $this->lineaConConsumos($detalle))
            ->all();
    }

    protected function lineaConConsumos(DetalleReserva $detalle): array
    {
        return [
            'detalle_reserva_id' => $detalle->id,
            'servicio' => $detalle->servicio
                ? ['id' => $detalle->servicio->id, 'nombre' => $detalle->servicio->nombre]
                : null,
            'cantidad' => (int) $detalle->cantidad,
            'consumos' => $detalle->consumos
                ->sortBy(fn (ConsumoRecurso $consumo) => $consumo->recurso?->nombre)
                ->values()
                ->map(fn (ConsumoRecurso $consumo) => [
                    'recurso_id' => $consumo->recurso_id,
                    'nombre' => $consumo->recurso?->nombre,
                    'unidad_medida' => $consumo->recurso?->unidad_medida,
                    'cantidad_utilizada' => (float) $consumo->cantidad_utilizada,
                ])
                ->all(),
        ];
    }

    protected function estadoLegible(string $estado): string
    {
        return match ($estado) {
            'no_asistio' => 'registrada como no asistida',
            default => $estado,
        };
    }
}
