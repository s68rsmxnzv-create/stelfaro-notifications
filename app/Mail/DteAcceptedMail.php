<?php

namespace App\Mail;

use App\Models\NotificationAttachment;
use App\Models\NotificationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DteAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NotificationMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message->subject ?: $this->defaultSubject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.dte.accepted',
            with: [
                'message' => $this->message,
                'metadata' => $this->message->metadata ?? [],
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
}
