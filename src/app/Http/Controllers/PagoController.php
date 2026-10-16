<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-19 y HU-20: Registro de cobros. Una reserva admite varios pagos (seña, pago final,
 * pago dividido entre métodos); también se registran ingresos sueltos sin reserva.
 */
class PagoController extends Controller
{
    /** Relaciones del listado; los detalles y pagos de la reserva solo se cargan para calcular su saldo. */
    protected const RELACIONES = ['metodoPago', 'reserva.cliente', 'reserva.detalles', 'reserva.descuento', 'reserva.pagos'];

    /** Estados en los que la cita no se cobra: no se atendió a nadie. */
    protected const ESTADOS_SIN_COBRO = ['cancelada', 'no_asistio'];

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'metodo_pago_id' => ['nullable', 'integer'],
            'reserva_id' => ['nullable', 'integer'],
        ], [
            'per_page.max' => 'Se pueden pedir como máximo 100 pagos por página.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        // fecha es un timestamp: el rango cubre los días completos (fechas puras en UTC para no
        // depender del cambio de horario de America/Asuncion, que ocurre a medianoche)
        $pagos = Pago::with(self::RELACIONES)
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $fecha) => $q->where('fecha', '>=', Carbon::parse($fecha, 'UTC')->toDateString() . ' 00:00:00'))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $fecha) => $q->where('fecha', '<', Carbon::parse($fecha, 'UTC')->addDay()->toDateString() . ' 00:00:00'))
            ->when($filtros['metodo_pago_id'] ?? null, fn ($q, $id) => $q->where('metodo_pago_id', $id))
            ->when($filtros['reserva_id'] ?? null, fn ($q, $id) => $q->where('reserva_id', $id))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 15)
            ->withQueryString();

        $pagos->getCollection()->each(fn (Pago $pago) => $this->aligerarReserva($pago));

        return response()->json($pagos);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reserva_id' => ['nullable', 'integer', 'exists:reservas,id'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'fecha' => ['nullable', 'date', 'before_or_equal:now'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'reserva_id.exists' => 'La reserva indicada no existe.',
            'metodo_pago_id.required' => 'Indica el método de pago.',
            'metodo_pago_id.exists' => 'El método de pago indicado no existe.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'monto.max' => 'El monto es demasiado grande.',
            'fecha.date' => 'La fecha del pago no es válida.',
            'fecha.before_or_equal' => 'No se puede registrar un pago con fecha futura.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
        ]);

        $reservaId = $validated['reserva_id'] ?? null;

        // Un ingreso suelto sin explicación sería imposible de conciliar en la caja
        if (!$reservaId && blank($validated['descripcion'] ?? null)) {
            throw ValidationException::withMessages([
                'descripcion' => ['Un ingreso sin reserva necesita una descripción (por ejemplo, venta de producto).'],
            ]);
        }

        $datos = [
            'reserva_id' => $reservaId,
            'metodo_pago_id' => $validated['metodo_pago_id'],
            'monto' => round((float) $validated['monto'], 2),
            'fecha' => isset($validated['fecha']) ? Carbon::parse($validated['fecha']) : now(),
            'descripcion' => $validated['descripcion'] ?? null,
        ];

        $pago = DB::transaction(function () use ($datos, $reservaId) {
            if ($reservaId) {
                // Bloquea la reserva hasta el fin de la transacción: dos cobros simultáneos
                // (por ejemplo, en nodos distintos) no pueden superar juntos el saldo
                $reserva = Reserva::whereKey($reservaId)->lockForUpdate()->firstOrFail();
                $reserva->load(['detalles', 'descuento', 'pagos']);
                $this->validarCobroDeReserva($reserva, $datos['monto']);
            }

            return Pago::create($datos);
        });

        $pago->load('metodoPago');
        if ($pago->reserva_id) {
            $pago->load(['reserva.cliente', 'reserva.detalles', 'reserva.descuento', 'reserva.pagos']);
            $this->aligerarReserva($pago);
        }

        return response()->json([
            'message' => 'Pago registrado exitosamente.',
            'data' => $pago,
        ], 201);
    }

    public function show(Pago $pago): JsonResponse
    {
        return response()->json([
            'data' => $this->aligerarReserva($pago->load(self::RELACIONES)),
        ]);
    }

    /**
     * Anula un cobro cargado por error. La reserva recupera el saldo automáticamente,
     * porque el saldo se calcula a partir de sus pagos.
     */
    public function destroy(Pago $pago): JsonResponse
    {
        $pago->delete();

        return response()->json([
            'message' => 'Pago anulado exitosamente.',
        ]);
    }

    /**
     * HU-20: se admite seña en reservas pendientes o confirmadas y el pago en las completadas,
     * pero nunca más que el saldo pendiente.
     */
    protected function validarCobroDeReserva(Reserva $reserva, float $monto): void
    {
        if (in_array($reserva->estado, self::ESTADOS_SIN_COBRO, true)) {
            $estado = $reserva->estado === 'cancelada' ? 'cancelada' : 'marcada como no asistió';
            throw ValidationException::withMessages([
                'reserva_id' => ["No se pueden registrar pagos en una reserva {$estado}."],
            ]);
        }

        $saldo = (float) $reserva->saldo;

        if ($saldo <= 0) {
            throw ValidationException::withMessages([
                'monto' => ['La reserva ya está pagada en su totalidad.'],
            ]);
        }

        if ($monto > $saldo + 0.001) {
            throw ValidationException::withMessages([
                'monto' => ['El monto supera el saldo pendiente de la reserva (Gs. ' . $this->guaranies($saldo) . ').'],
            ]);
        }
    }

    /**
     * La reserva viaja con total, pagado y saldo, pero sin la lista de líneas ni de pagos,
     * que solo se cargaron para calcularlos.
     */
    protected function aligerarReserva(Pago $pago): Pago
    {
        $pago->reserva?->makeHidden(['detalles', 'pagos']);

        return $pago;
    }

    protected function guaranies(float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}
