<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendPlatformTemporaryPasswordEmailJob;
use App\Models\NotificationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformTemporaryPasswordEmailNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient.email' => ['required', 'email:rfc', 'max:255'],
            'recipient.name' => ['nullable', 'string', 'max:160'],
            'tenant.id' => ['required', 'integer', 'min:1'],
            'tenant.name' => ['required', 'string', 'max:200'],
            'tenant.slug' => ['nullable', 'string', 'max:160'],
            'user.id' => ['required', 'integer', 'min:1'],
            'user.name' => ['required', 'string', 'max:160'],
            'user.email' => ['required', 'email:rfc', 'max:255'],
            'user.role' => ['required', 'string', 'max:80'],
            'temporary_password.value' => ['required', 'string', 'min:8', 'max:120'],
            'temporary_password.login_url' => ['required', 'url', 'max:2000'],
            'temporary_password.must_change' => ['sometimes', 'boolean'],
            'temporary_password.reason' => ['nullable', 'string', 'max:80'],
            'subject' => ['nullable', 'string', 'max:200'],
            'purpose' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $recipient = $validated['recipient'];
        $tenant = $validated['tenant'];
        $user = $validated['user'];
        $temporaryPassword = $validated['temporary_password'];

        $message = NotificationMessage::query()->create([
            'source_type' => 'platform_temporary_password',
            'source_id' => (int) $user['id'],
            'recipient_email' => $recipient['email'],
            'recipient_name' => $recipient['name'] ?? $user['name'],
            'subject' => $validated['subject'] ?? null,
            'purpose' => $validated['purpose'] ?? 'platform_temporary_password',
            'status' => 'pending',
            'metadata' => [
                'tenant' => $tenant,
                'user' => $user,
                'context' => $validated['metadata'] ?? [],
            ],
            'sensitive_metadata' => [
                'temporary_password' => $temporaryPassword,
            ],
        ]);

        $message->recordEvent('queued', [
            'source' => 'api',
            'user_id' => (int) $user['id'],
            'reason' => $temporaryPassword['reason'] ?? null,
        ]);

        SendPlatformTemporaryPasswordEmailJob::dispatch($message->id);

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
