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

class AnnexSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NotificationMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress(),
            subject: $this->message->subject ?: $this->defaultSubject(),
        );
    }

    public function content(): Content
    {
        $metadata = $this->message->metadata ?? [];

        return new Content(
            view: 'mail.annex.shared',
            with: [
                'notificationMessage' => $this->message,
                'metadata' => $metadata,
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
            )->as($attachment->filename)->withMime($attachment->mime ?: 'text/csv'))
            ->values()
            ->all();
    }

    private function defaultSubject(): string
    {
        $bookLabel = $this->message->metadata['book_label'] ?? $this->message->metadata['book'] ?? null;

        return $bookLabel
            ? "Anexo fiscal compartido: {$bookLabel}"
            : 'Anexo fiscal compartido';
    }

    private function fromAddress(): ?Address
    {
        return $this->message->from_email
            ? new Address($this->message->from_email, $this->message->from_name ?: null)
            : null;
    }
}
