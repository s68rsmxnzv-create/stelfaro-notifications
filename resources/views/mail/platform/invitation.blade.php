<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $notificationMessage->subject ?: 'Invitacion a StelFaro' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="font-size: 22px;">Te invitaron a {{ $tenant['name'] ?? 'StelFaro' }}</h1>
    <p>Usa este enlace para aceptar la invitacion y activar tu acceso.</p>
    <p>
        <a href="{{ $invitation['accept_url'] ?? '#' }}" style="display: inline-block; padding: 10px 14px; background: #0f172a; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Aceptar invitacion
        </a>
    </p>
    @if (! empty($invitation['expires_at']))
        <p style="font-size: 13px; color: #475569;">Esta invitacion vence el {{ $invitation['expires_at'] }}.</p>
    @endif
</body>
</html>
