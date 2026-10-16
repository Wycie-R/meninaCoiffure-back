<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $fillable = ['nombre', 'apellido', 'telefono', 'email', 'fecha_registro'];

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'cliente_id');
    }

    public function preferencias(): HasMany
    {
        return $this->hasMany(PreferenciaCliente::class, 'cliente_id');
    }

    public function historial(): HasManyThrough
    {
        return $this->hasManyThrough(HistorialVisita::class, Reserva::class, 'cliente_id', 'reserva_id');
    }
}
