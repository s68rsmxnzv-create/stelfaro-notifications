<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendPlatformInvitationEmailJob;
use App\Models\NotificationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformInvitationEmailNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient.email' => ['required', 'email:rfc', 'max:255'],
            'recipient.name' => ['nullable', 'string', 'max:160'],
            'tenant.id' => ['required', 'integer', 'min:1'],
            'tenant.name' => ['required', 'string', 'max:200'],
            'tenant.slug' => ['nullable', 'string', 'max:160'],
            'invitation.id' => ['required', 'integer', 'min:1'],
            'invitation.role' => ['required', 'string', 'max:80'],
            'invitation.status' => ['nullable', 'string', 'max:80'],
            'invitation.expires_at' => ['nullable', 'date'],
            'invitation.accept_url' => ['required', 'url', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:200'],
            'purpose' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $recipient = $validated['recipient'];
        $tenant = $validated['tenant'];
        $invitation = $validated['invitation'];

        $message = NotificationMessage::query()->create([
            'source_type' => 'platform_invitation',
            'source_id' => (int) $invitation['id'],
            'recipient_email' => $recipient['email'],
            'recipient_name' => $recipient['name'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'purpose' => $validated['purpose'] ?? 'platform_invitation',
            'status' => 'pending',
            'metadata' => [
                'tenant' => $tenant,
                'invitation' => $invitation,
                'context' => $validated['metadata'] ?? [],
            ],
        ]);

        $message->recordEvent('queued', [
            'source' => 'api',
            'invitation_id' => (int) $invitation['id'],
        ]);

        SendPlatformInvitationEmailJob::dispatch($message->id);

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
