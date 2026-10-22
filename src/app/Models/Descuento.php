<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /** Promociones aplicables en una fecha: activas y con la fecha dentro de su vigencia. */
    public function scopeVigentes(Builder $query, string $fecha): Builder
    {
        return $query
            ->where('activo', true)
            ->where(function (Builder $q) use ($fecha) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $fecha);
            })
            ->where(function (Builder $q) use ($fecha) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $fecha);
            });
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
