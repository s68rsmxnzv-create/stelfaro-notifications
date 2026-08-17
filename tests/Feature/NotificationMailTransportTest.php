<?php

namespace Tests\Feature;

use App\Models\NotificationMailTransport;
use App\Models\NotificationMessage;
use App\Jobs\SendDteEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationMailTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_active_smtp_transport_without_exposing_password(): void
    {
        config(['notifications.internal_tokens' => [[
            'client' => 'platform-api',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

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

    public function test_dte_core_client_cannot_reconfigure_mail_transport(): void
    {
        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/mail-transport', [
                'name' => 'Attacker SMTP',
                'host' => 'attacker.example.test',
                'port' => 587,
                'scheme' => 'tls',
                'username' => 'attacker',
                'password' => 'attacker-password',
                'default_from_email' => 'noreply@stelfaro.com',
                'default_from_name' => 'StelFaro',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('notification_mail_transports', 0);
    }

    public function test_it_keeps_previous_password_when_updating_without_password(): void
    {
        config(['notifications.internal_tokens' => [[
            'client' => 'platform-api',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

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

    public function test_it_requeues_messages_waiting_for_mail_transport(): void
    {
        Queue::fake();
        config(['notifications.internal_tokens' => [[
            'client' => 'platform-api',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $message = NotificationMessage::query()->create([
            'source_type' => 'dte',
            'source_id' => 135,
            'recipient_email' => 'cliente@example.test',
            'status' => 'waiting_transport',
            'purpose' => 'dte_delivery',
            'metadata' => [
                'delivery_blocker' => 'mail_transport_missing',
            ],
        ]);

        $this
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
            ])
            ->assertCreated()
            ->assertJsonPath('data.requeued_waiting_messages', 1);

        Queue::assertPushed(SendDteEmailJob::class, fn (SendDteEmailJob $job): bool => $job->messageId === $message->id);
    }
}
