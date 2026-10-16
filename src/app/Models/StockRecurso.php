<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockRecurso extends Model
{
    protected $table = 'stock_recursos';
    protected $fillable = ['nombre', 'descripcion', 'unidad_medida', 'cantidad_actual', 'stock_minimo'];
    protected $appends = ['bajo_minimo'];

    protected function casts(): array
    {
        return [
            'cantidad_actual' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class, 'recurso_id');
    }

    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'servicio_recurso', 'recurso_id', 'servicio_id')
            ->withPivot('cantidad_utilizada');
    }

    /** Alerta de reposición: la existencia llegó al mínimo definido. */
    public function getBajoMinimoAttribute(): bool
    {
        return (float) $this->cantidad_actual <= (float) $this->stock_minimo;
    }
}
