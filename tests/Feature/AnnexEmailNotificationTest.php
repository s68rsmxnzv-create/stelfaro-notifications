<?php

namespace Tests\Feature;

use App\Jobs\SendAnnexEmailJob;
use App\Mail\AnnexSharedMail;
use App\Models\NotificationMailTransport;
use App\Models\NotificationMessage;
use App\Services\MailTransportConfigurator;
use App\Services\SenderAliasResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnexEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_annex_email_notification_request(): void
    {
        Queue::fake();
        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $response = $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => [
                    'name' => 'Contador Externo',
                    'email' => 'contador@example.test',
                ],
                'subject' => 'Anexos de ventas julio 2026',
                'from' => '2026-07-01',
                'to' => '2026-07-31',
                'attachments' => [
                    [
                        'book' => 'ventas_contribuyente',
                        'book_label' => 'Ventas a contribuyentes',
                        'filename' => 'anexo_ventas_contribuyente.csv',
                        'content_base64' => base64_encode("fecha;total\n03/07/2026;113.00"),
                    ],
                    [
                        'book' => 'documentos_invalidados',
                        'book_label' => 'Documentos invalidados',
                        'filename' => 'anexo_documentos_invalidados.csv',
                        'content_base64' => base64_encode("fecha;total\n05/07/2026;50.00"),
                    ],
                ],
            ]);

        $response->assertAccepted()
            ->assertJsonPath('data.source_type', 'annex')
            ->assertJsonPath('data.empresa_id', 7)
            ->assertJsonPath('data.recipient_email', 'contador@example.test')
            ->assertJsonPath('data.status', 'pending');

        $message = NotificationMessage::query()->firstOrFail();

        $this->assertSame(
            ['ventas_contribuyente', 'documentos_invalidados'],
            collect($message->metadata['books'])->pluck('book')->all()
        );
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'csv',
            'filename' => 'anexo_ventas_contribuyente.csv',
        ]);
        $this->assertDatabaseHas('notification_attachments', [
            'notification_message_id' => $message->id,
            'type' => 'csv',
            'filename' => 'anexo_documentos_invalidados.csv',
        ]);
        Queue::assertPushed(SendAnnexEmailJob::class, fn (SendAnnexEmailJob $job): bool => $job->messageId === $message->id);
    }

    public function test_send_annex_email_job_sends_mail_with_all_csv_attachments(): void
    {
        Mail::fake();
        Storage::fake('local');
        config([
            'notifications.attachments.disk' => 'local',
            'notifications.attachments.path' => 'notifications',
        ]);
        $this->createActiveMailTransport();

        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => ['email' => 'contador@example.test'],
                'attachments' => [
                    [
                        'book' => 'ventas_contribuyente',
                        'book_label' => 'Ventas a contribuyentes',
                        'filename' => 'anexo_ventas_contribuyente.csv',
                        'content_base64' => base64_encode("fecha;total\n03/07/2026;113.00"),
                    ],
                    [
                        'book' => 'ventas_consumidor_final',
                        'book_label' => 'Ventas a consumidor final',
                        'filename' => 'anexo_ventas_consumidor_final.csv',
                        'content_base64' => base64_encode("fecha;total\n04/07/2026;25.00"),
                    ],
                ],
            ])
            ->assertAccepted();

        $message = NotificationMessage::query()->firstOrFail();

        (new SendAnnexEmailJob($message->id))->handle(
            app(SenderAliasResolver::class),
            app(MailTransportConfigurator::class),
        );

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertNotNull($message->sent_at);
        Storage::disk('local')->assertExists("notifications/{$message->id}/anexo_ventas_contribuyente.csv");
        Storage::disk('local')->assertExists("notifications/{$message->id}/anexo_ventas_consumidor_final.csv");
        Mail::assertSent(AnnexSharedMail::class, fn (AnnexSharedMail $mail): bool => $mail->message->id === $message->id
            && count($mail->attachments()) === 2);
    }

    public function test_send_annex_email_job_sends_copy_to_cc_recipients(): void
    {
        Mail::fake();
        Storage::fake('local');
        config([
            'notifications.attachments.disk' => 'local',
            'notifications.attachments.path' => 'notifications',
        ]);
        $this->createActiveMailTransport();

        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => ['email' => 'contador@example.test'],
                'attachments' => [[
                    'book' => 'ventas_contribuyente',
                    'filename' => 'anexo_ventas_contribuyente.csv',
                    'content_base64' => base64_encode("fecha;total\n03/07/2026;113.00"),
                ]],
                'cc' => ['contabilidad@example.test', 'socio@example.test'],
            ])
            ->assertAccepted();

        $message = NotificationMessage::query()->firstOrFail();
        $this->assertSame(['contabilidad@example.test', 'socio@example.test'], $message->metadata['cc']);

        (new SendAnnexEmailJob($message->id))->handle(
            app(SenderAliasResolver::class),
            app(MailTransportConfigurator::class),
        );

        Mail::assertSent(AnnexSharedMail::class, function (AnnexSharedMail $mail): bool {
            $ccEmails = collect($mail->envelope()->cc)->map(fn ($address) => $address->address)->all();

            return $ccEmails === ['contabilidad@example.test', 'socio@example.test'];
        });
    }

    public function test_annex_email_stores_and_renders_zip_download_links(): void
    {
        Mail::fake();
        Storage::fake('local');
        config([
            'notifications.attachments.disk' => 'local',
            'notifications.attachments.path' => 'notifications',
        ]);
        $this->createActiveMailTransport();

        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => ['email' => 'contador@example.test'],
                'attachments' => [[
                    'book' => 'ventas_contribuyente',
                    'book_label' => 'Ventas a contribuyentes',
                    'filename' => 'anexo_ventas_contribuyente.csv',
                    'content_base64' => base64_encode("fecha;total\n03/07/2026;113.00"),
                ]],
                'download_links' => [[
                    'book' => 'ventas_contribuyente',
                    'book_label' => 'Ventas a contribuyentes',
                    'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-zip-annex/ventas_contribuyente/abc123',
                ]],
            ])
            ->assertAccepted();

        $message = NotificationMessage::query()->firstOrFail();
        $this->assertSame(
            'https://dev.stelfaro.com/core-api/v1/dte/shared-zip-annex/ventas_contribuyente/abc123',
            $message->metadata['download_links'][0]['url']
        );

        (new SendAnnexEmailJob($message->id))->handle(
            app(SenderAliasResolver::class),
            app(MailTransportConfigurator::class),
        );

        Mail::assertSent(AnnexSharedMail::class, function (AnnexSharedMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'https://dev.stelfaro.com/core-api/v1/dte/shared-zip-annex/ventas_contribuyente/abc123');
        });
    }

    public function test_annex_email_rejects_more_than_five_cc_recipients(): void
    {
        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => ['email' => 'contador@example.test'],
                'attachments' => [[
                    'book' => 'ventas_contribuyente',
                    'filename' => 'anexo_ventas_contribuyente.csv',
                    'content_base64' => base64_encode('fecha;total'),
                ]],
                'cc' => ['a@example.test', 'b@example.test', 'c@example.test', 'd@example.test', 'e@example.test', 'f@example.test'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cc');
    }

    private function createActiveMailTransport(): NotificationMailTransport
    {
        return NotificationMailTransport::query()->create([
            'name' => 'Hostinger Stelfaro',
            'host' => 'smtp.hostinger.com',
            'port' => 465,
            'scheme' => 'ssl',
            'username' => 'noreply@stelfaro.com',
            'password' => 'secret-password',
            'default_from_email' => 'noreply@stelfaro.com',
            'default_from_name' => 'StelFaro',
            'is_active' => true,
        ]);
    }
}
