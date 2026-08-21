<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anexo fiscal compartido</title>
</head>
<body style="margin: 0; padding: 0; background: #0f172a; color: #e5efff; font-family: Arial, Helvetica, sans-serif;">
    @php
        $bookLabel = data_get($metadata, 'book_label') ?? data_get($metadata, 'book');
        $empresaNombre = data_get($metadata, 'empresa_nombre_comercial') ?? data_get($metadata, 'empresa_nombre');
        $from = data_get($metadata, 'from');
        $to = data_get($metadata, 'to');
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #0f172a; margin: 0; padding: 0;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 36px 40px 32px;">
                            <h1 style="margin: 0 0 16px; font-size: 20px; color: #ffffff;">Anexo fiscal compartido</h1>
                            <p style="margin: 0 0 12px; font-size: 14px; line-height: 1.6; color: #cbd5f5;">
                                @if ($empresaNombre)
                                    Se compartió el anexo fiscal de <strong>{{ $empresaNombre }}</strong>@if ($bookLabel) ({{ $bookLabel }})@endif.
                                @else
                                    Se compartió un anexo fiscal@if ($bookLabel) ({{ $bookLabel }})@endif.
                                @endif
                            </p>
                            @if ($from || $to)
                                <p style="margin: 0 0 12px; font-size: 14px; color: #cbd5f5;">
                                    Periodo: {{ $from ?: '—' }} al {{ $to ?: '—' }}
                                </p>
                            @endif
                            <p style="margin: 0; font-size: 13px; color: #94a3b8;">
                                Adjuntamos el archivo CSV correspondiente. Este correo fue generado automáticamente; por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if ($trackingPixelUrl ?? null)
        <img src="{{ $trackingPixelUrl }}" width="1" height="1" alt="" style="display:block;border:0;">
    @endif
</body>
</html>
