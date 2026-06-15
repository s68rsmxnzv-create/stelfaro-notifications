<?php

namespace Tests\Feature;

use App\Jobs\SendDteEmailJob;
use App\Mail\DteAcceptedMail;
use App\Models\NotificationMessage;
use App\Support\Core\CoreApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DteEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_dte_email_notification_request(): void
    {
        Queue::fake();
        config(['notifications.api_token' => 'secret']);

        $response = $this
            ->withToken('secret')
            ->postJson('/api/v1/dte/135/email', [
                'empresa_id' => 1,
                'recipient' => [
                    'name' => 'Cliente Demo',
                    'email' => 'cliente@example.test',
                ],
                'subject' => 'Su factura electronica',
                'tipo_dte' => '01',
                'numero_control' => 'DTE-01-M001P001-000000000000135',
                'codigo_generacion' => 'AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',
            ]);

        $response->assertAccepted()
            ->assertJsonPath('data.source_type', 'dte')
            ->assertJsonPath('data.source_id', 135)
            ->assertJsonPath('data.recipient_email', 'cliente@example.test')
            ->assertJsonPath('data.status', 'pending');

        $message = NotificationMessage::query()->firstOrFail();

        $this->assertSame('DTE-01-M001P001-000000000000135', $message->metadata['numero_control']);
        $this->assertDatabaseHas('notification_events', [
            'notification_message_id' => $message->id,
            'type' => 'queued',
        ]);
        Queue::assertPushed(SendDteEmailJob::class, fn (SendDteEmailJob $job): bool => $job->messageId === $message->id);
    }

    public function test_send_dte_email_job_fetches_artifacts_and_sends_mail(): void
    {
        Mail::fake();
        Storage::fake('local');
        config([
            'notifications.core.base_url' => 'https://core.example.test/api/v1',
            'notifications.core.token' => 'core-token',
            'notifications.attachments.disk' => 'local',
            'notifications.attachments.path' => 'notifications',
            'services.notifications.default_provider' => 'array',
        ]);

        Http::fake([
            'https://core.example.test/api/v1/dte/drafts/135/artifacts/pdf' => Http::response('%PDF-1.4', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="dte-demo.pdf"',
            ]),
            'https://core.example.test/api/v1/dte/drafts/135/artifacts/client-json' => Http::response('{"payload":[]}', 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="dte-demo.json"',
            ]),
        ]);

        $message = NotificationMessage::query()->create([
            'source_type' => 'dte',
            'source_id' => 135,
            'empresa_id' => 1,
            'recipient_email' => 'cliente@example.test',
            'recipient_name' => 'Cliente Demo',
            'status' => 'pending',
            'metadata' => [
                'numero_control' => 'DTE-01-M001P001-000000000000135',
                'codigo_generacion' => 'AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',
            ],
        ]);

        (new SendDteEmailJob($message->id))->handle(app(CoreApiClient::class));

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('array', $message->provider);
        $this->assertNotNull($message->sent_at);
        $this->assertDatabaseHas('notification_events', [
            'notification_message_id' => $message->id,
            'type' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'pdf',
            'filename' => 'dte-demo.pdf',
        ]);
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'json',
            'filename' => 'dte-demo.json',
        ]);

        Storage::disk('local')->assertExists("notifications/{$message->id}/dte-demo.pdf");
        Storage::disk('local')->assertExists("notifications/{$message->id}/dte-demo.json");
        Mail::assertSent(DteAcceptedMail::class, fn (DteAcceptedMail $mail): bool => $mail->message->id === $message->id);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer core-token'));
    }

    public function test_internal_token_is_required(): void
    {
        config(['notifications.api_token' => 'secret']);

        $this->postJson('/api/v1/dte/135/email', [
            'recipient' => [
                'email' => 'cliente@example.test',
            ],
        ])->assertUnauthorized();
    }
}
