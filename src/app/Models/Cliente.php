<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $fillable = ['nombre', 'apellido', 'telefono', 'email', 'fecha_registro'];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'cliente_id');
    }
}
