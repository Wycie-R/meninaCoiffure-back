<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoPersonal extends Model
{
    protected $table = 'turnos_personal';
    protected $fillable = ['personal_id', 'dia_semana', 'hora_inicio', 'hora_fin'];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}