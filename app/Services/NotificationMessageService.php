<?php

namespace App\Services;

use App\Jobs\SendAnnexEmailJob;
use App\Jobs\SendDteEmailJob;
use App\Models\NotificationMessage;
use Illuminate\Support\Facades\DB;

class NotificationMessageService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function queueDteEmail(int $documentId, array $data): NotificationMessage
    {
        return $this->queueEmail('dte', $documentId, $data, 'document_id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function queueAnnexEmail(int $empresaId, array $data): NotificationMessage
    {
        $recipient = $data['recipient'];
        $links = $data['links'];

        $books = collect($links)
            ->unique(fn (array $link): string => $link['book'])
            ->map(fn (array $link): array => [
                'book' => $link['book'],
                'book_label' => $link['book_label'] ?? null,
            ])
            ->values()
            ->all();

        $message = NotificationMessage::query()->create([
            'source_type' => 'annex',
            'source_id' => $empresaId,
            'empresa_id' => $empresaId,
            'recipient_email' => $recipient['email'],
            'recipient_name' => $recipient['name'] ?? null,
            'subject' => $data['subject'] ?? null,
            'purpose' => 'annex_delivery',
            'status' => 'pending',
            'metadata' => [
                'books' => $books,
                'links' => array_values($links),
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
                'empresa_nombre' => $data['empresa_nombre'] ?? null,
                'empresa_nombre_comercial' => $data['empresa_nombre_comercial'] ?? null,
                'requested_by' => $data['requested_by'] ?? null,
                'cc' => array_values($data['cc'] ?? []),
            ],
        ]);

        $message->recordEvent('queued', ['source' => 'api', 'empresa_id' => $empresaId]);

        SendAnnexEmailJob::dispatch($message->id);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function queueMhFiscalEventEmail(int $eventId, array $data): NotificationMessage
    {
        return $this->queueEmail('mh_fiscal_event', $eventId, $data, 'event_id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function queueEmail(string $sourceType, int $sourceId, array $data, string $eventContextKey): NotificationMessage
    {
        $recipient = $data['recipient'];

        $message = DB::transaction(function () use ($sourceType, $sourceId, $data, $recipient, $eventContextKey): NotificationMessage {
            $message = NotificationMessage::query()->create([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'empresa_id' => $data['empresa_id'] ?? null,
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'] ?? null,
                'subject' => $data['subject'] ?? null,
                'purpose' => $data['purpose'] ?? 'dte_delivery',
                'status' => 'pending',
                'metadata' => [
                    'tipo_dte' => $data['tipo_dte'] ?? null,
                    'event_type' => $data['event_type'] ?? null,
                    'numero_control' => $data['numero_control'] ?? null,
                    'codigo_generacion' => $data['codigo_generacion'] ?? null,
                    'empresa_nombre' => $data['empresa_nombre'] ?? null,
                    'empresa_nombre_comercial' => $data['empresa_nombre_comercial'] ?? null,
                    'requested_by' => $data['requested_by'] ?? null,
                    'context' => $data['metadata'] ?? [],
                ],
            ]);

            $message->recordEvent('queued', [
                'source' => 'api',
                $eventContextKey => $sourceId,
            ]);

            return $message;
        });

        SendDteEmailJob::dispatch($message->id);

        return $message;
    }

    public function dispatchMessagesWaitingForTransport(): int
    {
        $messages = NotificationMessage::query()
            ->where('status', 'waiting_transport')
            ->whereNull('sent_at')
            ->get(['id']);

        foreach ($messages as $message) {
            SendDteEmailJob::dispatch($message->id);
        }

        return $messages->count();
    }
}
