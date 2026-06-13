<?php

namespace App\Mail;

use App\Models\Compania;
use App\Models\VentaCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CotizacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VentaCotizacion $cotizacion,
        public ?Compania $compania,
        public ?string $mensajePersonal = null,
    ) {}

    public function envelope(): Envelope
    {
        $nombre = $this->compania?->nombre ?? config('app.name');

        return new Envelope(
            subject: "Cotización {$this->cotizacion->numero} — {$nombre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cotizacion',
        );
    }
}
