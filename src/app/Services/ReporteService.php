<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * HU-23 y HU-24: Reportes de gestión. Todo se resuelve con consultas agregadas en la base
 * (sin cargar modelos) para que respondan rápido aun con miles de reservas.
 *
 * Criterios comunes:
 * - Las reservas se agrupan por reservas.fecha; los cobros, por la fecha del pago.
 * - "facturado" es el total (subtotal de líneas menos descuento) de las reservas COMPLETADAS.
 * - "cobrado" es la suma de los pagos con fecha dentro del período.
 */
class ReporteService
{
    public const ESTADOS = ['pendiente', 'confirmada', 'completada', 'cancelada', 'no_asistio'];

    public function resumen(string $desde, string $hasta): array
    {
        // 1. Reservas del período por estado (una sola consulta)
        $porEstado = array_fill_keys(self::ESTADOS, 0);
        $aDomicilio = 0;
        $filas = DB::table('reservas')
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('estado, COUNT(*) AS cantidad, SUM(CASE WHEN a_domicilio THEN 1 ELSE 0 END) AS domicilio')
            ->groupBy('estado')
            ->get();
        foreach ($filas as $fila) {
            $porEstado[$fila->estado] = (int) $fila->cantidad;
            $aDomicilio += (int) $fila->domicilio;
        }
        $totalReservas = array_sum($porEstado);

        // 2. Facturación de las completadas, con lo que todavía deben
        $pagosPorReserva = DB::table('pagos_ingresos')
            ->whereNotNull('reserva_id')
            ->selectRaw('reserva_id, SUM(monto) AS pagado')
            ->groupBy('reserva_id');

        $completadas = $this->reservasConTotal($desde, $hasta)
            ->where('r.estado', 'completada')
            ->leftJoinSub($pagosPorReserva, 'p', 'p.reserva_id', '=', 'r.id')
            ->selectRaw('r.cliente_id, ' . $this->expresionTotal() . ' AS total, COALESCE(p.pagado, 0) AS pagado');

        $facturacion = DB::query()->fromSub($completadas, 't')
            ->selectRaw('COUNT(*) AS cantidad, COUNT(DISTINCT t.cliente_id) AS clientes, COALESCE(SUM(t.total), 0) AS facturado,
                COALESCE(SUM(CASE WHEN t.total > t.pagado THEN t.total - t.pagado ELSE 0 END), 0) AS por_cobrar')
            ->first();

        // 3. Cobros del período por método de pago
        $porMetodo = $this->pagosDelPeriodo('pg', $desde, $hasta)
            ->join('metodos_pago as m', 'm.id', '=', 'pg.metodo_pago_id')
            ->selectRaw('m.id AS metodo_pago_id, m.nombre AS metodo, SUM(pg.monto) AS monto, COUNT(*) AS cantidad')
            ->groupBy('m.id', 'm.nombre')
            ->orderByRaw('SUM(pg.monto) DESC')
            ->get()
            ->map(fn ($fila) => [
                'metodo_pago_id' => (int) $fila->metodo_pago_id,
                'metodo' => $fila->metodo,
                'monto' => $this->monto($fila->monto),
                'cantidad' => (int) $fila->cantidad,
            ])
            ->values();

        $cantidadCompletadas = (int) $facturacion->cantidad;
        $facturado = $this->monto($facturacion->facturado);
        $finalizadas = $porEstado['completada'] + $porEstado['no_asistio'];

        return [
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'reservas' => [
                'total' => $totalReservas,
                'por_estado' => $porEstado,
                'a_domicilio' => $aDomicilio,
                // Ausencias sobre las citas que llegaron a su hora (las canceladas no cuentan)
                'tasa_ausencia' => $this->tasa($porEstado['no_asistio'], $finalizadas),
                'tasa_cancelacion' => $this->tasa($porEstado['cancelada'], $totalReservas),
            ],
            'ingresos' => [
                'facturado' => $facturado,
                'cobrado' => $this->monto($porMetodo->sum('monto')),
                'por_cobrar' => $this->monto($facturacion->por_cobrar),
                'ticket_promedio' => $cantidadCompletadas > 0 ? round($facturado / $cantidadCompletadas, 2) : 0.0,
                'por_metodo' => $porMetodo->all(),
            ],
            'clientes' => [
                'atendidos' => (int) $facturacion->clientes,
                'nuevos' => DB::table('clientes')->whereBetween('fecha_registro', [$desde, $hasta])->count(),
            ],
        ];
    }

    /**
     * Servicios más pedidos en reservas completadas. Los ingresos de cada línea llevan su parte
     * proporcional del descuento de la reserva, así la suma coincide con el facturado.
     */
    public function servicios(string $desde, string $hasta): array
    {
        $totales = $this->reservasConTotal($desde, $hasta)
            ->where('r.estado', 'completada')
            ->selectRaw('r.id AS reserva_id, COALESCE(l.subtotal, 0) AS subtotal, ' . $this->expresionTotal() . ' AS total');

        return DB::table('detalle_reserva as dr')
            ->joinSub($totales, 't', 't.reserva_id', '=', 'dr.reserva_id')
            ->join('servicios as s', 's.id', '=', 'dr.servicio_id')
            ->leftJoin('categorias_servicio as c', 'c.id', '=', 's.categoria_id')
            ->selectRaw('s.id AS servicio_id, s.nombre, c.nombre AS categoria, SUM(dr.cantidad) AS cantidad,
                SUM(CASE WHEN t.subtotal > 0 THEN dr.precio_aplicado * dr.cantidad * t.total / t.subtotal ELSE 0 END) AS ingresos')
            ->groupBy('s.id', 's.nombre', 'c.nombre')
            // Por la expresión y no por el alias: cantidad también es columna de detalle_reserva
            ->orderByRaw('SUM(dr.cantidad) DESC')
            ->orderByDesc('ingresos')
            ->orderBy('s.nombre')
            ->get()
            ->map(fn ($fila) => [
                'servicio_id' => (int) $fila->servicio_id,
                'nombre' => $fila->nombre,
                'categoria' => $fila->categoria,
                'cantidad' => (int) $fila->cantidad,
                'ingresos' => $this->monto($fila->ingresos),
            ])
            ->all();
    }

    /**
     * Desempeño de cada profesional que tuvo reservas en el período.
     */
    public function personal(string $desde, string $hasta): array
    {
        $reservas = $this->reservasConTotal($desde, $hasta)
            ->selectRaw('r.personal_id, r.estado, ' . $this->expresionMinutos() . ' AS minutos, ' . $this->expresionTotal() . ' AS total');

        return DB::query()->fromSub($reservas, 't')
            ->join('personal as pe', 'pe.id', '=', 't.personal_id')
            ->selectRaw("pe.id AS personal_id, pe.nombre, pe.apellido,
                SUM(CASE WHEN t.estado = 'completada' THEN 1 ELSE 0 END) AS completadas,
                SUM(CASE WHEN t.estado = 'no_asistio' THEN 1 ELSE 0 END) AS no_asistio,
                SUM(CASE WHEN t.estado = 'cancelada' THEN 1 ELSE 0 END) AS canceladas,
                SUM(CASE WHEN t.estado = 'completada' THEN t.minutos ELSE 0 END) AS minutos_atendidos,
                SUM(CASE WHEN t.estado = 'completada' THEN t.total ELSE 0 END) AS facturado")
            ->groupBy('pe.id', 'pe.nombre', 'pe.apellido')
            ->orderByDesc('facturado')
            ->orderBy('pe.nombre')
            ->get()
            ->map(fn ($fila) => [
                'personal_id' => (int) $fila->personal_id,
                'nombre' => $fila->nombre,
                'apellido' => $fila->apellido,
                'completadas' => (int) $fila->completadas,
                'no_asistio' => (int) $fila->no_asistio,
                'canceladas' => (int) $fila->canceladas,
                'minutos_atendidos' => (int) round((float) $fila->minutos_atendidos),
                'facturado' => $this->monto($fila->facturado),
            ])
            ->all();
    }

    /**
     * Una fila por día del período (los días sin movimiento van en cero, para graficar).
     */
    public function ingresosDiarios(string $desde, string $hasta): array
    {
        $reservas = $this->reservasConTotal($desde, $hasta)
            ->where('r.estado', 'completada')
            ->selectRaw('r.fecha, ' . $this->expresionTotal() . ' AS total');

        $facturadoPorDia = DB::query()->fromSub($reservas, 't')
            ->selectRaw('t.fecha, COUNT(*) AS citas, SUM(t.total) AS facturado')
            ->groupBy('t.fecha')
            ->get()
            ->keyBy(fn ($fila) => substr((string) $fila->fecha, 0, 10));

        $cobradoPorDia = $this->pagosDelPeriodo('pg', $desde, $hasta)
            ->selectRaw('DATE(pg.fecha) AS dia, SUM(pg.monto) AS cobrado')
            ->groupByRaw('DATE(pg.fecha)')
            ->get()
            ->keyBy(fn ($fila) => substr((string) $fila->dia, 0, 10));

        // Se recorre en UTC: en America/Asuncion el cambio de horario ocurre a medianoche y un
        // recorrido en hora local puede saltearse el último día
        $dias = [];
        foreach (CarbonPeriod::create(Carbon::parse($desde, 'UTC'), Carbon::parse($hasta, 'UTC')) as $dia) {
            $clave = $dia->toDateString();
            $dias[] = [
                'fecha' => $clave,
                'citas_completadas' => (int) ($facturadoPorDia[$clave]->citas ?? 0),
                'facturado' => $this->monto($facturadoPorDia[$clave]->facturado ?? 0),
                'cobrado' => $this->monto($cobradoPorDia[$clave]->cobrado ?? 0),
            ];
        }

        return $dias;
    }

    /**
     * Reservas del período con el subtotal de sus líneas (alias l) y su descuento (alias d),
     * listas para calcular el total con expresionTotal().
     */
    protected function reservasConTotal(string $desde, string $hasta): Builder
    {
        // Solo se suman las líneas de las reservas del período, no toda la tabla
        $lineas = DB::table('detalle_reserva as dl')
            ->join('reservas as rl', 'rl.id', '=', 'dl.reserva_id')
            ->whereBetween('rl.fecha', [$desde, $hasta])
            ->selectRaw('dl.reserva_id, SUM(dl.precio_aplicado * dl.cantidad) AS subtotal')
            ->groupBy('dl.reserva_id');

        return DB::table('reservas as r')
            ->leftJoinSub($lineas, 'l', 'l.reserva_id', '=', 'r.id')
            ->leftJoin('descuentos as d', 'd.id', '=', 'r.descuento_id')
            ->whereBetween('r.fecha', [$desde, $hasta]);
    }

    /**
     * Total de la reserva en SQL, igual que Reserva::total y Descuento::montoSobre(): el descuento
     * (porcentaje redondeado a 2 decimales o monto fijo) nunca supera el subtotal.
     * Usa solo CASE y ROUND, que existen tanto en PostgreSQL como en SQLite;
     * el 100.0 evita la división entera de SQLite.
     */
    protected function expresionTotal(): string
    {
        $subtotal = 'COALESCE(l.subtotal, 0)';
        $porcentaje = "ROUND({$subtotal} * d.valor / 100.0, 2)";

        return "({$subtotal} - CASE
            WHEN d.id IS NULL THEN 0
            WHEN d.tipo = 'porcentaje' THEN CASE WHEN {$porcentaje} < {$subtotal} THEN {$porcentaje} ELSE {$subtotal} END
            ELSE CASE WHEN d.valor < {$subtotal} THEN d.valor ELSE {$subtotal} END
        END)";
    }

    /** Duración de la cita en minutos; la resta de horas cambia según el motor. */
    protected function expresionMinutos(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(EPOCH FROM (r.hora_fin - r.hora_inicio)) / 60',
            'sqlite' => "(strftime('%s', r.hora_fin) - strftime('%s', r.hora_inicio)) / 60",
            default => 'TIME_TO_SEC(TIMEDIFF(r.hora_fin, r.hora_inicio)) / 60',
        };
    }

    /** Pagos cuya fecha (timestamp) cae en los días del período, inclusive. */
    protected function pagosDelPeriodo(string $alias, string $desde, string $hasta): Builder
    {
        [$inicio, $fin] = $this->limitesDeTiempo($desde, $hasta);

        return DB::table("pagos_ingresos as {$alias}")
            ->where("{$alias}.fecha", '>=', $inicio)
            ->where("{$alias}.fecha", '<', $fin);
    }

    /** [inicio del primer día, inicio del día siguiente al último] para columnas timestamp. */
    protected function limitesDeTiempo(string $desde, string $hasta): array
    {
        // Fechas puras (en UTC) para no depender del cambio de horario a medianoche
        return [
            Carbon::parse($desde, 'UTC')->toDateString() . ' 00:00:00',
            Carbon::parse($hasta, 'UTC')->addDay()->toDateString() . ' 00:00:00',
        ];
    }

    protected function tasa(int $parte, int $total): float
    {
        return $total > 0 ? round($parte / $total, 4) : 0.0;
    }

    protected function monto(mixed $valor): float
    {
        return round((float) ($valor ?? 0), 2);
    }
}
