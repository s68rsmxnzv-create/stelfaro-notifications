<?php

namespace App\Jobs;

use App\Mail\DteAcceptedMail;
use App\Models\NotificationMessage;
use App\Services\MailTransportConfigurator;
use App\Services\SenderAliasResolver;
use App\Support\Core\CoreApiClient;
use App\Support\Core\CoreArtifact;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendDteEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $messageId) {}

    public function handle(CoreApiClient $core, SenderAliasResolver $aliases, MailTransportConfigurator $mailTransport): void
    {
        $message = NotificationMessage::query()->findOrFail($this->messageId);

        if ($message->status === 'sent') {
            return;
        }

        $message->forceFill([
            'status' => $message->attempts > 0 ? 'retrying' : 'processing',
            'attempts' => $message->attempts + 1,
            'last_error' => null,
        ])->save();
        $message->recordEvent('processing', ['attempt' => $message->attempts]);

        try {
            $activeTransport = $mailTransport->applyActiveTransport();
            $this->resolveSenderAlias($message, $aliases);

            $pdf = $core->dtePdf($message->source_id);
            $json = $core->dteClientJson($message->source_id);

            $this->storeAttachment($message, 'pdf', $pdf);
            $this->storeAttachment($message, 'json', $json);

            Mail::to($message->recipient_email, $message->recipient_name)
                ->send(new DteAcceptedMail($message->fresh('attachments')));

            $message->forceFill([
                'status' => 'sent',
                'provider' => $activeTransport?->name ?? (string) config('services.notifications.default_provider', config('mail.default')),
                'sent_at' => now(),
            ])->save();
            $message->recordEvent('sent', ['attempt' => $message->attempts]);
        } catch (Throwable $exception) {
            $message->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ])->save();
            $message->recordEvent('failed', [
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

        $alias = $aliases->resolve($message->purpose ?: 'dte_delivery');

        if (! $alias) {
            return;
        }

        $message->forceFill([
            'notification_sender_alias_id' => $alias->id,
            'from_email' => $alias->from_email,
            'from_name' => $this->senderName($message),
            'reply_to_email' => null,
            'reply_to_name' => null,
        ])->save();
    }

    private function senderName(NotificationMessage $message): string
    {
        $metadata = $message->metadata ?? [];
        $context = is_array($metadata['context'] ?? null) ? $metadata['context'] : [];

        return (string) (
            $metadata['empresa_nombre_comercial']
            ?? $metadata['empresa_nombre']
            ?? data_get($context, 'empresa.nombre_comercial')
            ?? data_get($context, 'empresa.razon_social')
            ?? data_get($context, 'empresa.nombre')
            ?? config('mail.from.name', 'StelFaro')
        );
    }

    private function storeAttachment(NotificationMessage $message, string $type, CoreArtifact $artifact): void
    {
        $disk = (string) config('notifications.attachments.disk', 'local');
        $basePath = trim((string) config('notifications.attachments.path', 'notifications'), '/');
        $path = "{$basePath}/{$message->id}/{$artifact->filename}";

        Storage::disk($disk)->put($path, $artifact->content);

        $message->attachments()->updateOrCreate([
            'type' => $type,
        ], [
            'filename' => $artifact->filename,
            'mime' => $artifact->contentType,
            'content_hash' => hash('sha256', $artifact->content),
            'disk' => $disk,
            'storage_path' => $path,
            'source_url' => $artifact->sourceUrl,
        ]);
    }
}
