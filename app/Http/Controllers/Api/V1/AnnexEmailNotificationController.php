<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationMessage;
use App\Services\NotificationMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnexEmailNotificationController extends Controller
{
    public function __invoke(int $empresa, Request $request, NotificationMessageService $messages): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'array'],
            'recipient.email' => ['required', 'email:rfc', 'max:255'],
            'recipient.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'book' => ['required', 'string', 'max:80'],
            'book_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'from' => ['sometimes', 'nullable', 'string', 'max:20'],
            'to' => ['sometimes', 'nullable', 'string', 'max:20'],
            'empresa_nombre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'empresa_nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requested_by' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filename' => ['required', 'string', 'max:255'],
            'content_base64' => ['required', 'string'],
            'cc' => ['sometimes', 'nullable', 'array', 'max:5'],
            'cc.*' => ['email:rfc', 'max:255'],
        ]);

        $message = $messages->queueAnnexEmail($empresa, $validated);

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
            'status' => $message->status,
            'attempts' => $message->attempts,
            'created_at' => optional($message->created_at)->toISOString(),
        ];
    }
}
