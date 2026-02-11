<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that gets used while
    | using this caching library. This connection is used when another is
    | not explicitly specified when executing a given caching function.
    |
    */

    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "apc", "array", "database", "file",
    |                    "memcached", "redis", "dynamodb", "octane", "null"
    |
    */

    'stores' => [

        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, or DynamoDB cache
    | stores there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),

    /*
    |--------------------------------------------------------------------------
    | Shipment Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options specific to shipment tracking data caching.
    |
    */

    'shipment' => [
        // TTL for shipment status cache (in seconds)
        'status_ttl' => env('CACHE_SHIPMENT_STATUS_TTL', 30),

        // TTL for event timeline cache (in seconds)
        'timeline_ttl' => env('CACHE_SHIPMENT_TIMELINE_TTL', 60),

        // TTL for not-found shipments (shorter to allow quick recovery)
        'not_found_ttl' => env('CACHE_SHIPMENT_NOT_FOUND_TTL', 10),

        // Cache key prefixes
        'prefix' => 'shipment:',
        'stale_prefix' => 'shipment_stale:',
        'timeline_prefix' => 'shipment_timeline:',
        'stats_prefix' => 'shipment_stats:',

        // TTL for stale shipment cache used during graceful degradation (in seconds)
        'stale_ttl' => env('CACHE_SHIPMENT_STALE_TTL', 86400),

        // Enable cache warming for frequently accessed shipments
        'warm_cache' => env('CACHE_WARM_ENABLED', true),

        // Number of recent shipments to keep warm
        'warm_cache_count' => env('CACHE_WARM_COUNT', 100),

        // Tags for grouped cache invalidation
        'tags' => [
            'shipments' => 'shipments',
            'timelines' => 'shipment_timelines',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cache performance monitoring.
    |
    */

    'metrics' => [
        // Enable cache metrics collection
        'enabled' => env('CACHE_METRICS_ENABLED', true),

        // Metrics collection interval (in seconds)
        'interval' => env('CACHE_METRICS_INTERVAL', 60),

        // Metrics key prefix
        'prefix' => 'cache_metrics:',
    ],

];
