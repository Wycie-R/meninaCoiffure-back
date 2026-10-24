<?php

use App\Services\AvisoTelegram;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Paso para vincular Telegram: la dueña le escribe "/start" al bot y este comando muestra su chat
Artisan::command('telegram:chats', function (AvisoTelegram $telegram) {
    if (blank(config('services.telegram.token'))) {
        $this->error('Falta TELEGRAM_BOT_TOKEN en el .env.');
        return 1;
    }

    $chats = $telegram->chatsRecientes();
    if (count($chats) === 0) {
        $this->warn('Nadie le escribió al bot todavía. Abre el bot en Telegram, toca Iniciar y vuelve a correr este comando.');
        return 0;
    }

    $this->table(['TELEGRAM_CHAT_ID', 'Nombre', 'Último mensaje'], $chats);
    $this->info('Copia el TELEGRAM_CHAT_ID de la dueña en el .env y corre php artisan config:clear.');

    return 0;
})->purpose('Muestra los chats que le escribieron al bot de Telegram');

Artisan::command('telegram:probar', function (AvisoTelegram $telegram) {
    if (!$telegram->configurado()) {
        $this->error('Faltan TELEGRAM_BOT_TOKEN o TELEGRAM_CHAT_ID en el .env.');
        return 1;
    }

    $enviado = $telegram->enviar((string) config('services.telegram.chat_id'), 'Prueba de Menina Coiffure: los avisos de pedidos de cita llegan a este chat.');
    if (!$enviado) {
        $this->error('Telegram no aceptó el mensaje. Revisa el token y el chat (detalle en storage/logs/laravel.log).');
        return 1;
    }

    $this->info('Listo: revisa Telegram.');

    return 0;
})->purpose('Envía un mensaje de prueba al chat de la dueña');
