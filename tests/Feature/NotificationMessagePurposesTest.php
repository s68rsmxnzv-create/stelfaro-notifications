<?php

namespace Tests\Feature;

use App\Models\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMessagePurposesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_distinct_purposes_actually_used_by_messages(): void
    {
        config(['notifications.internal_tokens' => [[
            'client' => 'platform-api',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        NotificationMessage::query()->create([
            'source_type' => 'annex',
            'source_id' => 1,
            'empresa_id' => 1,
            'recipient_email' => 'a@example.test',
            'purpose' => 'annex_delivery',
            'status' => 'sent',
        ]);
        NotificationMessage::query()->create([
            'source_type' => 'annex',
            'source_id' => 2,
            'empresa_id' => 2,
            'recipient_email' => 'b@example.test',
            'purpose' => 'annex_delivery',
            'status' => 'sent',
        ]);
        NotificationMessage::query()->create([
            'source_type' => 'platform_invitation',
            'source_id' => 3,
            'recipient_email' => 'c@example.test',
            'purpose' => 'platform_invitation',
            'status' => 'sent',
        ]);

        $response = $this
            ->withToken('secret')
            ->getJson('/api/v1/messages/purposes');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $annex = collect($response->json('data'))->firstWhere('purpose', 'annex_delivery');
        $this->assertNotNull($annex);
        $this->assertSame('annex', $annex['source_type']);
        $this->assertSame(2, $annex['message_count']);
        $this->assertNotNull($annex['last_used_at']);
    }

    public function test_it_requires_internal_token(): void
    {
        $this
            ->getJson('/api/v1/messages/purposes')
            ->assertUnauthorized();
    }
}
