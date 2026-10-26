<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Días en que el salón no trabaja: uno puntual ("este miércoles no abrimos") o varios seguidos
 * (vacaciones, un accidente), de "fecha" a "fecha_hasta". No toca el horario semanal: esos días
 * no se agenda nada, ni siquiera fuera de horario.
 */
class CierreSalon extends Model
{
    protected $table = 'cierres_salon';

    protected $fillable = ['fecha', 'fecha_hasta', 'motivo'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'fecha_hasta' => 'date:Y-m-d',
        ];
    }

    /** El cierre que incluye esa fecha, o null si el salón trabaja ese día. */
    public static function delDia(string $fecha): ?self
    {
        $dia = substr($fecha, 0, 10);

        // Empezó ese día o antes, y termina ese día o después (sin fecha_hasta, termina el mismo día)
        return self::whereDate('fecha', '<=', $dia)
            ->where(function ($consulta) use ($dia) {
                $consulta->whereDate('fecha', $dia)
                    ->orWhereDate('fecha_hasta', '>=', $dia);
            })
            ->first();
    }
}
