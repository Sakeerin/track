<?php

return [
    'rate_limits' => [
        'public_per_minute' => (int) env('RATE_LIMIT_PUBLIC', 100),
        'api_per_minute' => (int) env('RATE_LIMIT_API', 60),
        'admin_per_minute' => (int) env('RATE_LIMIT_ADMIN', 500),
        'webhook_per_minute' => (int) env('RATE_LIMIT_WEBHOOK', 1000),
        'batch_per_minute' => (int) env('RATE_LIMIT_BATCH', 10),
    ],

    'admin_ip_whitelist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_IP_WHITELIST', ''))
    ))),

    'retention' => [
        'audit_logs_days' => (int) env('RETENTION_AUDIT_LOG_DAYS', 365),
        'notification_logs_days' => (int) env('RETENTION_NOTIFICATION_LOG_DAYS', 180),
        'closed_tickets_days' => (int) env('RETENTION_CLOSED_TICKET_DAYS', 365),
    ],

    'headers' => [
        'x_frame_options' => env('SECURITY_HEADER_X_FRAME_OPTIONS', 'DENY'),
        'x_content_type_options' => env('SECURITY_HEADER_X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'referrer_policy' => env('SECURITY_HEADER_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'content_security_policy' => env(
            'SECURITY_HEADER_CONTENT_SECURITY_POLICY',
            "default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
        ),
        'strict_transport_security' => env(
            'SECURITY_HEADER_STRICT_TRANSPORT_SECURITY',
            'max-age=31536000; includeSubDomains'
        ),
    ],
];
