<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    protected $table = 'servicios';
    protected $fillable = ['categoria_id', 'nombre', 'descripcion', 'duracion_minutos', 'activo'];

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioServicio::class, 'servicio_id');
    }
}