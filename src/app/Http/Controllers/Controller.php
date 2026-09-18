<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[
    OA\Info(
        version: "1.0.0",
        title: "Menina Coiffure API",
        description: "Documentación interactiva de la API para el sistema de reservas y gestión de peluquería Menina Coiffure."
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
    //
}

