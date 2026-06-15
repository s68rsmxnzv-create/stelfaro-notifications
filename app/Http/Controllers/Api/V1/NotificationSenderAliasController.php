<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationSenderAlias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $validated['scope_id'] = $this->scopeId($validated);

        $alias = NotificationSenderAlias::query()->updateOrCreate([
            'scope_type' => $validated['scope_type'],
            'scope_id' => $validated['scope_id'],
            'purpose' => $validated['purpose'],
        ], $validated);

        return response()->json([
            'data' => $this->payload($alias),
        ], 201);
    }

    public function update(NotificationSenderAlias $alias, Request $request): JsonResponse
    {
        $validated = $this->validated($request, partial: true);

        if (isset($validated['scope_type']) || array_key_exists('scope_id', $validated)) {
            $validated['scope_id'] = $this->scopeId([
                'scope_type' => $validated['scope_type'] ?? $alias->scope_type,
                'scope_id' => $validated['scope_id'] ?? $alias->scope_id,
            ]);
        }

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
            'scope_type' => [$required, Rule::in(['global', 'empresa'])],
            'scope_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'purpose' => [$required, 'string', 'max:80', 'regex:/^[a-z0-9_\\.\\-]+$/'],
            'from_email' => [$required, 'email:rfc', 'max:255'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reply_to_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'reply_to_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function scopeId(array $data): int
    {
        if (($data['scope_type'] ?? null) === 'global') {
            return 0;
        }

        if (empty($data['scope_id'])) {
            throw ValidationException::withMessages([
                'scope_id' => 'El scope_id es requerido para alias por empresa.',
            ]);
        }

        return (int) $data['scope_id'];
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
