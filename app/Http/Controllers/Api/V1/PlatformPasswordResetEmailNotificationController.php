<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendPlatformPasswordResetEmailJob;
use App\Models\NotificationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformPasswordResetEmailNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient.email' => ['required', 'email:rfc', 'max:255'],
            'recipient.name' => ['nullable', 'string', 'max:160'],
            'reset.url' => ['required', 'url', 'max:2000'],
            'reset.expires_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'subject' => ['nullable', 'string', 'max:200'],
            'purpose' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $recipient = $validated['recipient'];
        $reset = $validated['reset'];

        $message = NotificationMessage::query()->create([
            'source_type' => 'platform_password_reset',
            'source_id' => 0,
            'recipient_email' => $recipient['email'],
            'recipient_name' => $recipient['name'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'purpose' => $validated['purpose'] ?? 'platform_password_reset',
            'status' => 'pending',
            'metadata' => [
                'expires_minutes' => $reset['expires_minutes'] ?? 60,
                'context' => $validated['metadata'] ?? [],
            ],
            'sensitive_metadata' => [
                'reset_url' => $reset['url'],
            ],
        ]);

        $message->recordEvent('queued', [
            'source' => 'api',
            'recipient_email' => $recipient['email'],
        ]);

        SendPlatformPasswordResetEmailJob::dispatch($message->id);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'status' => $message->status,
                'purpose' => $message->purpose,
                'recipient_email' => $message->recipient_email,
            ],
        ], 202);
    }
}
