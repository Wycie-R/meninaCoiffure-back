<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pedido de cita de una clienta. No ocupa la agenda: la dueña la acepta creando la reserva
 * (con todas las reglas de ReservaService) o la rechaza con un motivo.
 */
class SolicitudCita extends Model
{
    public const ESTADOS = ['pendiente', 'aceptada', 'rechazada', 'cancelada'];

    public const FRANJAS = ['manana', 'tarde', 'indistinto'];

    /** Tope de pedidos abiertos por clienta, para que la bandeja no se llene de duplicados. */
    public const MAXIMO_PENDIENTES = 3;

    /** Servicios que se pueden pedir juntos en una misma cita. */
    public const MAXIMO_SERVICIOS = 8;

    protected $table = 'solicitudes_cita';

    protected $fillable = [
        'cliente_id', 'fecha_preferida', 'hora_preferida', 'franja', 'fuera_de_horario', 'a_domicilio', 'direccion_domicilio',
        'comentario', 'estado', 'motivo_rechazo', 'reserva_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_preferida' => 'date:Y-m-d',
            'a_domicilio' => 'boolean',
            'fuera_de_horario' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** Lo que pidió la clienta; al aceptar, la dueña puede sumar o quitar servicios en la reserva. */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'detalle_solicitud_cita', 'solicitud_cita_id', 'servicio_id')
            ->withTimestamps()
            ->orderBy('servicios.nombre');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }
}
