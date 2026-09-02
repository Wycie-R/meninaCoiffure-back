<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personal extends Model
{
    protected $table = 'personal';
    protected $fillable = ['nombre', 'apellido', 'telefono', 'email', 'fecha_ingreso', 'activo'];

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoPersonal::class, 'personal_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'personal_id');
    }
}