<?php

namespace App\Mail;

use App\Models\NotificationAttachment;
use App\Models\NotificationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DteAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NotificationMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress(),
            replyTo: $this->replyToAddress(),
            subject: $this->message->subject ?: $this->defaultSubject(),
        );
    }

    public function content(): Content
    {
        $metadata = $this->message->metadata ?? [];

        return new Content(
            view: 'mail.dte.accepted',
            with: [
                'notificationMessage' => $this->message,
                'metadata' => $metadata,
                'queryUrl' => $this->queryUrl($metadata),
                'whatsappUrl' => config('notifications.marketing.whatsapp_url', 'https://wa.me/50375640652'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->message->attachments
            ->map(fn (NotificationAttachment $attachment): Attachment => Attachment::fromStorageDisk(
                $attachment->disk ?: config('notifications.attachments.disk', 'local'),
                (string) $attachment->storage_path,
            )->as($attachment->filename)->withMime($attachment->mime ?: 'application/octet-stream'))
            ->values()
            ->all();
    }

    private function defaultSubject(): string
    {
        $numeroControl = $this->message->metadata['numero_control'] ?? null;

        return $numeroControl
            ? "Documento tributario electronico {$numeroControl}"
            : 'Documento tributario electronico';
    }

    private function fromAddress(): ?Address
    {
        return $this->message->from_email
            ? new Address($this->message->from_email, $this->message->from_name ?: null)
            : null;
    }

    /**
     * @return array<int, Address>
     */
    private function replyToAddress(): array
    {
        return $this->message->reply_to_email
            ? [new Address($this->message->reply_to_email, $this->message->reply_to_name ?: null)]
            : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function queryUrl(array $metadata): ?string
    {
        $codigoGeneracion = $metadata['codigo_generacion'] ?? null;

        if (! is_scalar($codigoGeneracion) || blank($codigoGeneracion)) {
            return null;
        }

        $context = Arr::wrap($metadata['context'] ?? []);

        if (($context['query_enabled'] ?? true) === false) {
            return null;
        }

        $ambiente = $context['ambiente'] ?? null;
        $fechaEmision = $context['fecha_emi'] ?? $context['fec_emi'] ?? null;
        $publicQueryUrl = rtrim((string) config('notifications.dte.public_query_url', 'https://admin.factura.gob.sv/consultaPublica'), '?');

        $query = http_build_query([
            'ambiente' => is_scalar($ambiente) ? (string) $ambiente : '',
            'codGen' => Str::upper((string) $codigoGeneracion),
            'fechaEmi' => is_scalar($fechaEmision) ? (string) $fechaEmision : '',
        ], '', '&', PHP_QUERY_RFC3986);

        return $publicQueryUrl.'?'.$query;
    }
}
