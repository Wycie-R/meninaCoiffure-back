<?php

namespace App\Services;

use App\Mail\CorreoDelSalon;
use App\Models\SolicitudCita;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Avisa a la dueña por correo cuando una clienta pide una cita. Si no está configurado o el
 * servidor de correo falla, el pedido de la clienta ya quedó guardado igual: el aviso nunca
 * hace fallar nada.
 */
class AvisoCorreo
{
    public function configurado(): bool
    {
        return filled(config('services.avisos.correo_destino'));
    }

    public function avisarNuevaSolicitud(SolicitudCita $solicitud): void
    {
        if (!$this->configurado()) {
            return;
        }

        $solicitud->loadMissing(['cliente', 'servicios']);

        $destino = (string) config('services.avisos.correo_destino');
        $this->enviar($destino, 'Nuevo pedido de cita', $this->textoDeLaSolicitud($solicitud));
    }

    /**
     * Envía un correo de texto. Devuelve true si el servidor de correo lo aceptó.
     */
    public function enviar(string $destino, string $asunto, string $texto): bool
    {
        try {
            Mail::to($destino)->send(new CorreoDelSalon($asunto, $texto));
        } catch (TransportExceptionInterface $excepcion) {
            // Sin credenciales en el log: solo el motivo
            Log::warning('No se pudo enviar el aviso por correo', ['error' => $excepcion->getMessage()]);
            return false;
        }

        return true;
    }

    /**
     * Lo que escribe la clienta va en una sola línea: con saltos de línea podría armar renglones
     * que parezcan del salón (por ejemplo un "Responder en el panel:" con un enlace falso).
     */
    private function enUnaLinea(?string $texto): string
    {
        $sinSaltos = str_replace(["\r\n", "\r", "\n"], ' ', (string) $texto);

        return trim($sinSaltos);
    }

    private function textoDeLaSolicitud(SolicitudCita $solicitud): string
    {
        $cliente = $solicitud->cliente;
        $nombreClienta = $this->enUnaLinea($cliente->nombre . ' ' . $cliente->apellido);
        if ($cliente->telefono) {
            $nombreClienta .= ' (' . $this->enUnaLinea($cliente->telefono) . ')';
        }

        $servicios = [];
        foreach ($solicitud->servicios as $servicio) {
            $servicios[] = $servicio->nombre;
        }

        $dia = Carbon::parse($solicitud->fecha_preferida)->locale('es')->translatedFormat('l d/m');
        if ($solicitud->hora_preferida) {
            $dia .= ' a las ' . substr((string) $solicitud->hora_preferida, 0, 5);
        }
        if ($solicitud->fuera_de_horario) {
            $dia .= ' (fuera de horario)';
        }

        $lugar = 'En el salón';
        if ($solicitud->a_domicilio) {
            $lugar = 'A domicilio: ' . $this->enUnaLinea($solicitud->direccion_domicilio);
        }

        // El enlace del salón va arriba, antes de cualquier texto de la clienta, y sale de APP_URL
        $lineas = [
            'Nuevo pedido de cita',
            '',
            'Responder en el panel: ' . rtrim((string) config('app.url'), '/') . '/dashboard',
            '',
            'Clienta: ' . $nombreClienta,
            'Servicios: ' . implode(', ', $servicios),
            'Día: ' . $dia,
            'Lugar: ' . $lugar,
        ];

        if ($solicitud->a_domicilio && $cliente->latitud !== null) {
            $lineas[] = 'Cómo llegar: https://www.google.com/maps?q=' . $cliente->latitud . ',' . $cliente->longitud;
        }
        // Al final y entre comillas, para que se lea como texto de la clienta y no del salón
        if ($solicitud->comentario) {
            $lineas[] = '';
            $lineas[] = 'Comentario de la clienta: "' . $this->enUnaLinea($solicitud->comentario) . '"';
        }

        return implode("\n", $lineas);
    }
}
