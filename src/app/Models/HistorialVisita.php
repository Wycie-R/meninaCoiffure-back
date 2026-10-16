<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialVisita extends Model
{
    protected $table = 'historial_visitas';
    protected $fillable = ['reserva_id', 'notas', 'fecha_realizada'];

    protected function casts(): array
    {
        return [
            // Se serializa como fecha sola (sin hora ni zona) para el expediente del cliente
            'fecha_realizada' => 'date:Y-m-d',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }
}
