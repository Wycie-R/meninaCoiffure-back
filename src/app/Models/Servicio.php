<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    protected $table = 'servicios';
    protected $fillable = ['categoria_id', 'nombre', 'descripcion', 'duracion_minutos', 'activo'];

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CategoriaServicio::class, 'categoria_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioServicio::class, 'servicio_id');
    }

    public function precioVigente(?string $fecha = null): ?PrecioServicio
    {
        $fecha = $fecha ?? now()->toDateString();
        return $this->precios()
            ->where('vigente_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha);
            })
            ->orderByDesc('vigente_desde')
            ->first();
    }

    public function getPrecioActualAttribute(): ?float
    {
        $pv = $this->precioVigente();
        return $pv ? (float) $pv->precio : null;
    }
}