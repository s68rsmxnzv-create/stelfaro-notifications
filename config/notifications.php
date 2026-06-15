<?php

return [
    'api_token' => env('NOTIFICATIONS_API_TOKEN'),

    'core' => [
        'base_url' => env('DTE_CORE_BASE_URL', 'http://127.0.0.1/api/v1'),
        'token' => env('DTE_CORE_TOKEN'),
        'timeout' => (int) env('DTE_CORE_TIMEOUT', 20),
    ],

    'attachments' => [
        'disk' => env('NOTIFICATIONS_ATTACHMENTS_DISK', 'local'),
        'path' => env('NOTIFICATIONS_ATTACHMENTS_PATH', 'notifications'),
    ],
];
