<?php

namespace App\Services\Tracking;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TrackingService
{
    private int $statusTtl;
    private int $timelineTtl;
    private int $notFoundTtl;
    private int $staleTtl;
    private string $cachePrefix;
    private string $stalePrefix;
    private string $timelinePrefix;
    private bool $metricsEnabled;
    private string $metricsPrefix;

    public function __construct(
        private ShipmentFormatter $formatter
    ) {
        $this->statusTtl = config('cache.shipment.status_ttl', 30);
        $this->timelineTtl = config('cache.shipment.timeline_ttl', 60);
        $this->notFoundTtl = config('cache.shipment.not_found_ttl', 10);
        $this->staleTtl = config('cache.shipment.stale_ttl', 86400);
        $this->cachePrefix = config('cache.shipment.prefix', 'shipment:');
        $this->stalePrefix = config('cache.shipment.stale_prefix', 'shipment_stale:');
        $this->timelinePrefix = config('cache.shipment.timeline_prefix', 'shipment_timeline:');
        $this->metricsEnabled = config('cache.metrics.enabled', true);
        $this->metricsPrefix = config('cache.metrics.prefix', 'cache_metrics:');
    }

    /**
     * Get multiple shipments by tracking numbers with caching
     */
    public function getShipments(array $trackingNumbers): array
    {
        $results = [];
        $uncachedNumbers = [];
        $cacheHits = 0;
        $cacheMisses = 0;

        // Check cache first
        foreach ($trackingNumbers as $trackingNumber) {
            $cacheKey = $this->getCacheKey($trackingNumber);
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                $results[$trackingNumber] = $cached;
                $cacheHits++;
            } else {
                $uncachedNumbers[] = $trackingNumber;
                $cacheMisses++;
            }
        }

        // Record cache metrics
        if ($this->metricsEnabled) {
            $this->recordCacheMetrics($cacheHits, $cacheMisses);
        }

        // Fetch uncached shipments from database
        if (!empty($uncachedNumbers)) {
            try {
                $shipments = $this->fetchShipmentsFromDatabase($uncachedNumbers);

                // Cache the results and add to response
                foreach ($uncachedNumbers as $trackingNumber) {
                    $shipment = $shipments->get($trackingNumber);
                    $formattedData = $shipment ? $this->formatShipmentData($shipment) : null;

                    // Use different TTL for found vs not found
                    $ttl = $formattedData ? $this->statusTtl : $this->notFoundTtl;

                    Cache::put($this->getCacheKey($trackingNumber), $formattedData, $ttl);

                    if ($formattedData !== null) {
                        Cache::put($this->getStaleCacheKey($trackingNumber), $formattedData, $this->staleTtl);
                    }

                    $results[$trackingNumber] = $formattedData;
                }
            } catch (\Throwable $exception) {
                Log::warning('Tracking database lookup failed, falling back to stale cache', [
                    'error' => $exception->getMessage(),
                    'tracking_numbers' => $uncachedNumbers,
                ]);

                foreach ($uncachedNumbers as $trackingNumber) {
                    $staleData = Cache::get($this->getStaleCacheKey($trackingNumber));
                    $results[$trackingNumber] = $staleData;
                }
            }
        }

