<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consumo real de un insumo en una línea de atención (tabla detalle_reserva_recurso).
 * Se precarga con el consumo estándar del servicio y se puede ajustar por atención.
 */
class ConsumoRecurso extends Model
{
    protected $table = 'detalle_reserva_recurso';
    protected $fillable = ['detalle_reserva_id', 'recurso_id', 'cantidad_utilizada'];

    protected function casts(): array
    {
        return [
            'cantidad_utilizada' => 'decimal:2',
        ];
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(DetalleReserva::class, 'detalle_reserva_id');
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(StockRecurso::class, 'recurso_id');
    }
}
