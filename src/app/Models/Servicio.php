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

    /** HU-17: consumo estándar de insumos por servicio (plantilla editable). */
    public function recursos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(StockRecurso::class, 'servicio_recurso', 'servicio_id', 'recurso_id')
            ->withPivot('cantidad_utilizada');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleReserva::class, 'servicio_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioServicio::class, 'servicio_id');
    }

    public function precioVigente(?string $fecha = null): ?PrecioServicio
    {
        $fecha = $fecha ?? now()->toDateString();

        // Si los precios ya vienen cargados (listado del catálogo) se evita una consulta por servicio
        if ($this->relationLoaded('precios')) {
            return $this->precios
                ->filter(fn (PrecioServicio $p) => $p->vigente_desde <= $fecha && ($p->vigente_hasta === null || $p->vigente_hasta >= $fecha))
                ->sortByDesc('vigente_desde')
                ->first();
        }

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