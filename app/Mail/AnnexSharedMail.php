<?php

namespace App\Mail;

use App\Models\NotificationMessage;
use App\Support\MessageOpenTrackingToken;
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
            cc: $this->ccAddresses(),
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
                'trackingPixelUrl' => app(MessageOpenTrackingToken::class)->url($this->message),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function defaultSubject(): string
    {
        $labels = collect($this->message->metadata['books'] ?? [])
            ->map(fn (array $book): ?string => $book['book_label'] ?? $book['book'] ?? null)
            ->filter()
            ->values();

        return $labels->isNotEmpty()
            ? 'Anexos fiscales compartidos: '.$labels->implode(', ')
            : 'Anexos fiscales compartidos';
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
    private function ccAddresses(): array
    {
        $cc = $this->message->metadata['cc'] ?? [];

        if (! is_array($cc)) {
            return [];
        }

        return collect($cc)
            ->filter(fn ($email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn (string $email): Address => new Address($email))
            ->values()
            ->all();
    }
}
