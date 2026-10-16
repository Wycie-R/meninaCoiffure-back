<?php

namespace App\Models;

use App\Events\DetalleReservaCreado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleReserva extends Model
{
    protected $table = 'detalle_reserva';
    protected $fillable = ['reserva_id', 'servicio_id', 'precio_aplicado', 'cantidad', 'agregado_en_el_momento'];

    /** HU-18: al crear la línea se precarga el consumo estándar de insumos del servicio. */
    protected $dispatchesEvents = [
        'created' => DetalleReservaCreado::class,
    ];

    protected function casts(): array
    {
        return [
            'precio_aplicado' => 'decimal:2',
            'agregado_en_el_momento' => 'boolean',
            'cantidad' => 'integer',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoRecurso::class, 'detalle_reserva_id');
    }
}
