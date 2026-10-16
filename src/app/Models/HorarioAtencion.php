<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioAtencion extends Model
{
    protected $table = 'horarios_atencion';
    protected $fillable = ['dia_semana', 'hora_apertura', 'hora_cierre'];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }
}
