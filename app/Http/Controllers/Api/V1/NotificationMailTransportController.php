<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationMailTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationMailTransportController extends Controller
{
    public function show(): JsonResponse
    {
        $transport = NotificationMailTransport::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        return response()->json([
            'data' => $transport ? $this->payload($transport) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $current = NotificationMailTransport::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['required', 'string', 'in:ssl,tls,null'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_from_email' => ['required', 'email:rfc', 'max:255'],
            'default_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if (($validated['password'] ?? null) === null && ! $current) {
            return response()->json([
                'message' => 'La contrasena SMTP es requerida para la primera configuracion.',
                'errors' => [
                    'password' => ['La contrasena SMTP es requerida para la primera configuracion.'],
                ],
            ], 422);
        }

        $transport = DB::transaction(function () use ($validated, $current): NotificationMailTransport {
            NotificationMailTransport::query()->update(['is_active' => false]);

            $payload = [
                ...$validated,
                'mailer' => 'smtp',
                'scheme' => $validated['scheme'] === 'null' ? null : $validated['scheme'],
                'is_active' => true,
            ];

            if (($payload['password'] ?? null) === null && $current) {
                $payload['password'] = $current->password;
            }

            return NotificationMailTransport::query()->create($payload);
        });

        return response()->json([
            'data' => $this->payload($transport),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(NotificationMailTransport $transport): array
    {
        return [
            'id' => $transport->id,
            'name' => $transport->name,
            'mailer' => $transport->mailer,
            'host' => $transport->host,
            'port' => $transport->port,
            'scheme' => $transport->scheme,
            'username' => $transport->username,
            'password_configured' => $transport->password !== null,
            'default_from_email' => $transport->default_from_email,
            'default_from_name' => $transport->default_from_name,
            'is_active' => $transport->is_active,
            'last_verified_at' => optional($transport->last_verified_at)->toISOString(),
            'metadata' => $transport->metadata,
            'created_at' => optional($transport->created_at)->toISOString(),
            'updated_at' => optional($transport->updated_at)->toISOString(),
        ];
    }
}
