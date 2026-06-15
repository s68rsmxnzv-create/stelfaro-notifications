<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationSenderAlias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSenderAliasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NotificationSenderAlias::query()
            ->orderBy('scope_type')
            ->orderBy('scope_id')
            ->orderBy('purpose');

        if ($request->filled('scope_type')) {
            $query->where('scope_type', $request->query('scope_type'));
        }

        if ($request->filled('scope_id')) {
            $query->where('scope_id', (int) $request->query('scope_id'));
        }

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->query('purpose'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (NotificationSenderAlias $alias): array => $this->payload($alias))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $validated['scope_type'] = 'global';
        $validated['scope_id'] = 0;

        $alias = NotificationSenderAlias::query()->updateOrCreate([
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => $validated['purpose'],
        ], $validated);

        return response()->json([
            'data' => $this->payload($alias),
        ], 201);
    }

    public function update(NotificationSenderAlias $alias, Request $request): JsonResponse
    {
        $validated = $this->validated($request, partial: true);
        unset($validated['scope_type'], $validated['scope_id']);

        $alias->update($validated);

        return response()->json([
            'data' => $this->payload($alias->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'purpose' => [$required, 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/'],
            'from_email' => [$required, 'email:rfc', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(NotificationSenderAlias $alias): array
    {
        return [
            'id' => $alias->id,
            'scope_type' => $alias->scope_type,
            'scope_id' => $alias->scope_id,
            'purpose' => $alias->purpose,
            'from_email' => $alias->from_email,
            'from_name' => $alias->from_name,
            'reply_to_email' => $alias->reply_to_email,
            'reply_to_name' => $alias->reply_to_name,
            'is_active' => $alias->is_active,
            'metadata' => $alias->metadata,
            'created_at' => optional($alias->created_at)->toISOString(),
            'updated_at' => optional($alias->updated_at)->toISOString(),
        ];
    }
}
