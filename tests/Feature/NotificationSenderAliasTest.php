<?php

namespace Tests\Feature;

use App\Models\NotificationSenderAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSenderAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_global_sender_alias_for_a_purpose(): void
    {
        config(['notifications.api_token' => 'secret']);

        $response = $this
            ->withToken('secret')
            ->postJson('/api/v1/sender-aliases', [
                'purpose' => 'dte_delivery',
                'from_email' => 'stelfaro.dte@stelfaro.com',
                'from_name' => 'Stelfaro DTE',
                'reply_to_email' => 'soporte@stelfaro.com',
                'reply_to_name' => 'Soporte Stelfaro',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.scope_type', 'global')
            ->assertJsonPath('data.scope_id', 0)
            ->assertJsonPath('data.purpose', 'dte_delivery')
            ->assertJsonPath('data.from_email', 'stelfaro.dte@stelfaro.com');

        $this->assertDatabaseHas('notification_sender_aliases', [
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'dte_delivery',
            'from_email' => 'stelfaro.dte@stelfaro.com',
        ]);
    }

    public function test_it_updates_existing_alias_for_same_scope_and_purpose(): void
    {
        config(['notifications.api_token' => 'secret']);

        NotificationSenderAlias::query()->create([
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'registration',
            'from_email' => 'registro@stelfaro.com',
        ]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/sender-aliases', [
                'purpose' => 'registration',
                'from_email' => 'bienvenida@stelfaro.com',
                'from_name' => 'Registro Stelfaro',
            ])
            ->assertCreated()
            ->assertJsonPath('data.from_email', 'bienvenida@stelfaro.com');

        $this->assertSame(1, NotificationSenderAlias::query()->count());
        $this->assertDatabaseHas('notification_sender_aliases', [
            'purpose' => 'registration',
            'from_email' => 'bienvenida@stelfaro.com',
        ]);
    }

    public function test_it_lists_sender_aliases(): void
    {
        config(['notifications.api_token' => 'secret']);

        NotificationSenderAlias::query()->create([
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'dte_delivery',
            'from_email' => 'stelfaro.dte@stelfaro.com',
        ]);

        $this
            ->withToken('secret')
            ->getJson('/api/v1/sender-aliases?purpose=dte_delivery')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.from_email', 'stelfaro.dte@stelfaro.com');
    }

    public function test_sender_aliases_are_always_global_even_if_scope_is_sent(): void
    {
        config(['notifications.api_token' => 'secret']);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/sender-aliases', [
                'scope_type' => 'empresa',
                'scope_id' => 77,
                'purpose' => 'dte_delivery',
                'from_email' => 'facturacion@empresa.test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.scope_type', 'global')
            ->assertJsonPath('data.scope_id', 0)
            ->assertJsonPath('data.from_email', 'facturacion@empresa.test');

        $this->assertDatabaseHas('notification_sender_aliases', [
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'dte_delivery',
        ]);
    }
}
