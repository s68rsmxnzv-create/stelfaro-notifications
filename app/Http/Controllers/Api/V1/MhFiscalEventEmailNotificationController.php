<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationMessage;
use App\Services\NotificationMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MhFiscalEventEmailNotificationController extends Controller
{
    public function __invoke(int $event, Request $request, NotificationMessageService $messages): JsonResponse
    {
        $validated = $request->validate([
            'empresa_id' => ['sometimes', 'nullable', 'integer'],
            'recipient' => ['required', 'array'],
            'recipient.email' => ['required', 'email:rfc', 'max:255'],
            'recipient.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'purpose' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/'],
            'event_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'numero_control' => ['sometimes', 'nullable', 'string', 'max:80'],
            'codigo_generacion' => ['sometimes', 'nullable', 'string', 'max:80'],
            'empresa_nombre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'empresa_nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requested_by' => ['sometimes', 'nullable', 'string', 'max:120'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $message = $messages->queueMhFiscalEventEmail($event, $validated);

        return response()->json([
            'data' => $this->messagePayload($message),
        ], 202);
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(NotificationMessage $message): array
    {
        return [
            'id' => $message->id,
            'source_type' => $message->source_type,
            'source_id' => $message->source_id,
            'empresa_id' => $message->empresa_id,
            'recipient_email' => $message->recipient_email,
            'recipient_name' => $message->recipient_name,
            'subject' => $message->subject,
            'purpose' => $message->purpose,
            'from_email' => $message->from_email,
            'from_name' => $message->from_name,
            'status' => $message->status,
            'attempts' => $message->attempts,
            'created_at' => optional($message->created_at)->toISOString(),
        ];
    }
}
