<?php

namespace App\Mail;

use App\Models\NotificationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NotificationMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress(),
            replyTo: $this->replyToAddress(),
            subject: $this->message->subject ?: 'Restablece tu contrasena de StelFaro',
        );
    }

    public function content(): Content
    {
        $sensitive = $this->message->sensitive_metadata ?? [];

        return new Content(
            view: 'mail.platform.password-reset',
            with: [
                'notificationMessage' => $this->message,
                'recipientName' => $this->message->recipient_name,
                'resetUrl' => $sensitive['reset_url'] ?? null,
                'expiresMinutes' => (int) data_get($this->message->metadata, 'expires_minutes', 60),
            ],
        );
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
