<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationMessageController extends Controller
{
    /**
     * @var array<string, list<string>>
     */
    private const SOURCE_TYPES_BY_CLIENT = [
        'dte-core' => ['dte', 'mh_fiscal_event', 'annex'],
        'platform-api' => ['platform_invitation', 'platform_temporary_password'],
    ];

    public function show(NotificationMessage $message, Request $request): JsonResponse
    {
        $client = $request->attributes->get('internal_api_client');
        $allowedSourceTypes = self::SOURCE_TYPES_BY_CLIENT[$client] ?? [];

        abort_unless(in_array($message->source_type, $allowedSourceTypes, true), 404);

        return response()->json([
            'data' => $this->payload($message),
        ]);
    }

    public function purposes(): JsonResponse
    {
        $rows = NotificationMessage::query()
            ->selectRaw('purpose, source_type, COUNT(*) as message_count, MAX(created_at) as last_used_at')
            ->groupBy('purpose', 'source_type')
            ->orderByDesc('last_used_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($row): array => [
                'purpose' => $row->purpose,
                'source_type' => $row->source_type,
                'message_count' => (int) $row->message_count,
                'last_used_at' => $row->last_used_at,
            ])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $client = $request->attributes->get('internal_api_client');
        $allowedSourceTypes = self::SOURCE_TYPES_BY_CLIENT[$client] ?? [];

        $validated = $request->validate([
            'source_type' => ['required', 'string'],
            'empresa_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_unless(in_array($validated['source_type'], $allowedSourceTypes, true), 404);

        $paginator = NotificationMessage::query()
            ->where('source_type', $validated['source_type'])
            ->when(isset($validated['empresa_id']), fn ($query) => $query->where('empresa_id', (int) $validated['empresa_id']))
            ->latest('id')
            ->paginate(
                perPage: $validated['per_page'] ?? 15,
                page: $validated['page'] ?? 1,
            );

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (NotificationMessage $message): array => $this->payload($message))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(NotificationMessage $message): array
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
            'provider' => $message->provider,
            'provider_message_id' => $message->provider_message_id,
            'attempts' => $message->attempts,
            'last_error' => $message->last_error,
            'metadata' => $message->metadata,
            'created_at' => optional($message->created_at)->toISOString(),
            'updated_at' => optional($message->updated_at)->toISOString(),
            'sent_at' => optional($message->sent_at)->toISOString(),
            'opened_at' => optional($message->opened_at)->toISOString(),
            'open_count' => $message->open_count,
        ];
    }
}
