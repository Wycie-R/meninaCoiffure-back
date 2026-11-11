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

    private function textoDeLaSolicitud(SolicitudCita $solicitud): string
    {
        $cliente = $solicitud->cliente;
        $nombreClienta = trim($cliente->nombre . ' ' . $cliente->apellido);
        if ($cliente->telefono) {
            $nombreClienta .= ' (' . $cliente->telefono . ')';
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
            $lugar = 'A domicilio: ' . $solicitud->direccion_domicilio;
        }

        $lineas = [
            'Nuevo pedido de cita',
            '',
            'Clienta: ' . $nombreClienta,
            'Servicios: ' . implode(', ', $servicios),
            'Día: ' . $dia,
            'Lugar: ' . $lugar,
        ];

        if ($solicitud->a_domicilio && $cliente->latitud !== null) {
            $lineas[] = 'Cómo llegar: https://www.google.com/maps?q=' . $cliente->latitud . ',' . $cliente->longitud;
        }
        if ($solicitud->comentario) {
            $lineas[] = 'Comentario: ' . $solicitud->comentario;
        }

        $lineas[] = '';
        $lineas[] = 'Responder en el panel: ' . url('/dashboard');

        return implode("\n", $lineas);
    }
}
