<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'personal_id' => ['required', 'integer', 'exists:personal,id'],
            // Y-m-d exacto: con 'date' pasaban textos como "tomorrow" o "2026-12-01 05:00", que al
            // juntarse con la hora daban error 500
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i,H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i,H:i:s', 'after:hora_inicio'],
            'servicios' => ['required', 'array', 'min:1', 'max:20'],
            // Solo ids, como al modificar: antes se aceptaba {servicio_id, cantidad} con cualquier
            // cantidad, y con 0 o un negativo la reserva quedaba gratis o con total negativo
            'servicios.*' => ['required', 'integer', 'distinct'],
            'descuento_id' => ['nullable', 'integer', 'exists:descuentos,id'],
            // Una reserva nace pendiente o confirmada; los demás estados se alcanzan después
            'estado' => ['nullable', 'string', 'in:pendiente,confirmada'],
            'a_domicilio' => ['nullable', 'boolean'],
            'direccion_domicilio' => ['nullable', 'required_if_accepted:a_domicilio', 'string', 'max:255'],
            // HU-14: minutos de viaje antes y después de una cita a domicilio
            'minutos_traslado' => ['nullable', 'integer', 'min:0', 'max:240'],
            // Regla 2: true si la dueña autoriza atender fuera del horario del salón
            'fuera_de_horario' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'personal_id.required' => 'El personal es obligatorio.',
            'personal_id.exists' => 'El personal seleccionado no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'fecha.after_or_equal' => 'La fecha de la reserva no puede ser anterior al día de hoy.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM o HH:MM:SS.',
            'hora_fin.required' => 'La hora de fin es obligatoria.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM o HH:MM:SS.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'servicios.required' => 'Debe incluir al menos un servicio.',
            'servicios.array' => 'Los servicios deben proporcionarse en una lista.',
            'servicios.min' => 'Debe incluir al menos un servicio.',
            'servicios.max' => 'Una reserva puede tener como máximo 20 servicios.',
            'servicios.*.integer' => 'Cada servicio debe indicarse con su número.',
            'servicios.*.distinct' => 'La lista de servicios tiene elementos repetidos.',
            'estado.in' => 'Una reserva nueva solo puede quedar pendiente o confirmada.',
            'a_domicilio.boolean' => 'Indica si la reserva es a domicilio con verdadero o falso.',
            'direccion_domicilio.required_if_accepted' => 'La dirección es obligatoria para una reserva a domicilio.',
            'direccion_domicilio.max' => 'La dirección no puede superar los 255 caracteres.',
            'minutos_traslado.max' => 'El traslado no puede superar las 4 horas (240 minutos).',
            'minutos_traslado.min' => 'El traslado no puede ser negativo.',
            'fuera_de_horario.boolean' => 'Indica con verdadero o falso si la cita es fuera de horario.',
        ];
    }
}
