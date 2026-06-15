<?php

namespace App\Jobs;

use App\Mail\DteAcceptedMail;
use App\Models\NotificationMessage;
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

    public function handle(CoreApiClient $core, SenderAliasResolver $aliases): void
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
            $this->resolveSenderAlias($message, $aliases);

            $pdf = $core->dtePdf($message->source_id);
            $json = $core->dteClientJson($message->source_id);

            $this->storeAttachment($message, 'pdf', $pdf);
            $this->storeAttachment($message, 'json', $json);

            Mail::to($message->recipient_email, $message->recipient_name)
                ->send(new DteAcceptedMail($message->fresh('attachments')));

            $message->forceFill([
                'status' => 'sent',
                'provider' => (string) config('services.notifications.default_provider', config('mail.default')),
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

        $alias = $aliases->resolve($message->purpose ?: 'dte_delivery', $message->empresa_id);

        if (! $alias) {
            return;
        }

        $message->forceFill([
            'notification_sender_alias_id' => $alias->id,
            'from_email' => $alias->from_email,
            'from_name' => $alias->from_name,
            'reply_to_email' => $alias->reply_to_email,
            'reply_to_name' => $alias->reply_to_name,
        ])->save();
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
