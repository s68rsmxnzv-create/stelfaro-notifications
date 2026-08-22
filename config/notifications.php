<?php

return [
    'internal_tokens' => array_values(array_filter(array_map(function (string $entry): ?array {
        $parts = explode(':', trim($entry), 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$client, $tokenHash] = $parts;
        $client = trim($client);
        $tokenHash = strtolower(trim($tokenHash));

        if ($client === '' || ! preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
            return null;
        }

        return [
            'client' => $client,
            'token_hash' => $tokenHash,
        ];
    }, explode(',', (string) env('NOTIFICATIONS_INTERNAL_TOKENS', ''))))),

    'core' => [
        'base_url' => env('DTE_CORE_BASE_URL', 'http://127.0.0.1:8181/api/v1'),
        'token' => env('DTE_CORE_TOKEN'),
        'timeout' => (int) env('DTE_CORE_TIMEOUT', 20),
    ],

    'attachments' => [
        'disk' => env('NOTIFICATIONS_ATTACHMENTS_DISK', 'local'),
        'path' => env('NOTIFICATIONS_ATTACHMENTS_PATH', 'notifications'),
    ],

    'dte' => [
        'public_query_url' => env('DTE_PUBLIC_QUERY_URL', 'https://admin.factura.gob.sv/consultaPublica'),
    ],

    'marketing' => [
        'whatsapp_url' => env('STELFARO_WHATSAPP_URL', 'https://wa.me/50375640652'),
    ],

    'tracking' => [
        'pixel_key' => env('NOTIFICATIONS_TRACKING_PIXEL_KEY') ?: env('APP_KEY'),
        'public_base_url' => env('NOTIFICATIONS_PUBLIC_BASE_URL') ?: env('APP_URL', 'http://localhost'),
    ],
];
