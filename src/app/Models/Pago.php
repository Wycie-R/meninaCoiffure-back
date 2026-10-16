<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ingreso registrado (tabla pagos_ingresos). Puede pertenecer a una reserva
 * (seña, pago final, pago dividido) o ser un ingreso suelto.
 */
class Pago extends Model
{
    protected $table = 'pagos_ingresos';
    protected $fillable = ['reserva_id', 'metodo_pago_id', 'monto', 'fecha', 'descripcion'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }
}
