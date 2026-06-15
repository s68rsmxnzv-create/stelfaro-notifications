<?php

namespace Tests\Feature;

use App\Models\NotificationMailTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMailTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_active_smtp_transport_without_exposing_password(): void
    {
        config(['notifications.api_token' => 'secret']);

        $response = $this
            ->withToken('secret')
            ->postJson('/api/v1/mail-transport', [
                'name' => 'Hostinger Stelfaro',
                'host' => 'smtp.hostinger.com',
                'port' => 465,
                'scheme' => 'ssl',
                'username' => 'facturaciondte@stelfaro.com',
                'password' => 'secret-password',
                'default_from_email' => 'facturaciondte@stelfaro.com',
                'default_from_name' => 'StelFaro',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Hostinger Stelfaro')
            ->assertJsonPath('data.password_configured', true)
            ->assertJsonMissingPath('data.password');

        $transport = NotificationMailTransport::query()->firstOrFail();

        $this->assertSame('secret-password', $transport->password);
        $this->assertNotSame('secret-password', $transport->getRawOriginal('password'));
    }

    public function test_it_keeps_previous_password_when_updating_without_password(): void
    {
        config(['notifications.api_token' => 'secret']);

        NotificationMailTransport::query()->create([
            'name' => 'Hostinger Stelfaro',
            'host' => 'smtp.hostinger.com',
            'port' => 465,
            'scheme' => 'ssl',
            'username' => 'facturaciondte@stelfaro.com',
            'password' => 'secret-password',
            'default_from_email' => 'facturaciondte@stelfaro.com',
            'default_from_name' => 'StelFaro',
            'is_active' => true,
        ]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/mail-transport', [
                'name' => 'Hostinger Stelfaro actualizado',
                'host' => 'smtp.hostinger.com',
                'port' => 465,
                'scheme' => 'ssl',
                'username' => 'facturaciondte@stelfaro.com',
                'default_from_email' => 'facturaciondte@stelfaro.com',
                'default_from_name' => 'StelFaro',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Hostinger Stelfaro actualizado');

        $active = NotificationMailTransport::query()->where('is_active', true)->firstOrFail();

        $this->assertSame('secret-password', $active->password);
        $this->assertSame(1, NotificationMailTransport::query()->where('is_active', true)->count());
    }
}
