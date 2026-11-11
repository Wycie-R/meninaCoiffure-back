<?php

use App\Services\AvisoCorreo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Comprueba la configuración de correo (MAIL_* en el .env). Sin argumento usa AVISOS_CORREO_DESTINO
Artisan::command('correo:probar {destino?}', function (AvisoCorreo $aviso) {
    $destino = $this->argument('destino');
    if (blank($destino)) {
        $destino = config('services.avisos.correo_destino');
    }

    if (blank($destino)) {
        $this->error('Falta el destino: pásalo como argumento o completa AVISOS_CORREO_DESTINO en el .env.');
        return 1;
    }

    $enviado = $aviso->enviar(
        (string) $destino,
        'Prueba de Menina Coiffure',
        'Prueba de Menina Coiffure: los avisos de pedidos de cita llegan a este correo.'
    );

    if (!$enviado) {
        $this->error('El servidor de correo no aceptó el mensaje. Revisa MAIL_* en el .env (detalle en storage/logs/laravel.log).');
        return 1;
    }

    // Con MAIL_MAILER=log no sale nada a internet: se avisa para no buscar un correo que no llegará
    if (config('mail.default') === 'log') {
        $this->warn('MAIL_MAILER=log: el correo no se envió, quedó escrito en storage/logs/laravel.log.');
        return 0;
    }

    $this->info('Listo: revisa la bandeja de ' . $destino . ' (y la carpeta de spam).');

    return 0;
})->purpose('Envía un correo de prueba para comprobar la configuración de correo');
