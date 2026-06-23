<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $notificationMessage->subject ?: 'Acceso temporal a StelFaro' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="font-size: 22px;">Tu acceso a {{ $tenant['name'] ?? 'StelFaro' }} esta listo</h1>
    <p>Usa esta contrasena temporal para iniciar sesion. El sistema te pedira crear una nueva contrasena en el primer ingreso.</p>
    <p style="margin: 18px 0; padding: 12px 14px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-family: Consolas, Monaco, monospace; font-size: 18px; letter-spacing: 1px;">
        {{ $temporaryPassword }}
    </p>
    <p>
        <a href="{{ $loginUrl ?? '#' }}" style="display: inline-block; padding: 10px 14px; background: #0f172a; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Iniciar sesion
        </a>
    </p>
    <p style="font-size: 13px; color: #475569;">Si no solicitaste este acceso, contacta al administrador de tu empresa.</p>
</body>
</html>
