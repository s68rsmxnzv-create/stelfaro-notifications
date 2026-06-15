<?php

namespace App\Services;

use App\Models\NotificationMailTransport;

class MailTransportConfigurator
{
    public function active(): ?NotificationMailTransport
    {
        return NotificationMailTransport::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    public function applyActiveTransport(): ?NotificationMailTransport
    {
        $transport = $this->active();

        if (! $transport) {
            return null;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $transport->scheme,
            'mail.mailers.smtp.host' => $transport->host,
            'mail.mailers.smtp.port' => $transport->port,
            'mail.mailers.smtp.username' => $transport->username,
            'mail.mailers.smtp.password' => $transport->password,
            'mail.from.address' => $transport->default_from_email,
            'mail.from.name' => $transport->default_from_name,
        ]);

        return $transport;
    }
}
