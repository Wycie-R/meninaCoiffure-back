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
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i,H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i,H:i:s', 'after:hora_inicio'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['required'],
            'descuento_id' => ['nullable', 'integer', 'exists:descuentos,id'],
            // Una reserva nace pendiente o confirmada; los demás estados se alcanzan después
            'estado' => ['nullable', 'string', 'in:pendiente,confirmada'],
            'a_domicilio' => ['nullable', 'boolean'],
            'direccion_domicilio' => ['nullable', 'required_if_accepted:a_domicilio', 'string', 'max:255'],
            'minutos_traslado' => ['nullable', 'integer', 'min:0', 'max:240'],
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
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'fecha.after_or_equal' => 'La fecha de la reserva no puede ser anterior al día de hoy.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM o HH:MM:SS.',
            'hora_fin.required' => 'La hora de fin es obligatoria.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM o HH:MM:SS.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'servicios.required' => 'Debe incluir al menos un servicio.',
            'servicios.array' => 'Los servicios deben proporcionarse en una lista.',
            'servicios.min' => 'Debe incluir al menos un servicio.',
            'estado.in' => 'Una reserva nueva solo puede quedar pendiente o confirmada.',
            'a_domicilio.boolean' => 'Indica si la reserva es a domicilio con verdadero o falso.',
            'direccion_domicilio.required_if_accepted' => 'La dirección es obligatoria para una reserva a domicilio.',
            'direccion_domicilio.max' => 'La dirección no puede superar los 255 caracteres.',
            'minutos_traslado.max' => 'El traslado no puede superar las 4 horas (240 minutos).',
            'minutos_traslado.min' => 'El traslado no puede ser negativo.',
        ];
    }
}
