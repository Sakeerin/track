<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Partner HMAC Secrets
    |--------------------------------------------------------------------------
    |
    | HMAC secrets for validating webhook signatures from partners
    |
    */
    'partners' => [
        'partner1' => env('PARTNER1_HMAC_SECRET'),
        'partner2' => env('PARTNER2_HMAC_SECRET'),
        'test_partner' => env('TEST_PARTNER_HMAC_SECRET', 'test_secret_key'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | Valid API keys for batch upload endpoints
    |
    */
    'api_keys' => [
        env('BATCH_API_KEY_1'),
        env('BATCH_API_KEY_2'),
        env('TEST_API_KEY', 'test_api_key'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Kafka event streaming
    |
    */
    'kafka' => [
        'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
        'topics' => [
            'events' => env('KAFKA_EVENTS_TOPIC', 'tracking-events'),
            'notifications' => env('KAFKA_NOTIFICATIONS_TOPIC', 'notifications'),
        ],
        'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'tracking-system'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS notification delivery
    |
    */
    'sms' => [
        'api_url' => env('SMS_API_URL', 'https://api.sms-provider.com/send'),
        'status_url' => env('SMS_STATUS_URL', 'https://api.sms-provider.com/status'),
        'api_key' => env('SMS_API_KEY', ''),
        'sender_id' => env('SMS_SENDER_ID', 'TRACKING'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LINE Messaging API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for LINE notification delivery
    |
    */
    'line' => [
        'channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN', ''),
        'channel_secret' => env('LINE_CHANNEL_SECRET', ''),
        'api_url' => env('LINE_API_URL', 'https://api.line.me/v2/bot/message/push'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook notification delivery
    |
    */
    'webhook' => [
        'timeout' => env('WEBHOOK_TIMEOUT', 10),
        'retry_attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('WEBHOOK_RETRY_DELAY', 1000), // milliseconds
        'secret' => env('WEBHOOK_SECRET', env('APP_KEY')),
    ],

];