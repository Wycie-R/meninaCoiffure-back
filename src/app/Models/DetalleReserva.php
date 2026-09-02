<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleReserva extends Model
{
    protected $table = 'detalle_reserva';
    protected $fillable = ['reserva_id', 'servicio_id', 'precio_aplicado', 'cantidad', 'agregado_en_el_momento'];

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
}