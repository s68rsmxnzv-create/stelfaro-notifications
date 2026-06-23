<?php

namespace App\Mail;

use App\Models\NotificationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformTemporaryPasswordMail extends Mailable
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
        $sensitive = $this->message->sensitive_metadata ?? [];

        return new Content(
            view: 'mail.platform.temporary-password',
            with: [
                'notificationMessage' => $this->message,
                'tenant' => $metadata['tenant'] ?? [],
                'user' => $metadata['user'] ?? [],
                'temporaryPassword' => $sensitive['temporary_password']['value'] ?? null,
                'loginUrl' => $sensitive['temporary_password']['login_url'] ?? null,
            ],
        );
    }

    private function defaultSubject(): string
    {
        $tenantName = data_get($this->message->metadata, 'tenant.name');

        return $tenantName
            ? "Acceso temporal para {$tenantName}"
            : 'Acceso temporal a StelFaro';
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
}
