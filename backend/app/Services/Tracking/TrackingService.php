<?php

namespace App\Services\Tracking;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    private const CACHE_TTL = 30; // 30 seconds as per requirements
    private const CACHE_PREFIX = 'shipment:';

    public function __construct(
        private ShipmentFormatter $formatter
    ) {}

    /**
     * Get multiple shipments by tracking numbers with caching
     */
    public function getShipments(array $trackingNumbers): array
    {
        $results = [];
        $uncachedNumbers = [];

        // Check cache first
        foreach ($trackingNumbers as $trackingNumber) {
            $cacheKey = $this->getCacheKey($trackingNumber);
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                $results[$trackingNumber] = $cached;
            } else {
                $uncachedNumbers[] = $trackingNumber;
            }
        }

        // Fetch uncached shipments from database
        if (!empty($uncachedNumbers)) {
            $shipments = Shipment::with([
                'events' => function ($query) {
                    $query->with(['facility', 'location'])
                          ->orderBy('event_time', 'desc');
                },
                'originFacility',
                'destinationFacility',
                'currentLocation'
            ])
            ->whereIn('tracking_number', $uncachedNumbers)
            ->get()
            ->keyBy('tracking_number');

            // Cache the results and add to response
            foreach ($uncachedNumbers as $trackingNumber) {
                $shipment = $shipments->get($trackingNumber);
                $formattedData = $shipment ? $this->formatShipmentData($shipment) : null;
                
                // Cache both found and not found results
                Cache::put($this->getCacheKey($trackingNumber), $formattedData, self::CACHE_TTL);
                $results[$trackingNumber] = $formattedData;
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
        return self::CACHE_PREFIX . $trackingNumber;
    }

    /**
     * Invalidate cache for a tracking number
     */
    public function invalidateCache(string $trackingNumber): void
    {
        Cache::forget($this->getCacheKey($trackingNumber));
        Log::info('Cache invalidated for tracking number', ['tracking_number' => $trackingNumber]);
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
        ];
    }
}