<?php

namespace App\Http\Controllers;

use App\Models\Descuento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HU-22: Descuentos y promociones que se aplican sobre el subtotal de una reserva.
 */
class DescuentoController extends Controller
{
    public const TIPOS = ['porcentaje', 'monto_fijo'];

    protected const MENSAJES = [
        'nombre.required' => 'El nombre de la promoción es obligatorio.',
        'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
        'tipo.required' => 'Indica el tipo de descuento.',
        'tipo.in' => 'El tipo de descuento debe ser porcentaje o monto_fijo.',
        'valor.required' => 'El valor del descuento es obligatorio.',
        'valor.numeric' => 'El valor del descuento debe ser un número.',
        'valor.gt' => 'El valor del descuento debe ser mayor que cero.',
        'fecha_inicio.date' => 'La fecha de inicio no es una fecha válida.',
        'fecha_fin.date' => 'La fecha de fin no es una fecha válida.',
        'activo.boolean' => 'El campo activo debe ser verdadero o falso.',
        'dias_semana.array' => 'Los días deben enviarse como una lista.',
        'dias_semana.*.between' => 'Cada día debe ser entre 1 (lunes) y 7 (domingo).',
        'dias_semana.*.distinct' => 'Elegiste el mismo día dos veces.',
    ];

    /**
     * Listado (cualquier rol). Con solo_vigentes=1 devuelve las promociones aplicables hoy,
     * que son las que acepta la pantalla de nueva reserva.
     */
    public function index(Request $request): JsonResponse
    {
        $hoy = now()->toDateString();

        $descuentos = Descuento::query()
            ->withCount('reservas')
            ->with('dias')
            ->when($request->boolean('solo_vigentes'), fn ($q) => $q->vigentes($hoy))
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $descuentos,
        ]);
    }

    /**
     * Página pública del salón: las promociones en vigencia, sin datos internos (cuántas
     * reservas la usaron). Las de ciertos días ("los miércoles") se anuncian toda la semana,
     * con sus días. Sin promociones, el mensaje lo muestra la página.
     */
    public function promociones(): JsonResponse
    {
        $hoy = now()->toDateString();

        $vigentes = Descuento::query()
            ->with('dias')
            ->enPeriodo($hoy)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'valor', 'fecha_inicio', 'fecha_fin']);

        $mensaje = null;
        if ($vigentes->isEmpty()) {
            $mensaje = 'No hay promociones vigentes por el momento.';
        }

        return response()->json([
            'data' => $vigentes,
            'message' => $mensaje,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', Rule::in(self::TIPOS)],
            'valor' => ['required', 'numeric', 'gt:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
            // Días en que rige (1 = lunes … 7 = domingo); vacío o sin enviar: todos los días
            'dias_semana' => ['nullable', 'array', 'max:7'],
            'dias_semana.*' => ['integer', 'between:1,7', 'distinct'],
        ], self::MENSAJES);

        $dias = $validated['dias_semana'] ?? [];
        unset($validated['dias_semana']);

        $datos = [...$validated, 'activo' => $validated['activo'] ?? true];
        $this->validarCoherencia($datos);

        $descuento = DB::transaction(function () use ($datos, $dias) {
            $nuevo = Descuento::create($datos);
            $nuevo->guardarDias($dias);

            return $nuevo;
        });

        return response()->json([
            'message' => 'Descuento registrado exitosamente.',
            'data' => $descuento->fresh()->loadCount('reservas'),
        ], 201);
    }

    public function show(Descuento $descuento): JsonResponse
    {
        return response()->json([
            'data' => $descuento->loadCount('reservas'),
        ]);
    }

    public function update(Request $request, Descuento $descuento): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'tipo' => ['sometimes', 'required', 'string', Rule::in(self::TIPOS)],
            'valor' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'activo' => ['sometimes', 'boolean'],
            'dias_semana' => ['sometimes', 'nullable', 'array', 'max:7'],
            'dias_semana.*' => ['integer', 'between:1,7', 'distinct'],
        ], self::MENSAJES);

        $cambianLosDias = array_key_exists('dias_semana', $validated);
        $dias = $validated['dias_semana'] ?? [];
        unset($validated['dias_semana']);

        // Las reglas cruzadas se validan contra el resultado final (lo enviado + lo que ya estaba)
        $this->validarCoherencia([
            'tipo' => $validated['tipo'] ?? $descuento->tipo,
            'valor' => $validated['valor'] ?? $descuento->valor,
            'fecha_inicio' => array_key_exists('fecha_inicio', $validated) ? $validated['fecha_inicio'] : $descuento->fecha_inicio,
            'fecha_fin' => array_key_exists('fecha_fin', $validated) ? $validated['fecha_fin'] : $descuento->fecha_fin,
        ]);

        DB::transaction(function () use ($descuento, $validated, $cambianLosDias, $dias) {
            $descuento->update($validated);
            if ($cambianLosDias) {
                $descuento->guardarDias($dias);
            }
        });

        return response()->json([
            'message' => 'Descuento actualizado exitosamente.',
            'data' => $descuento->fresh()->loadCount('reservas'),
        ]);
    }

    /**
     * Una promoción usada en reservas no se borra: la clave foránea dejaría esas reservas sin
     * el descuento y cambiaría su total. Para retirarla basta con desactivarla.
     */
    public function destroy(Descuento $descuento): JsonResponse
    {
        $usos = $descuento->reservas()->count();

        if ($usos > 0) {
            throw ValidationException::withMessages([
                'descuento' => ["La promoción está aplicada en {$usos} reserva(s) y no se puede eliminar; desactívala para que no se ofrezca en nuevas reservas."],
            ]);
        }

        $descuento->delete();

        return response()->json([
            'message' => 'Descuento eliminado exitosamente.',
        ]);
    }

    /**
     * Un porcentaje no puede pasar de 100 y la vigencia no puede terminar antes de empezar.
     */
    protected function validarCoherencia(array $datos): void
    {
        $errores = [];

        if (($datos['tipo'] ?? null) === 'porcentaje' && (float) $datos['valor'] > 100) {
            $errores['valor'] = ['Un descuento por porcentaje no puede superar el 100 %.'];
        }

        $inicio = $datos['fecha_inicio'] ?? null;
        $fin = $datos['fecha_fin'] ?? null;
        if ($inicio && $fin && strtotime((string) $fin) < strtotime((string) $inicio)) {
            $errores['fecha_fin'] = ['La fecha de fin no puede ser anterior a la fecha de inicio.'];
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }
}
