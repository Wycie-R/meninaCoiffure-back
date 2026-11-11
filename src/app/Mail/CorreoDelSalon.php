<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de texto simple del salón: aviso de pedidos de cita a la dueña y enlace para
 * crear una contraseña nueva. Un solo formato para todo: asunto y texto.
 */
class CorreoDelSalon extends Mailable
{
    public function __construct(
        public string $asunto,
        public string $texto,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(text: 'correos.texto');
    }
}
