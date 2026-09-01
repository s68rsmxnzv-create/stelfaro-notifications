<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $notificationMessage->subject ?: 'Restablece tu contrasena de StelFaro' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="font-size: 22px;">Restablece tu contrasena</h1>
    <p>{{ $recipientName ? 'Hola '.$recipientName.',' : 'Hola,' }}</p>
    <p>Recibimos una solicitud para restablecer la contrasena de tu cuenta en StelFaro. Haz clic en el boton para elegir una nueva contrasena.</p>
    <p style="margin: 22px 0;">
        <a href="{{ $resetUrl ?? '#' }}" style="display: inline-block; padding: 10px 16px; background: #0f172a; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Restablecer contrasena
        </a>
    </p>
    <p style="font-size: 13px; color: #475569;">Este enlace vencera en {{ $expiresMinutes }} minutos.</p>
    <p style="font-size: 13px; color: #475569;">Si no solicitaste el restablecimiento, no necesitas hacer nada y tu contrasena seguira igual.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
    <p style="font-size: 12px; color: #94a3b8;">Si tienes problemas con el boton, copia y pega esta direccion en tu navegador:<br>
        <span style="word-break: break-all;">{{ $resetUrl ?? '' }}</span>
    </p>
</body>
</html>
