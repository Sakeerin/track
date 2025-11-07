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

];