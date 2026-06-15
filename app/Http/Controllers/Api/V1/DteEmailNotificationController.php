<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationMessage;
use App\Services\NotificationMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DteEmailNotificationController extends Controller
{
    public function __invoke(int $document, Request $request, NotificationMessageService $messages): JsonResponse
    {
        $validated = $request->validate([
            'empresa_id' => ['sometimes', 'nullable', 'integer'],
            'recipient' => ['required', 'array'],
            'recipient.email' => ['required', 'email:rfc', 'max:255'],
            'recipient.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tipo_dte' => ['sometimes', 'nullable', 'string', 'max:8'],
            'numero_control' => ['sometimes', 'nullable', 'string', 'max:80'],
            'codigo_generacion' => ['sometimes', 'nullable', 'string', 'max:80'],
            'requested_by' => ['sometimes', 'nullable', 'string', 'max:120'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $message = $messages->queueDteEmail($document, $validated);

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
            'status' => $message->status,
            'attempts' => $message->attempts,
            'created_at' => optional($message->created_at)->toISOString(),
        ];
    }
}
