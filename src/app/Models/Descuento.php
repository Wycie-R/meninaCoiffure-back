<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Descuento extends Model
{
    protected $table = 'descuentos';
    protected $fillable = ['nombre', 'tipo', 'valor', 'fecha_inicio', 'fecha_fin', 'activo'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'descuento_id');
    }

    /** Monto a restar de un subtotal: porcentaje o monto fijo, nunca mayor que el subtotal. */
    public function montoSobre(float $subtotal): float
    {
        $monto = $this->tipo === 'porcentaje'
            ? $subtotal * (float) $this->valor / 100
            : (float) $this->valor;

        return round(min($monto, $subtotal), 2);
    }
}
