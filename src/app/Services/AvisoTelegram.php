<?php

namespace App\Services;

use App\Models\SolicitudCita;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Avisa a la dueña por Telegram cuando una clienta pide una cita. Usa la API de bots de Telegram
 * con el cliente Http de Laravel (sin paquetes). Si no está configurado o Telegram falla, el
 * pedido de la clienta ya quedó guardado igual: el aviso nunca hace fallar nada.
 */
class AvisoTelegram
{
    private const URL_API = 'https://api.telegram.org/bot';

    public function configurado(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.chat_id'));
    }

    public function avisarNuevaSolicitud(SolicitudCita $solicitud): void
    {
        if (!$this->configurado()) {
            return;
        }

        $solicitud->loadMissing(['cliente', 'servicios']);

        $this->enviar((string) config('services.telegram.chat_id'), $this->textoDeLaSolicitud($solicitud));
    }

    /**
     * Envía un mensaje de texto. Devuelve true si Telegram lo aceptó.
     */
    public function enviar(string $chatId, string $texto): bool
    {
        $token = (string) config('services.telegram.token');
        if ($token === '') {
            return false;
        }

        try {
            $respuesta = Http::timeout(10)->asForm()->post(self::URL_API . $token . '/sendMessage', [
                'chat_id' => $chatId,
                'text' => $texto,
                'disable_web_page_preview' => 'true',
            ]);
        } catch (ConnectionException $excepcion) {
            Log::warning('No se pudo conectar con Telegram', ['error' => $excepcion->getMessage()]);
            return false;
        }

        if ($respuesta->failed()) {
            // Sin el token en el log: es una credencial
            Log::warning('Telegram rechazó el aviso', ['estado' => $respuesta->status(), 'detalle' => $respuesta->json('description')]);
            return false;
        }

        return true;
    }

    /**
     * Quienes le escribieron al bot (por ejemplo, /start): sirve para saber el chat de la dueña.
     *
     * @return array<int, array{chat_id: string, nombre: string, mensaje: string}>
     */
    public function chatsRecientes(): array
    {
        $token = (string) config('services.telegram.token');
        if ($token === '') {
            return [];
        }

        $respuesta = Http::timeout(10)->get(self::URL_API . $token . '/getUpdates');
        if ($respuesta->failed()) {
            return [];
        }

        $chats = [];
        foreach ($respuesta->json('result') ?? [] as $actualizacion) {
            $mensaje = $actualizacion['message'] ?? null;
            if ($mensaje === null) {
                continue;
            }

            $chatId = (string) $mensaje['chat']['id'];
            $nombre = trim(($mensaje['chat']['first_name'] ?? '') . ' ' . ($mensaje['chat']['last_name'] ?? ''));
            $chats[$chatId] = ['chat_id' => $chatId, 'nombre' => $nombre, 'mensaje' => (string) ($mensaje['text'] ?? '')];
        }

        return array_values($chats);
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
