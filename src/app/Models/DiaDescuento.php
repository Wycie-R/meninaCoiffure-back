<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Día de la semana en que rige una promoción (1 = lunes … 7 = domingo).
 */
class DiaDescuento extends Model
{
    protected $table = 'dias_descuento';

    protected $fillable = ['descuento_id', 'dia_semana'];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }
}
