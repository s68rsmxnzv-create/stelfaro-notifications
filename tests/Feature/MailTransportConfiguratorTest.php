<?php

namespace Tests\Feature;

use App\Models\NotificationMailTransport;
use App\Services\MailTransportConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailTransportConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_ssl_transport_scheme_to_smtps(): void
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

        app(MailTransportConfigurator::class)->applyActiveTransport();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }
}
