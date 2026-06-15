<?php

namespace Tests\Feature;

use App\Jobs\SendDteEmailJob;
use App\Mail\DteAcceptedMail;
use App\Models\NotificationMessage;
use App\Models\NotificationSenderAlias;
use App\Services\MailTransportConfigurator;
use App\Services\SenderAliasResolver;
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
            'https://core.example.test/api/v1/internal/dte/drafts/135/artifacts/pdf' => Http::response('%PDF-1.4', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="DTE-01-M001P001-000000000000135.pdf"',
            ]),
            'https://core.example.test/api/v1/internal/dte/drafts/135/artifacts/client-json' => Http::response('{"payload":[]}', 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="DTE-01-M001P001-000000000000135.json"',
            ]),
        ]);

        $message = NotificationMessage::query()->create([
            'source_type' => 'dte',
            'source_id' => 135,
            'empresa_id' => 1,
            'recipient_email' => 'cliente@example.test',
            'recipient_name' => 'Cliente Demo',
            'status' => 'pending',
            'purpose' => 'dte_delivery',
            'metadata' => [
                'numero_control' => 'DTE-01-M001P001-000000000000135',
                'codigo_generacion' => 'AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',
            ],
        ]);

        (new SendDteEmailJob($message->id))->handle(
            app(CoreApiClient::class),
            app(SenderAliasResolver::class),
            app(MailTransportConfigurator::class),
        );

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('array', $message->provider);
        $this->assertNotNull($message->sent_at);
        $this->assertDatabaseHas('notification_events', [
            'notification_message_id' => $message->id,
            'type' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_events', [
            'notification_message_id' => $message->id,
            'type' => 'artifact_fetched',
        ]);
        $this->assertDatabaseHas('notification_events', [
            'notification_message_id' => $message->id,
            'type' => 'smtp_sent',
        ]);
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'pdf',
            'filename' => 'DTE-01-M001P001-000000000000135.pdf',
        ]);
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'json',
            'filename' => 'DTE-01-M001P001-000000000000135.json',
        ]);

        Storage::disk('local')->assertExists("notifications/{$message->id}/DTE-01-M001P001-000000000000135.pdf");
        Storage::disk('local')->assertExists("notifications/{$message->id}/DTE-01-M001P001-000000000000135.json");
        $this->assertIsInt($message->refresh()->metadata['timing']['total_ms']);
        $this->assertIsInt($message->metadata['timing']['fetch_pdf_ms']);
        $this->assertIsInt($message->metadata['timing']['smtp_send_ms']);
        Mail::assertSent(DteAcceptedMail::class, fn (DteAcceptedMail $mail): bool => $mail->message->id === $message->id);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer core-token'));
    }

    public function test_send_mh_fiscal_event_email_job_fetches_event_artifacts_and_sends_mail(): void
    {
        Mail::fake();
        Storage::fake('local');
        config([
            'notifications.api_token' => 'secret',
            'notifications.core.base_url' => 'https://core.example.test/api/v1',
            'notifications.core.token' => 'core-token',
            'notifications.attachments.disk' => 'local',
            'notifications.attachments.path' => 'notifications',
            'services.notifications.default_provider' => 'array',
        ]);

        Http::fake([
            'https://core.example.test/api/v1/internal/mh/events/22/artifacts/pdf' => Http::response('%PDF-event', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="mh-event-invalidacion-BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB.pdf"',
            ]),
            'https://core.example.test/api/v1/internal/mh/events/22/artifacts/client-json' => Http::response('{"identificacion":[]}', 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="mh-event-invalidacion-BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB.json"',
            ]),
        ]);

        $response = $this
            ->withToken('secret')
            ->postJson('/api/v1/mh-events/22/email', [
                'empresa_id' => 1,
                'recipient' => [
                    'name' => 'Cliente Original',
                    'email' => 'cliente@example.test',
                ],
                'subject' => 'Invalidacion de comprobante electronico',
                'purpose' => 'dte_delivery',
                'event_type' => 'invalidacion',
                'numero_control' => 'EVT-INVALIDACION-001',
                'codigo_generacion' => 'BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB',
                'metadata' => [
                    'notification_type' => 'invalidation',
                    'query_enabled' => false,
                ],
            ]);

        $response->assertAccepted()
            ->assertJsonPath('data.source_type', 'mh_fiscal_event')
            ->assertJsonPath('data.source_id', 22);

        $message = NotificationMessage::query()->firstOrFail();

        (new SendDteEmailJob($message->id))->handle(
            app(CoreApiClient::class),
            app(SenderAliasResolver::class),
            app(MailTransportConfigurator::class),
        );

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame('invalidation', $message->metadata['context']['notification_type']);
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'pdf',
            'filename' => 'mh-event-invalidacion-BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB.pdf',
        ]);
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'json',
            'filename' => 'mh-event-invalidacion-BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB.json',
        ]);
        Mail::assertSent(DteAcceptedMail::class, fn (DteAcceptedMail $mail): bool => $mail->message->id === $message->id);
        Http::assertSentCount(2);
    }

    public function test_send_dte_email_job_uses_configured_sender_alias(): void
    {
        Mail::fake();
        Storage::fake('local');
        config([
            'notifications.core.base_url' => 'https://core.example.test/api/v1',
            'notifications.attachments.disk' => 'local',
            'notifications.attachments.path' => 'notifications',
        ]);

        Http::fake([
            'https://core.example.test/api/v1/internal/dte/drafts/135/artifacts/pdf' => Http::response('%PDF-1.4', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="DTE-01-M001P001-000000000000135.pdf"',
            ]),
            'https://core.example.test/api/v1/internal/dte/drafts/135/artifacts/client-json' => Http::response('{"payload":[]}', 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="DTE-01-M001P001-000000000000135.json"',
            ]),
        ]);

        NotificationSenderAlias::query()->create([
            'scope_type' => 'global',
            'scope_id' => 0,
            'purpose' => 'dte_delivery',
            'from_email' => 'stelfaro.dte@stelfaro.com',
            'from_name' => 'Stelfaro DTE',
            'reply_to_email' => 'soporte@stelfaro.com',
            'reply_to_name' => 'Soporte Stelfaro',
        ]);

        $message = NotificationMessage::query()->create([
            'source_type' => 'dte',
            'source_id' => 135,
            'empresa_id' => 1,
            'recipient_email' => 'cliente@example.test',
            'recipient_name' => 'Cliente Demo',
            'status' => 'pending',
            'purpose' => 'dte_delivery',
            'metadata' => [
                'empresa_nombre_comercial' => 'Vidrieria El Faro',
            ],
        ]);

        (new SendDteEmailJob($message->id))->handle(
            app(CoreApiClient::class),
            app(SenderAliasResolver::class),
            app(MailTransportConfigurator::class),
        );

        $message->refresh();

        $this->assertSame('stelfaro.dte@stelfaro.com', $message->from_email);
        $this->assertSame('Vidrieria El Faro', $message->from_name);
        $this->assertNull($message->reply_to_email);

        Mail::assertSent(DteAcceptedMail::class, function (DteAcceptedMail $mail): bool {
            $envelope = $mail->envelope();

            return $envelope->from?->address === 'stelfaro.dte@stelfaro.com'
                && $envelope->from?->name === 'Vidrieria El Faro'
                && $envelope->replyTo === [];
        });
    }

    public function test_dte_email_uses_branded_html_template_with_query_button(): void
    {
        config([
            'notifications.dte.public_query_url' => 'https://admin.factura.gob.sv/consultaPublica',
            'notifications.marketing.whatsapp_url' => 'https://wa.me/50375640652',
        ]);

        $message = NotificationMessage::query()->create([
            'source_type' => 'dte',
            'source_id' => 135,
            'recipient_email' => 'cliente@example.test',
            'recipient_name' => 'Cliente Demo',
            'status' => 'pending',
            'purpose' => 'dte_delivery',
            'metadata' => [
                'numero_control' => 'DTE-01-M001P001-000000000000135',
                'codigo_generacion' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'context' => [
                    'ambiente' => '00',
                    'fecha_emi' => '2026-06-15',
                ],
            ],
        ]);

        $html = (new DteAcceptedMail($message))->render();

        $this->assertStringContainsString('Consultar tu DTE', $html);
        $this->assertStringContainsString('width="190"', $html);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $html);
        $this->assertStringContainsString('M11.625 16.5', $html);
        $this->assertStringContainsString('ambiente=00&amp;codGen=AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA&amp;fechaEmi=2026-06-15', $html);
        $this->assertStringContainsString('https://wa.me/50375640652', $html);
        $this->assertStringContainsString('¿Aún no emites factura electrónica?', $html);
        $this->assertStringNotContainsString('Stelfaro Notifications', $html);
        $this->assertStringNotContainsString('http://localhost', $html);
    }

    public function test_dte_email_template_adapts_to_invalidation_notice(): void
    {
        $message = NotificationMessage::query()->create([
            'source_type' => 'mh_fiscal_event',
            'source_id' => 22,
            'recipient_email' => 'cliente@example.test',
            'recipient_name' => 'Cliente Demo',
            'status' => 'pending',
            'purpose' => 'dte_delivery',
            'metadata' => [
                'numero_control' => 'EVT-INVALIDACION-001',
                'codigo_generacion' => 'BBBBBBBB-BBBB-BBBB-BBBB-BBBBBBBBBBBB',
                'context' => [
                    'notification_type' => 'invalidation',
                    'query_enabled' => false,
                ],
            ],
        ]);

        $html = (new DteAcceptedMail($message))->render();

        $this->assertStringContainsString('invalidación de un documento tributario electrónico', $html);
        $this->assertStringContainsString('Evento de invalidación', $html);
        $this->assertStringNotContainsString('Consultar tu DTE', $html);
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
