<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Descuento extends Model
{
    protected $table = 'descuentos';
    protected $fillable = ['nombre', 'tipo', 'valor', 'fecha_inicio', 'fecha_fin', 'activo'];

    // Los días viajan como una lista simple: [3] (los miércoles) o [] (todos los días)
    protected $appends = ['dias_semana'];

    protected $hidden = ['dias'];

    // Siempre se cargan juntos: las reservas listan su promoción y no debe haber una consulta por cada una
    protected $with = ['dias'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /** Promociones aplicables en una fecha: activas, dentro de su vigencia y que rigen ese día de la semana. */
    public function scopeVigentes(Builder $query, string $fecha): Builder
    {
        $diaDeLaSemana = Carbon::parse($fecha)->dayOfWeekIso;

        return $query
            ->enPeriodo($fecha)
            ->where(function (Builder $q) use ($diaDeLaSemana) {
                // Sin días cargados rige todos los días
                $q->whereDoesntHave('dias')
                    ->orWhereHas('dias', function (Builder $dias) use ($diaDeLaSemana) {
                        $dias->where('dia_semana', $diaDeLaSemana);
                    });
            });
    }

    /**
     * Activas y con la fecha dentro de su vigencia, sin mirar el día de la semana. La página
     * pública las muestra así: "Los miércoles" se anuncia toda la semana.
     */
    public function scopeEnPeriodo(Builder $query, string $fecha): Builder
    {
        return $query
            ->where('activo', true)
            ->where(function (Builder $q) use ($fecha) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $fecha);
            })
            ->where(function (Builder $q) use ($fecha) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $fecha);
            });
    }

    public function dias(): HasMany
    {
        return $this->hasMany(DiaDescuento::class, 'descuento_id')->orderBy('dia_semana');
    }

    /** Días en que rige (1 = lunes … 7 = domingo). Vacío: todos los días. */
    public function getDiasSemanaAttribute(): array
    {
        $dias = [];
        foreach ($this->dias as $dia) {
            $dias[] = (int) $dia->dia_semana;
        }

        return $dias;
    }

    public function rigeElDia(string $fecha): bool
    {
        $dias = $this->dias_semana;
        if (count($dias) === 0) {
            return true;
        }

        return in_array(Carbon::parse($fecha)->dayOfWeekIso, $dias, true);
    }

    /** Reemplaza los días en que rige. Una lista vacía vuelve a "todos los días". */
    public function guardarDias(array $dias): void
    {
        $this->dias()->delete();

        $sinRepetir = array_values(array_unique(array_map('intval', $dias)));
        sort($sinRepetir);
        foreach ($sinRepetir as $dia) {
            $this->dias()->create(['dia_semana' => $dia]);
        }

        $this->unsetRelation('dias');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'descuento_id');
    }

    /** Monto a restar de un subtotal: porcentaje o monto fijo, nunca mayor que el subtotal. */
    public function montoSobre(float $subtotal): float
    {
        $monto = $this->tipo === 'porcentaje'
            ? $subtotal * (float) $this->valor / 100
            : (float) $this->valor;

        return round(min($monto, $subtotal), 2);
    }
}
