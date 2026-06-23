<?php

namespace App\Jobs;

use App\Mail\PlatformTemporaryPasswordMail;
use App\Models\NotificationMessage;
use App\Services\MailTransportConfigurator;
use App\Services\SenderAliasResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPlatformTemporaryPasswordEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $messageId) {}

    public function handle(SenderAliasResolver $aliases, MailTransportConfigurator $mailTransport): void
    {
        $message = NotificationMessage::query()->findOrFail($this->messageId);

        if ($message->status === 'sent') {
            return;
        }

        try {
            $activeTransport = $mailTransport->applyActiveTransport();

            if (! $activeTransport) {
                $message->forceFill([
                    'status' => 'waiting_transport',
                    'last_error' => 'No hay transporte SMTP activo configurado.',
                    'metadata' => array_replace_recursive($message->metadata ?? [], [
                        'delivery_blocker' => 'mail_transport_missing',
                        'waiting_transport_since' => data_get($message->metadata, 'waiting_transport_since') ?? now()->toISOString(),
                    ]),
                ])->save();
                $message->recordEvent('waiting_transport', ['reason' => 'mail_transport_missing']);

                $this->release(600);

                return;
            }

            $message->forceFill([
                'status' => $message->attempts > 0 ? 'retrying' : 'processing',
                'attempts' => $message->attempts + 1,
                'last_error' => null,
            ])->save();
            $message->recordEvent('processing', ['attempt' => $message->attempts]);

            $this->resolveSenderAlias($message, $aliases);

            Mail::to($message->recipient_email, $message->recipient_name)
                ->send(new PlatformTemporaryPasswordMail($message->fresh()));

            $message->forceFill([
                'status' => 'sent',
                'provider' => $activeTransport?->name ?? (string) config('services.notifications.default_provider', config('mail.default')),
                'sent_at' => now(),
            ])->save();
            $message->recordEvent('sent', ['attempt' => $message->attempts]);
        } catch (Throwable $exception) {
            $willRetry = $this->attempts() < $this->tries;

            $message->forceFill([
                'status' => $willRetry ? 'retrying' : 'failed',
                'last_error' => $exception->getMessage(),
            ])->save();
            $message->recordEvent($willRetry ? 'retry_scheduled' : 'failed', [
                'attempt' => $message->attempts,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function resolveSenderAlias(NotificationMessage $message, SenderAliasResolver $aliases): void
    {
        if ($message->from_email) {
            return;
        }

        $alias = $aliases->resolve($message->purpose ?: 'platform_temporary_password');

        if (! $alias) {
            return;
        }

        $message->forceFill([
            'notification_sender_alias_id' => $alias->id,
            'from_email' => $alias->from_email,
            'from_name' => $alias->from_name ?: config('mail.from.name', 'StelFaro'),
            'reply_to_email' => $alias->reply_to_email,
            'reply_to_name' => $alias->reply_to_name,
        ])->save();
    }
}
