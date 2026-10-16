<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[
    OA\Info(
        version: "1.0.0",
        title: "Menina Coiffure API",
        description: "Documentación interactiva de la API para el sistema de reservas y gestión de Menina Coiffure (peluquería y estética)."
    ),
    OA\Server(
        url: "http://localhost:8000",
        description: "Servidor de Desarrollo Local"
    ),
    OA\Tag(
        name: "Reservas",
        description: "Operaciones y ciclo de vida del CRUD de Reservas"
    )
]
abstract class Controller
{
    /**
     * Un profesional solo opera sobre las reservas de su propia agenda; la administración, sobre todas.
     */
    protected function autorizarReserva(Request $request, Reserva $reserva): void
    {
        if (!$request->user()?->puedeOperarReserva($reserva)) {
            abort(403, 'Esta reserva pertenece a la agenda de otro profesional.');
        }
    }
}

