<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Día puntual en que el salón no trabaja ("este miércoles no abrimos"). No toca el horario
 * semanal: ese día no se agenda nada, ni siquiera fuera de horario.
 */
class CierreSalon extends Model
{
    protected $table = 'cierres_salon';

    protected $fillable = ['fecha', 'motivo'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
        ];
    }

    /** El cierre de esa fecha, o null si el salón trabaja ese día. */
    public static function delDia(string $fecha): ?self
    {
        return self::whereDate('fecha', substr($fecha, 0, 10))->first();
    }
}
