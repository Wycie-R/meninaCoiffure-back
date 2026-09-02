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
            'estado' => ['sometimes', 'string', 'in:pendiente,confirmada,cancelada,completada'],
            'descuento_id' => ['nullable', 'integer', 'exists:descuentos,id'],
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
        ];
    }
}
