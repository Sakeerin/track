<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the parcel tracking system
    |
    */

    // Maximum number of tracking numbers allowed per request
    'max_tracking_numbers' => env('TRACKING_MAX_NUMBERS', 20),

    // Cache TTL for shipment data (in seconds)
    'cache_ttl' => env('TRACKING_CACHE_TTL', 30),

    // Rate limiting configuration
    'rate_limits' => [
        'public' => env('TRACKING_RATE_LIMIT_PUBLIC', '100,1'), // 100 requests per minute
        'authenticated' => env('TRACKING_RATE_LIMIT_AUTH', '500,1'), // 500 requests per minute
    ],

    // Tracking number validation pattern
    'tracking_number_pattern' => '/^[A-Z]{2}[0-9]{10}$/',

    // Supported locales for event descriptions
    'supported_locales' => ['en', 'th'],

    // Default locale
    'default_locale' => env('TRACKING_DEFAULT_LOCALE', 'en'),
];