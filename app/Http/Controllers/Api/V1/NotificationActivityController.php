<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationAction;
use App\Models\NotificationActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NotificationActivity::query()
            ->with(['actions.senderAlias'])
            ->orderBy('name');

        if ($request->filled('key')) {
            $query->where('key', $request->query('key'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (NotificationActivity $activity): array => $this->activityPayload($activity))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/', Rule::unique('notification_activities', 'key')],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'metadata' => ['sometimes', 'array'],
        ]);

        $activity = NotificationActivity::query()->create($validated + ['status' => 'active']);

        return response()->json([
            'data' => $this->activityPayload($activity->load('actions.senderAlias')),
        ], 201);
    }

    public function storeAction(NotificationActivity $activity, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_\\.\\-]+$/',
                Rule::unique('notification_actions', 'key')->where('notification_activity_id', $activity->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/', Rule::unique('notification_actions', 'purpose')],
            'notification_sender_alias_id' => ['nullable', 'integer', Rule::exists('notification_sender_aliases', 'id')],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'metadata' => ['sometimes', 'array'],
        ]);

        $action = $activity->actions()->create($validated + ['status' => 'active']);

        return response()->json([
            'data' => $this->actionPayload($action->load('senderAlias')),
        ], 201);
    }

    public function updateAction(NotificationAction $action, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'notification_sender_alias_id' => ['sometimes', 'nullable', 'integer', Rule::exists('notification_sender_aliases', 'id')],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'metadata' => ['sometimes', 'array'],
        ]);

        $action->update($validated);

        return response()->json([
            'data' => $this->actionPayload($action->refresh()->load('senderAlias')),
        ]);
    }

    private function activityPayload(NotificationActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'key' => $activity->key,
            'name' => $activity->name,
            'description' => $activity->description,
            'status' => $activity->status,
            'metadata' => $activity->metadata,
            'actions' => $activity->actions
                ->map(fn (NotificationAction $action): array => $this->actionPayload($action))
                ->values(),
            'created_at' => optional($activity->created_at)->toISOString(),
            'updated_at' => optional($activity->updated_at)->toISOString(),
        ];
    }

    private function actionPayload(NotificationAction $action): array
    {
        return [
            'id' => $action->id,
            'notification_activity_id' => $action->notification_activity_id,
            'notification_sender_alias_id' => $action->notification_sender_alias_id,
            'key' => $action->key,
            'name' => $action->name,
            'purpose' => $action->purpose,
            'status' => $action->status,
            'metadata' => $action->metadata,
            'sender_alias' => $action->senderAlias ? [
                'id' => $action->senderAlias->id,
                'purpose' => $action->senderAlias->purpose,
                'from_email' => $action->senderAlias->from_email,
                'from_name' => $action->senderAlias->from_name,
                'is_active' => $action->senderAlias->is_active,
            ] : null,
            'created_at' => optional($action->created_at)->toISOString(),
            'updated_at' => optional($action->updated_at)->toISOString(),
        ];
    }
}
