<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecioServicio extends Model
{
    protected $table = 'precios_servicio';
    protected $fillable = ['servicio_id', 'precio', 'vigente_desde', 'vigente_hasta'];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}