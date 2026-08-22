<?php

namespace Tests\Feature;

use App\Models\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMessageIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);
    }

    public function test_it_lists_annex_messages_for_an_empresa_paginated(): void
    {
        $this->annexMessage(empresaId: 7, email: 'a@example.test');
        $this->annexMessage(empresaId: 7, email: 'b@example.test');
        $this->annexMessage(empresaId: 9, email: 'other-empresa@example.test');
        $this->dteMessage(empresaId: 7);

        $response = $this->withToken('secret')
            ->getJson('/api/v1/messages?source_type=annex&empresa_id=7&per_page=1&page=1')
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_email', 'b@example.test')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_it_rejects_source_types_not_allowed_for_the_client(): void
    {
        $this->withToken('secret')
            ->getJson('/api/v1/messages?source_type=platform_invitation')
            ->assertNotFound();
    }

    private function annexMessage(int $empresaId, string $email): NotificationMessage
    {
        return NotificationMessage::query()->create([
            'source_type' => 'annex',
            'source_id' => $empresaId,
            'empresa_id' => $empresaId,
            'recipient_email' => $email,
            'status' => 'sent',
            'purpose' => 'annex_delivery',
            'metadata' => ['books' => [['book' => 'ventas_contribuyente', 'book_label' => 'Ventas a contribuyentes']]],
        ]);
    }

    private function dteMessage(int $empresaId): NotificationMessage
    {
        return NotificationMessage::query()->create([
            'source_type' => 'dte',
            'source_id' => 1,
            'empresa_id' => $empresaId,
            'recipient_email' => 'cliente@example.test',
            'status' => 'sent',
            'purpose' => 'dte_delivery',
        ]);
    }
}
