<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationMessage;
use Illuminate\Http\JsonResponse;

class NotificationMessageController extends Controller
{
    public function show(NotificationMessage $message): JsonResponse
    {
        return response()->json([
            'data' => [
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
                'provider' => $message->provider,
                'provider_message_id' => $message->provider_message_id,
                'attempts' => $message->attempts,
                'last_error' => $message->last_error,
                'created_at' => optional($message->created_at)->toISOString(),
                'updated_at' => optional($message->updated_at)->toISOString(),
                'sent_at' => optional($message->sent_at)->toISOString(),
            ],
        ]);
    }
}
