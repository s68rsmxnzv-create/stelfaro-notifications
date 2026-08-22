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
                'links' => [
                    [
                        'book' => 'ventas_contribuyente',
                        'book_label' => 'Ventas a contribuyentes',
                        'kind' => 'csv',
                        'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-annex/sales_annex/ventas_contribuyente/abc',
                    ],
                    [
                        'book' => 'ventas_contribuyente',
                        'book_label' => 'Ventas a contribuyentes',
                        'kind' => 'zip',
                        'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-zip-annex/ventas_contribuyente/abc',
                    ],
                    [
                        'book' => 'documentos_invalidados',
                        'book_label' => 'Documentos invalidados',
                        'kind' => 'csv',
                        'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-annex/invalidated_annex/documentos_invalidados/abc',
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
        $this->assertCount(3, $message->metadata['links']);
        Queue::assertPushed(SendAnnexEmailJob::class, fn (SendAnnexEmailJob $job): bool => $job->messageId === $message->id);
    }

    public function test_send_annex_email_job_sends_mail_with_csv_and_zip_buttons_and_no_attachments(): void
    {
        Mail::fake();
        $this->createActiveMailTransport();

        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => ['email' => 'contador@example.test'],
                'links' => [
                    [
                        'book' => 'ventas_contribuyente',
                        'book_label' => 'Ventas a contribuyentes',
                        'kind' => 'csv',
                        'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-annex/sales_annex/ventas_contribuyente/abc',
                    ],
                    [
                        'book' => 'ventas_contribuyente',
                        'book_label' => 'Ventas a contribuyentes',
                        'kind' => 'zip',
                        'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-zip-annex/ventas_contribuyente/abc',
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

        Mail::assertSent(AnnexSharedMail::class, function (AnnexSharedMail $mail): bool {
            $html = $mail->render();

            return count($mail->attachments()) === 0
                && str_contains($html, 'https://dev.stelfaro.com/core-api/v1/dte/shared-annex/sales_annex/ventas_contribuyente/abc')
                && str_contains($html, 'https://dev.stelfaro.com/core-api/v1/dte/shared-zip-annex/ventas_contribuyente/abc');
        });
    }

    public function test_send_annex_email_job_sends_copy_to_cc_recipients(): void
    {
        Mail::fake();
        $this->createActiveMailTransport();

        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => ['email' => 'contador@example.test'],
                'links' => [[
                    'book' => 'ventas_contribuyente',
                    'book_label' => 'Ventas a contribuyentes',
                    'kind' => 'csv',
                    'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-annex/sales_annex/ventas_contribuyente/abc',
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
                'links' => [[
                    'book' => 'ventas_contribuyente',
                    'book_label' => 'Ventas a contribuyentes',
                    'kind' => 'csv',
                    'url' => 'https://dev.stelfaro.com/core-api/v1/dte/shared-annex/sales_annex/ventas_contribuyente/abc',
                ]],
                'cc' => ['a@example.test', 'b@example.test', 'c@example.test', 'd@example.test', 'e@example.test', 'f@example.test'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cc');
    }

    public function test_annex_email_rejects_request_without_links(): void
    {
        config(['notifications.internal_tokens' => [[
            'client' => 'dte-core',
            'token_hash' => hash('sha256', 'secret'),
        ]]]);

        $this
            ->withToken('secret')
            ->postJson('/api/v1/annex/7/email', [
                'recipient' => ['email' => 'contador@example.test'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('links');
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
