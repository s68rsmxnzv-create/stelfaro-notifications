<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documento tributario electrónico</title>
</head>
<body style="margin: 0; padding: 0; background: #0f172a; color: #e5efff; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #0f172a; margin: 0; padding: 0;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 36px 40px 32px;">
                            <p style="margin: 0 0 22px; color: #ffffff; font-size: 18px; line-height: 28px;">
                                Hola {{ $notificationMessage->recipient_name ?: 'cliente' }},
                            </p>

                            <p style="margin: 0 0 18px; color: #dbeafe; font-size: 17px; line-height: 29px;">
                                Tu documento tributario electrónico fue emitido y recibido correctamente por el Ministerio de Hacienda.
                            </p>

                            <p style="margin: 0 0 26px; color: #bfdbfe; font-size: 16px; line-height: 27px;">
                                Adjuntamos el PDF y el JSON fiscal para tu resguardo. Este correo fue generado automáticamente; por favor no respondas a este mensaje.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 28px; background: #0b1220; border: 1px solid #1d4ed8; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        @if(! empty($metadata['numero_control']))
                                            <p style="margin: 0 0 12px; color: #93c5fd; font-size: 12px; font-weight: 700; letter-spacing: 0; text-transform: uppercase;">Número de control</p>
                                            <p style="margin: 0 0 18px; color: #ffffff; font-size: 15px; line-height: 22px; word-break: break-word;">{{ $metadata['numero_control'] }}</p>
                                        @endif

                                        @if(! empty($metadata['codigo_generacion']))
                                            <p style="margin: 0 0 12px; color: #93c5fd; font-size: 12px; font-weight: 700; letter-spacing: 0; text-transform: uppercase;">Código de generación</p>
                                            <p style="margin: 0; color: #ffffff; font-size: 15px; line-height: 22px; word-break: break-word;">{{ $metadata['codigo_generacion'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if($queryUrl)
                                <p style="margin: 0 0 32px;">
                                    <a href="{{ $queryUrl }}" style="display: inline-block; background: #2563eb; border-radius: 8px; color: #ffffff; font-size: 16px; font-weight: 700; line-height: 20px; padding: 14px 22px; text-decoration: none;">
                                        Consultar tu DTE
                                    </a>
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 26px; border-top: 1px solid #1e3a5f; border-bottom: 1px solid #1e3a5f;">
                                <tr>
                                    <td style="padding: 20px 0;">
                                        <p style="margin: 0 0 8px; color: #e0f2fe; font-size: 15px; line-height: 24px;">
                                            ¿Aún no emites factura electrónica?
                                        </p>
                                        <p style="margin: 0; color: #bfdbfe; font-size: 14px; line-height: 23px;">
                                            Con Stelfaro puedes ordenar tu facturación electrónica y crecer con una plataforma pensada para El Salvador.
                                            <a href="{{ $whatsappUrl }}" style="color: #60a5fa; font-weight: 700; text-decoration: none;">Haz clic aquí para escribirnos por WhatsApp</a>.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #94a3b8; font-size: 13px; line-height: 22px;">
                                Stelfaro · Tecnología fiscal para negocios que quieren crecer ordenados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
