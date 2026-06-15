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
        $jobStartedAt = microtime(true);
        $timings = [];
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
            $stageStartedAt = microtime(true);
            $activeTransport = $mailTransport->applyActiveTransport();
            $timings['apply_transport_ms'] = $this->durationMs($stageStartedAt);

            $stageStartedAt = microtime(true);
            $this->resolveSenderAlias($message, $aliases);
            $timings['resolve_alias_ms'] = $this->durationMs($stageStartedAt);

            $stageStartedAt = microtime(true);
            $pdf = $core->dtePdf($message->source_id);
            $timings['fetch_pdf_ms'] = $this->durationMs($stageStartedAt);
            $message->recordEvent('artifact_fetched', [
                'type' => 'pdf',
                'filename' => $pdf->filename,
                'bytes' => strlen($pdf->content),
                'duration_ms' => $timings['fetch_pdf_ms'],
            ]);

            $stageStartedAt = microtime(true);
            $json = $core->dteClientJson($message->source_id);
            $timings['fetch_json_ms'] = $this->durationMs($stageStartedAt);
            $message->recordEvent('artifact_fetched', [
                'type' => 'json',
                'filename' => $json->filename,
                'bytes' => strlen($json->content),
                'duration_ms' => $timings['fetch_json_ms'],
            ]);

            $stageStartedAt = microtime(true);
            $this->storeAttachment($message, 'pdf', $pdf);
            $timings['store_pdf_ms'] = $this->durationMs($stageStartedAt);

            $stageStartedAt = microtime(true);
            $this->storeAttachment($message, 'json', $json);
            $timings['store_json_ms'] = $this->durationMs($stageStartedAt);

            $stageStartedAt = microtime(true);
            Mail::to($message->recipient_email, $message->recipient_name)
                ->send(new DteAcceptedMail($message->fresh('attachments')));
            $timings['smtp_send_ms'] = $this->durationMs($stageStartedAt);
            $message->recordEvent('smtp_sent', [
                'duration_ms' => $timings['smtp_send_ms'],
            ]);

            $message->forceFill([
                'status' => 'sent',
                'provider' => $activeTransport?->name ?? (string) config('services.notifications.default_provider', config('mail.default')),
                'metadata' => $this->mergeMetadata($message->metadata ?? [], [
                    'timing' => $this->timingPayload($timings, $jobStartedAt),
                ]),
                'sent_at' => now(),
            ])->save();
            $message->recordEvent('sent', [
                'attempt' => $message->attempts,
                'duration_ms' => $this->durationMs($jobStartedAt),
                'timing' => $timings,
            ]);
        } catch (Throwable $exception) {
            $message->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
                'metadata' => $this->mergeMetadata($message->metadata ?? [], [
                    'timing' => $this->timingPayload($timings, $jobStartedAt),
                ]),
            ])->save();
            $message->recordEvent('failed', [
                'attempt' => $message->attempts,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->durationMs($jobStartedAt),
                'timing' => $timings,
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

    /**
     * @param  array<string, int>  $timings
     * @return array<string, mixed>
     */
    private function timingPayload(array $timings, float $startedAt): array
    {
        return $timings + [
            'total_ms' => $this->durationMs($startedAt),
            'completed_at' => now()->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $next
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $current, array $next): array
    {
        return array_replace_recursive($current, $next);
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
