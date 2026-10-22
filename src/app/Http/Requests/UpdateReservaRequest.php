<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservaRequest extends FormRequest
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
            'cliente_id' => ['sometimes', 'integer', 'exists:clientes,id'],
            'personal_id' => ['sometimes', 'integer', 'exists:personal,id'],
            'fecha' => ['sometimes', 'date'],
            'hora_inicio' => ['sometimes', 'date_format:H:i,H:i:s'],
            'hora_fin' => ['sometimes', 'date_format:H:i,H:i:s'],
            'estado' => ['sometimes', 'string', 'in:pendiente,confirmada,cancelada,completada,no_asistio'],
            'descuento_id' => ['nullable', 'integer', 'exists:descuentos,id'],
            'a_domicilio' => ['sometimes', 'boolean'],
            'direccion_domicilio' => ['nullable', 'required_if_accepted:a_domicilio', 'string', 'max:255'],
            'minutos_traslado' => ['nullable', 'integer', 'min:0', 'max:240'],
            'fuera_de_horario' => ['sometimes', 'boolean'],
            // Modificar la reserva: la lista reemplaza a los servicios actuales
            'servicios' => ['sometimes', 'array', 'min:1'],
            'servicios.*' => ['required', 'integer', 'distinct', 'exists:servicios,id'],
            // HU-13: observaciones de la atención; al completar quedan en el historial del cliente
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'personal_id.exists' => 'El personal seleccionado no existe.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM o HH:MM:SS.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM o HH:MM:SS.',
            'estado.in' => 'El estado proporcionado no es válido.',
            'direccion_domicilio.required_if_accepted' => 'La dirección es obligatoria para una reserva a domicilio.',
            'direccion_domicilio.max' => 'La dirección no puede superar los 255 caracteres.',
            'minutos_traslado.max' => 'El traslado no puede superar las 4 horas (240 minutos).',
            'minutos_traslado.min' => 'El traslado no puede ser negativo.',
            'servicios.array' => 'Los servicios deben enviarse como una lista.',
            'servicios.min' => 'La reserva debe tener al menos un servicio.',
            'servicios.*.integer' => 'Cada servicio debe indicarse con su número.',
            'servicios.*.distinct' => 'La lista de servicios tiene elementos repetidos.',
            'servicios.*.exists' => 'Uno de los servicios elegidos no existe.',
            'fuera_de_horario.boolean' => 'Indica con verdadero o falso si la cita es fuera de horario.',
        ];
    }
}
