<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\DetalleReserva;
use App\Models\HistorialVisita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * HU-13: Historial de visitas del cliente. Cada visita nace al completar una reserva
 * (listener RegistrarHistorialVisita); aquí se consulta el expediente y se corrigen las notas.
 */
class HistorialVisitaController extends Controller
{
    /** Relaciones que necesita cada visita; se cargan de una vez para evitar N+1. */
    protected const RELACIONES = [
        'reserva.personal:id,nombre,apellido',
        'reserva.detalles.servicio:id,nombre',
        'reserva.descuento',
    ];

    /**
     * Expediente del cliente: datos, preferencias (HU-05), visitas y un resumen para quien atiende.
     */
    public function index(Cliente $cliente): JsonResponse
    {
        $visitas = $cliente->historial()
            ->with(self::RELACIONES)
            ->orderByDesc('historial_visitas.fecha_realizada')
            ->orderByDesc('reservas.fecha')
            ->orderByDesc('reservas.hora_inicio')
            ->orderByDesc('historial_visitas.id')
            ->get();

        // Ausencias y cancelaciones en una sola consulta
        $cliente->loadCount([
            'reservas as ausencias' => fn ($q) => $q->where('estado', 'no_asistio'),
            'reservas as cancelaciones' => fn ($q) => $q->where('estado', 'cancelada'),
        ]);

        return response()->json([
            'data' => [
                'cliente' => $cliente->only(['id', 'nombre', 'apellido', 'telefono', 'email']),
                'preferencias' => $cliente->preferencias()->orderBy('id')->get(),
                'visitas' => $visitas->map(fn (HistorialVisita $visita) => $this->formatearVisita($visita))->values(),
                'resumen' => [
                    'visitas' => $visitas->count(),
                    'ultima_visita' => $visitas->first()?->fecha_realizada?->toDateString(),
                    'profesional_frecuente' => $this->profesionalFrecuente($visitas),
                    'servicios_frecuentes' => $this->serviciosFrecuentes($visitas),
                    'ausencias' => (int) $cliente->ausencias,
                    'cancelaciones' => (int) $cliente->cancelaciones,
                ],
            ],
        ]);
    }

    /**
     * Corregir o completar las observaciones de una atención. Un profesional
     * solo puede tocar las visitas de su propia agenda.
     */
    public function update(Request $request, HistorialVisita $historial): JsonResponse
    {
        $this->autorizarReserva($request, $historial->reserva);

        $validated = $request->validate([
            'notas' => ['present', 'nullable', 'string', 'max:2000'],
        ], [
            'notas.present' => 'Debe enviar las notas de la visita (pueden estar vacías).',
            'notas.max' => 'Las notas no pueden superar los 2000 caracteres.',
        ]);

        $historial->update(['notas' => $validated['notas']]);

        return response()->json([
            'message' => 'Notas de la visita actualizadas exitosamente.',
            'data' => $this->formatearVisita($historial->load(self::RELACIONES)),
        ]);
    }

    protected function formatearVisita(HistorialVisita $visita): array
    {
        $reserva = $visita->reserva;

        return [
            'id' => $visita->id,
            'reserva_id' => $visita->reserva_id,
            'fecha_realizada' => $visita->fecha_realizada?->toDateString(),
            'notas' => $visita->notas,
            'profesional' => $reserva->personal?->only(['id', 'nombre', 'apellido']),
            'servicios' => $reserva->detalles->map(fn (DetalleReserva $detalle) => [
                'nombre' => $detalle->servicio?->nombre,
                'cantidad' => (int) $detalle->cantidad,
                'precio_aplicado' => (float) $detalle->precio_aplicado,
            ])->values(),
            'total' => $reserva->total,
            'a_domicilio' => (bool) $reserva->a_domicilio,
        ];
    }

    /**
     * Quien más veces atendió al cliente; ante empate, quien lo atendió más recientemente.
     */
    protected function profesionalFrecuente(Collection $visitas): ?array
    {
        $conteo = [];
        $profesionales = [];

        // Las visitas vienen de la más reciente a la más antigua: la primera en aparecer gana el empate
        foreach ($visitas as $visita) {
            $personal = $visita->reserva->personal;
            if (!$personal) {
                continue;
            }

            $conteo[$personal->id] = ($conteo[$personal->id] ?? 0) + 1;
            $profesionales[$personal->id] ??= $personal->only(['id', 'nombre', 'apellido']);
        }

        $elegido = null;
        foreach ($conteo as $id => $veces) {
            if ($elegido === null || $veces > $conteo[$elegido]) {
                $elegido = $id;
            }
        }

        return $elegido === null ? null : $profesionales[$elegido];
    }

    /**
     * Los tres servicios que más se hizo el cliente (contando la cantidad de cada línea).
     */
    protected function serviciosFrecuentes(Collection $visitas): array
    {
        $servicios = [];

        foreach ($visitas as $visita) {
            foreach ($visita->reserva->detalles as $detalle) {
                $clave = $detalle->servicio_id;
                $servicios[$clave] ??= ['nombre' => $detalle->servicio?->nombre, 'veces' => 0];
                $servicios[$clave]['veces'] += (int) $detalle->cantidad;
            }
        }

        return collect($servicios)
            ->sortBy([['veces', 'desc'], ['nombre', 'asc']])
            ->take(3)
            ->values()
            ->all();
    }
}
