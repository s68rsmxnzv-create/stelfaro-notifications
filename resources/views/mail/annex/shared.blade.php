<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anexo fiscal compartido</title>
</head>
<body style="margin: 0; padding: 0; background: #0f172a; color: #e5efff; font-family: Arial, Helvetica, sans-serif;">
    @php
        $books = collect(data_get($metadata, 'books', []));
        $bookLabels = $books->map(fn ($book) => $book['book_label'] ?? $book['book'] ?? null)->filter()->values();
        $bookSuffix = $bookLabels->isNotEmpty() ? ' ('.$bookLabels->implode(', ').')' : '';
        $empresaNombre = data_get($metadata, 'empresa_nombre_comercial') ?? data_get($metadata, 'empresa_nombre');
        $from = data_get($metadata, 'from');
        $to = data_get($metadata, 'to');
        $linksByBook = collect(data_get($metadata, 'links', []))
            ->filter(fn ($link) => !empty($link['url']))
            ->groupBy('book');
        $kindLabels = ['csv' => 'Descargar CSV', 'zip' => 'Descargar ZIP'];
        $kindColors = ['csv' => '#0f766e', 'zip' => '#2563eb'];
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #0f172a; margin: 0; padding: 0;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 36px 40px 32px;">
                            <h1 style="margin: 0 0 16px; font-size: 20px; color: #ffffff;">Anexos fiscales compartidos</h1>
                            <p style="margin: 0 0 12px; font-size: 14px; line-height: 1.6; color: #cbd5f5;">
                                @if ($empresaNombre)
                                    Se compartieron los anexos fiscales de <strong>{{ $empresaNombre }}</strong>{{ $bookSuffix }}.
                                @else
                                    Se compartieron anexos fiscales{{ $bookSuffix }}.
                                @endif
                            </p>
                            @if ($from || $to)
                                <p style="margin: 0 0 16px; font-size: 14px; color: #cbd5f5;">
                                    Periodo: {{ $from ?: '—' }} al {{ $to ?: '—' }}
                                </p>
                            @endif
                            @if ($books->isNotEmpty())
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px;">
                                    @foreach ($books as $book)
                                        <tr>
                                            <td style="padding: 0 0 12px; border-bottom: 1px solid #1e3a5f;">
                                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: bold; color: #e5efff;">
                                                    {{ $book['book_label'] ?? $book['book'] }}
                                                </p>
                                                <div style="margin: 0 0 12px;">
                                                    @foreach ($linksByBook->get($book['book'], collect()) as $link)
                                                        <a href="{{ $link['url'] }}" style="display: inline-block; margin: 0 8px 8px 0; padding: 9px 16px; background: {{ $kindColors[$link['kind']] ?? '#2563eb' }}; color: #ffffff; font-size: 13px; font-weight: bold; text-decoration: none; border-radius: 6px;">
                                                            {{ $kindLabels[$link['kind']] ?? 'Descargar' }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                            <p style="margin: 0; font-size: 13px; color: #94a3b8;">
                                Los enlaces vencen en 7 dias. Este correo fue generado automáticamente; por favor no respondas a este mensaje.
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
