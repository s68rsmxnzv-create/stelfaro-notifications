<?php

namespace Tests\Feature;

use App\Mail\PlatformInvitationMail;
use App\Mail\PlatformTemporaryPasswordMail;
use App\Jobs\SendPlatformTemporaryPasswordEmailJob;
use App\Models\NotificationAction;
use App\Models\NotificationMailTransport;
use App\Models\NotificationMessage;
use App\Models\NotificationSenderAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_default_invitations_activity_and_action(): void
    {
        $this->configureInternalToken();

        $this
            ->withToken('secret')
            ->getJson('/api/v1/activities?key=invitations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'invitations')
            ->assertJsonPath('data.0.name', 'Invitaciones')
            ->assertJsonPath('data.0.actions.0.key', 'platform_invitation')
            ->assertJsonPath('data.0.actions.0.purpose', 'platform_invitation');
    }

    public function test_it_exposes_default_users_temporary_password_action(): void
    {
        $this->configureInternalToken();

        $this
            ->withToken('secret')
            ->getJson('/api/v1/activities?key=users')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'users')
            ->assertJsonPath('data.0.actions.0.key', 'temporary_password_delivery')
            ->assertJsonPath('data.0.actions.0.purpose', 'platform_temporary_password');
    }

    public function test_it_creates_action_and_assigns_sender_alias(): void
    {
        $this->configureInternalToken();

        $alias = NotificationSenderAlias::query()->create([
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'platform_invitation_reminder',
            'from_email' => 'invitaciones@stelfaro.com',
            'from_name' => 'StelFaro Invitaciones',
        ]);

        $activity = $this
            ->withToken('secret')
            ->postJson('/api/v1/activities', [
                'key' => 'onboarding',
                'name' => 'Onboarding',
            ])
            ->assertCreated()
            ->json('data');

        $this
            ->withToken('secret')
            ->postJson("/api/v1/activities/{$activity['id']}/actions", [
                'key' => 'invitation_reminder',
                'name' => 'Recordatorio de invitacion',
                'purpose' => 'platform_invitation_reminder',
                'notification_sender_alias_id' => $alias->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'invitation_reminder')
            ->assertJsonPath('data.sender_alias.from_email', 'invitaciones@stelfaro.com');

        $this->assertDatabaseHas('notification_actions', [
            'key' => 'invitation_reminder',
            'purpose' => 'platform_invitation_reminder',
            'notification_sender_alias_id' => $alias->id,
        ]);
    }

    public function test_platform_invitation_email_uses_alias_assigned_to_action(): void
    {
        Mail::fake();
        $this->configureInternalToken();
        $this->createActiveTransport();

        $alias = NotificationSenderAlias::query()->create([
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'custom-purpose-name',
            'from_email' => 'invitaciones@stelfaro.com',
            'from_name' => 'StelFaro Invitaciones',
            'reply_to_email' => 'soporte@stelfaro.com',
            'reply_to_name' => 'Soporte StelFaro',
        ]);
        NotificationAction::query()
            ->where('purpose', 'platform_invitation')
            ->firstOrFail()
            ->update(['notification_sender_alias_id' => $alias->id]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/platform/invitations/email', [
                'recipient' => [
                    'email' => 'cliente@example.test',
                    'name' => 'Cliente Demo',
                ],
                'tenant' => [
                    'id' => 23,
                    'name' => 'Cliente Demo',
                    'slug' => 'cliente-demo',
                ],
                'invitation' => [
                    'id' => 91,
                    'role' => 'billing_user',
                    'status' => 'pending',
                    'expires_at' => now()->addWeek()->toISOString(),
                    'accept_url' => 'https://platform.stelfaro.com/invitations/token-demo',
                ],
            ])
            ->assertAccepted()
            ->assertJsonPath('data.purpose', 'platform_invitation')
            ->assertJsonPath('data.recipient_email', 'cliente@example.test');

        $message = NotificationMessage::query()->where('source_type', 'platform_invitation')->firstOrFail();

        $this->assertSame('invitaciones@stelfaro.com', $message->from_email);
        $this->assertSame('StelFaro Invitaciones', $message->from_name);
        $this->assertSame('soporte@stelfaro.com', $message->reply_to_email);
        $this->assertSame('sent', $message->status);
        $this->assertStringContainsString(
            'Aceptar invitacion',
            (new PlatformInvitationMail($message))->render()
        );

        Mail::assertSent(PlatformInvitationMail::class);
    }

    public function test_platform_temporary_password_email_is_queued_with_sensitive_metadata(): void
    {
        Queue::fake();
        $this->configureInternalToken();

        $this
            ->withToken('secret')
            ->postJson('/api/v1/platform/temporary-passwords/email', [
                'recipient' => [
                    'email' => 'cliente@example.test',
                    'name' => 'Cliente Demo',
                ],
                'tenant' => [
                    'id' => 23,
                    'name' => 'Cliente Demo',
                    'slug' => 'cliente-demo',
                ],
                'user' => [
                    'id' => 71,
                    'name' => 'Cliente Demo',
                    'email' => 'cliente@example.test',
                    'role' => 'billing_user',
                ],
                'temporary_password' => [
                    'value' => 'Sf-Temp-1234',
                    'login_url' => 'https://platform.stelfaro.com/login',
                    'must_change' => true,
                    'reason' => 'direct_user_creation',
                ],
            ])
            ->assertAccepted()
            ->assertJsonPath('data.purpose', 'platform_temporary_password')
            ->assertJsonPath('data.recipient_email', 'cliente@example.test');

        $message = NotificationMessage::query()->where('source_type', 'platform_temporary_password')->firstOrFail();

        $this->assertSame('Cliente Demo', $message->metadata['tenant']['name']);
        $this->assertSame('Sf-Temp-1234', $message->sensitive_metadata['temporary_password']['value']);
        $this->assertStringNotContainsString('Sf-Temp-1234', (string) $message->getRawOriginal('sensitive_metadata'));
        Queue::assertPushed(SendPlatformTemporaryPasswordEmailJob::class, fn (SendPlatformTemporaryPasswordEmailJob $job): bool => $job->messageId === $message->id);
    }

    public function test_platform_temporary_password_email_uses_alias_assigned_to_action(): void
    {
        Mail::fake();
        $this->configureInternalToken();
        $this->createActiveTransport();

        $alias = NotificationSenderAlias::query()->create([
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'temporary-password',
            'from_email' => 'accesos@stelfaro.com',
            'from_name' => 'StelFaro Accesos',
        ]);
        NotificationAction::query()
            ->where('purpose', 'platform_temporary_password')
            ->firstOrFail()
            ->update(['notification_sender_alias_id' => $alias->id]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/platform/temporary-passwords/email', [
                'recipient' => [
                    'email' => 'cliente@example.test',
                    'name' => 'Cliente Demo',
                ],
                'tenant' => [
                    'id' => 23,
                    'name' => 'Cliente Demo',
                    'slug' => 'cliente-demo',
                ],
                'user' => [
                    'id' => 71,
                    'name' => 'Cliente Demo',
                    'email' => 'cliente@example.test',
                    'role' => 'billing_user',
                ],
                'temporary_password' => [
                    'value' => 'Sf-Temp-1234',
                    'login_url' => 'https://platform.stelfaro.com/login',
                    'must_change' => true,
                    'reason' => 'direct_user_creation',
                ],
            ])
            ->assertAccepted();

        $message = NotificationMessage::query()->where('source_type', 'platform_temporary_password')->firstOrFail();

        $this->assertSame('accesos@stelfaro.com', $message->from_email);
        $this->assertSame('sent', $message->status);
        $this->assertStringContainsString(
            'Sf-Temp-1234',
            (new PlatformTemporaryPasswordMail($message))->render()
        );

        Mail::assertSent(PlatformTemporaryPasswordMail::class);
    }

    private function configureInternalToken(): void
    {
        config(['notifications.internal_tokens' => [[
            'client' => 'platform-api',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);
    }

    private function createActiveTransport(): void
    {
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
    }
}
