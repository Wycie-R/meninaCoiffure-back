<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reserva extends Model
{
    public const ESTADOS = ['pendiente', 'confirmada', 'cancelada', 'completada', 'no_asistio'];

    /** Estados que ocupan el horario del profesional (Regla 1). */
    public const ESTADOS_QUE_OCUPAN = ['pendiente', 'confirmada', 'completada'];

    /**
     * Estados finales: la reserva ya no admite cambios (Regla 5). "No asistió" no está: la dueña
     * puede corregirla o reprogramarla (la clienta llegó tarde o la cita se mueve a otro día).
     */
    public const ESTADOS_FINALES = ['cancelada', 'completada'];

    protected $table = 'reservas';
    protected $fillable = [
        'cliente_id', 'personal_id', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'descuento_id',
        'a_domicilio', 'direccion_domicilio', 'minutos_traslado', 'fuera_de_horario',
    ];

    protected $appends = ['subtotal', 'monto_descuento', 'total', 'pagado', 'saldo'];

    protected function casts(): array
    {
        return [
            'a_domicilio' => 'boolean',
            'minutos_traslado' => 'integer',
            'fuera_de_horario' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleReserva::class, 'reserva_id');
    }

    public function descuento(): BelongsTo
    {
        return $this->belongsTo(Descuento::class, 'descuento_id');
    }

    /** HU-19 y HU-20: una reserva puede tener varios pagos (seña, pago final, pago dividido). */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'reserva_id');
    }

    /** HU-13: registro de la visita, se crea al completar la reserva. */
    public function historial(): HasOne
    {
        return $this->hasOne(HistorialVisita::class, 'reserva_id');
    }

    /** Suma de los precios congelados de cada línea (Regla 4). */
    protected function subtotal(): Attribute
    {
        return Attribute::get(fn () => round(
            $this->detalles->sum(fn (DetalleReserva $detalle) => (float) $detalle->precio_aplicado * $detalle->cantidad),
            2
        ));
    }

    protected function montoDescuento(): Attribute
    {
        return Attribute::get(fn () => $this->descuento ? $this->descuento->montoSobre($this->subtotal) : 0.0);
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn () => round($this->subtotal - $this->monto_descuento, 2));
    }

    protected function pagado(): Attribute
    {
        return Attribute::get(fn () => round($this->pagos->sum(fn (Pago $pago) => (float) $pago->monto), 2));
    }

    protected function saldo(): Attribute
    {
        return Attribute::get(fn () => round(max($this->total - $this->pagado, 0), 2));
    }
}