        return $results;
    }

    /**
     * Get a single shipment by tracking number with caching
     */
    public function getShipment(string $trackingNumber): ?array
    {
        $results = $this->getShipments([$trackingNumber]);
        return $results[$trackingNumber] ?? null;
    }

    /**
     * Fetch shipments from database with eager loading
     */
    private function fetchShipmentsFromDatabase(array $trackingNumbers): Collection
    {
        return Shipment::with([
            'events' => function ($query) {
                $query->with(['facility', 'location'])
                      ->orderBy('event_time', 'desc');
            },
            'originFacility',
            'destinationFacility',
            'currentLocation'
        ])
        ->whereIn('tracking_number', $trackingNumbers)
        ->get()
        ->keyBy('tracking_number');
    }

    /**
     * Get shipment timeline with separate caching
     */
    public function getShipmentTimeline(string $trackingNumber): ?array
    {
        $cacheKey = $this->getTimelineCacheKey($trackingNumber);
        
        return Cache::remember($cacheKey, $this->timelineTtl, function () use ($trackingNumber) {
            $shipment = Shipment::with([
                'events' => function ($query) {
                    $query->with(['facility', 'location'])
                          ->orderBy('event_time', 'desc');
                }
            ])
            ->where('tracking_number', $trackingNumber)
            ->first();

            if (!$shipment) {
                return null;
            }

            return $this->formatter->formatTimeline($shipment->events);
        });
    }

    /**
     * Format shipment data for API response
     */
    private function formatShipmentData(Shipment $shipment): array
    {
        return $this->formatter->formatShipmentData($shipment);
    }

    /**
     * Get cache key for tracking number
     */
    private function getCacheKey(string $trackingNumber): string
    {
        return $this->cachePrefix . $trackingNumber;
    }

    /**
     * Get timeline cache key for tracking number
     */
    private function getTimelineCacheKey(string $trackingNumber): string
    {
        return $this->timelinePrefix . $trackingNumber;
    }

    /**
     * Get stale cache key for tracking number
     */
    private function getStaleCacheKey(string $trackingNumber): string
    {
        return $this->stalePrefix . $trackingNumber;
    }

    /**
     * Invalidate cache for a tracking number
     */
    public function invalidateCache(string $trackingNumber): void
    {
        Cache::forget($this->getCacheKey($trackingNumber));
        Cache::forget($this->getStaleCacheKey($trackingNumber));
        Cache::forget($this->getTimelineCacheKey($trackingNumber));
        Log::info('Cache invalidated for tracking number', ['tracking_number' => $trackingNumber]);
    }

    /**
     * Invalidate cache for multiple tracking numbers
     */
    public function invalidateCacheForMany(array $trackingNumbers): void
    {
        foreach ($trackingNumbers as $trackingNumber) {
            Cache::forget($this->getCacheKey($trackingNumber));
            Cache::forget($this->getStaleCacheKey($trackingNumber));
            Cache::forget($this->getTimelineCacheKey($trackingNumber));
        }
        Log::info('Cache invalidated for multiple tracking numbers', ['count' => count($trackingNumbers)]);
    }

    /**
     * Warm cache for frequently accessed shipments
     */
    public function warmCache(): void
    {
        if (!config('cache.shipment.warm_cache', true)) {
            return;
        }

        $count = config('cache.shipment.warm_cache_count', 100);
        
        // Get recently accessed shipments that are still active
        $shipments = Shipment::with([
            'events' => function ($query) {
                $query->with(['facility', 'location'])
                      ->orderBy('event_time', 'desc');
            },
            'originFacility',
            'destinationFacility',
            'currentLocation'
        ])
        ->whereNotIn('current_status', ['delivered', 'returned'])
        ->orderBy('updated_at', 'desc')
        ->limit($count)
        ->get();

        foreach ($shipments as $shipment) {
            $formattedData = $this->formatShipmentData($shipment);
            Cache::put($this->getCacheKey($shipment->tracking_number), $formattedData, $this->statusTtl);
            Cache::put($this->getStaleCacheKey($shipment->tracking_number), $formattedData, $this->staleTtl);
        }

        Log::info('Cache warmed for active shipments', ['count' => $shipments->count()]);
    }

    /**
     * Prefetch shipments into cache
     */
    public function prefetchShipments(array $trackingNumbers): void
    {
        $uncachedNumbers = [];

        foreach ($trackingNumbers as $trackingNumber) {
            $cacheKey = $this->getCacheKey($trackingNumber);
            if (!Cache::has($cacheKey)) {
                $uncachedNumbers[] = $trackingNumber;
            }
        }

        if (empty($uncachedNumbers)) {
            return;
        }

        $shipments = $this->fetchShipmentsFromDatabase($uncachedNumbers);

        foreach ($uncachedNumbers as $trackingNumber) {
            $shipment = $shipments->get($trackingNumber);
            $formattedData = $shipment ? $this->formatShipmentData($shipment) : null;
            $ttl = $formattedData ? $this->statusTtl : $this->notFoundTtl;
            Cache::put($this->getCacheKey($trackingNumber), $formattedData, $ttl);

            if ($formattedData !== null) {
                Cache::put($this->getStaleCacheKey($trackingNumber), $formattedData, $this->staleTtl);
            }
        }

        Log::info('Shipments prefetched into cache', ['count' => count($uncachedNumbers)]);
    }

    /**
     * Record cache metrics
     */
    private function recordCacheMetrics(int $hits, int $misses): void
    {
        try {
            $redis = Redis::connection();
            $timestamp = now()->format('Y-m-d-H');
            
            $redis->hincrby($this->metricsPrefix . 'hourly:' . $timestamp, 'hits', $hits);
            $redis->hincrby($this->metricsPrefix . 'hourly:' . $timestamp, 'misses', $misses);
            $redis->hincrby($this->metricsPrefix . 'hourly:' . $timestamp, 'requests', $hits + $misses);
            
            // Set expiry for hourly metrics (48 hours)
            $redis->expire($this->metricsPrefix . 'hourly:' . $timestamp, 172800);
        } catch (\Exception $e) {
            Log::warning('Failed to record cache metrics', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cache metrics
     */
    public function getCacheMetrics(): array
    {
        try {
            $redis = Redis::connection();
            $currentHour = now()->format('Y-m-d-H');
            $previousHour = now()->subHour()->format('Y-m-d-H');
            
            $currentMetrics = $redis->hgetall($this->metricsPrefix . 'hourly:' . $currentHour);
            $previousMetrics = $redis->hgetall($this->metricsPrefix . 'hourly:' . $previousHour);
            
            $hits = (int)($currentMetrics['hits'] ?? 0);
            $misses = (int)($currentMetrics['misses'] ?? 0);
            $requests = (int)($currentMetrics['requests'] ?? 0);
            
            return [
                'current_hour' => [
                    'hits' => $hits,
                    'misses' => $misses,
                    'requests' => $requests,
                    'hit_rate' => $requests > 0 ? round(($hits / $requests) * 100, 2) : 0,
                ],
                'previous_hour' => [
                    'hits' => (int)($previousMetrics['hits'] ?? 0),
                    'misses' => (int)($previousMetrics['misses'] ?? 0),
                    'requests' => (int)($previousMetrics['requests'] ?? 0),
                    'hit_rate' => (int)($previousMetrics['requests'] ?? 0) > 0 
                        ? round(((int)($previousMetrics['hits'] ?? 0) / (int)$previousMetrics['requests']) * 100, 2) 
                        : 0,
                ],
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get cache metrics', ['error' => $e->getMessage()]);
            return [
                'current_hour' => ['hits' => 0, 'misses' => 0, 'requests' => 0, 'hit_rate' => 0],
                'previous_hour' => ['hits' => 0, 'misses' => 0, 'requests' => 0, 'hit_rate' => 0],
            ];
        }
    }

    /**
     * Validate tracking number format
     */
    public function isValidTrackingNumber(string $trackingNumber): bool
    {
        return preg_match('/^[A-Z]{2}[0-9]{10}$/', $trackingNumber) === 1;
    }

    /**
     * Get tracking statistics for monitoring
     */
    public function getTrackingStats(): array
    {
        return [
            'total_shipments' => Shipment::count(),
            'active_shipments' => Shipment::whereNotIn('current_status', ['delivered', 'returned'])->count(),
            'delivered_today' => Shipment::where('current_status', 'delivered')
                ->whereDate('updated_at', today())
                ->count(),
            'cache_metrics' => $this->getCacheMetrics(),
        ];
    }
}
