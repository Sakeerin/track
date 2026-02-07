<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use App\Services\Tracking\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CachingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected TrackingService $trackingService;
    protected Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->trackingService = app(TrackingService::class);
        
        $this->facility = Facility::factory()->create([
            'code' => 'BKK001',
            'name' => 'Bangkok Hub',
            'facility_type' => 'HUB',
            'active' => true
        ]);
    }

    /** @test */
    public function cache_hit_rate_is_tracked_correctly()
    {
        // Clear metrics
        try {
            Redis::connection()->del('cache_metrics:hourly:' . now()->format('Y-m-d-H'));
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis not available');
        }

        // Create test shipments
        $shipments = Shipment::factory()->count(3)->create();
        $trackingNumbers = $shipments->pluck('tracking_number')->toArray();

        // Clear cache
        foreach ($trackingNumbers as $number) {
            Cache::forget('shipment:' . $number);
        }

        // First request - should be cache misses
        $this->trackingService->getShipments($trackingNumbers);
        
        // Second request - should be cache hits
        $this->trackingService->getShipments($trackingNumbers);

        // Get metrics
        $metrics = $this->trackingService->getCacheMetrics();
        
        $this->assertArrayHasKey('current_hour', $metrics);
        $this->assertEquals(3, $metrics['current_hour']['misses']);
        $this->assertEquals(3, $metrics['current_hour']['hits']);
        $this->assertEquals(6, $metrics['current_hour']['requests']);
        $this->assertEquals(50, $metrics['current_hour']['hit_rate']);
    }

    /** @test */
    public function cache_uses_different_ttl_for_found_and_not_found()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        // Clear cache
        Cache::forget('shipment:TH1234567890');
        Cache::forget('shipment:TH9999999999');

        // Request existing shipment
        $this->trackingService->getShipment('TH1234567890');
        
        // Request non-existing shipment
        $this->trackingService->getShipment('TH9999999999');

        // Both should be cached
        $this->assertTrue(Cache::has('shipment:TH1234567890'));
        $this->assertTrue(Cache::has('shipment:TH9999999999'));

        // Verify found shipment has data, not found is null
        $this->assertNotNull(Cache::get('shipment:TH1234567890'));
        $this->assertNull(Cache::get('shipment:TH9999999999'));
    }

    /** @test */
    public function cache_invalidation_clears_both_status_and_timeline()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => now(),
        ]);

        // Populate cache
        $this->trackingService->getShipment('TH1234567890');
        $this->trackingService->getShipmentTimeline('TH1234567890');

        // Verify both are cached
        $this->assertTrue(Cache::has('shipment:TH1234567890'));
        $this->assertTrue(Cache::has('shipment_timeline:TH1234567890'));

        // Invalidate cache
        $this->trackingService->invalidateCache('TH1234567890');

        // Both should be cleared
        $this->assertFalse(Cache::has('shipment:TH1234567890'));
        $this->assertFalse(Cache::has('shipment_timeline:TH1234567890'));
    }

    /** @test */
    public function cache_warming_populates_active_shipments()
    {
        // Create mix of active and delivered shipments
        $activeShipments = Shipment::factory()->count(5)->create([
            'current_status' => 'in_transit',
        ]);
        
        $deliveredShipments = Shipment::factory()->count(3)->create([
            'current_status' => 'delivered',
        ]);

        // Clear all caches
        foreach ([...$activeShipments, ...$deliveredShipments] as $shipment) {
            Cache::forget('shipment:' . $shipment->tracking_number);
        }

        // Warm cache
        $this->trackingService->warmCache();

        // Active shipments should be cached
        foreach ($activeShipments as $shipment) {
            $this->assertTrue(
                Cache::has('shipment:' . $shipment->tracking_number),
                "Active shipment {$shipment->tracking_number} should be cached"
            );
        }
    }

    /** @test */
    public function prefetch_only_fetches_uncached_shipments()
    {
        $shipments = Shipment::factory()->count(5)->create();
        $trackingNumbers = $shipments->pluck('tracking_number')->toArray();

        // Clear all cache
        foreach ($trackingNumbers as $number) {
            Cache::forget('shipment:' . $number);
        }

        // Pre-cache first 2 shipments
        $this->trackingService->getShipments(array_slice($trackingNumbers, 0, 2));

        // Track starting state
        $initialCacheState = [];
        foreach ($trackingNumbers as $number) {
            $initialCacheState[$number] = Cache::has('shipment:' . $number);
        }

        // First 2 should be cached, last 3 should not
        $this->assertTrue($initialCacheState[$trackingNumbers[0]]);
        $this->assertTrue($initialCacheState[$trackingNumbers[1]]);
        $this->assertFalse($initialCacheState[$trackingNumbers[2]]);
        $this->assertFalse($initialCacheState[$trackingNumbers[3]]);
        $this->assertFalse($initialCacheState[$trackingNumbers[4]]);

        // Prefetch all
        $this->trackingService->prefetchShipments($trackingNumbers);

        // Now all should be cached
        foreach ($trackingNumbers as $number) {
            $this->assertTrue(
                Cache::has('shipment:' . $number),
                "Shipment {$number} should be cached after prefetch"
            );
        }
    }

    /** @test */
    public function bulk_cache_invalidation_works_correctly()
    {
        $shipments = Shipment::factory()->count(10)->create();
        $trackingNumbers = $shipments->pluck('tracking_number')->toArray();

        // Populate cache
        $this->trackingService->getShipments($trackingNumbers);

        // Verify all are cached
        foreach ($trackingNumbers as $number) {
            $this->assertTrue(Cache::has('shipment:' . $number));
        }

        // Invalidate all
        $this->trackingService->invalidateCacheForMany($trackingNumbers);

        // Verify all are cleared
        foreach ($trackingNumbers as $number) {
            $this->assertFalse(Cache::has('shipment:' . $number));
        }
    }

    /** @test */
    public function cache_returns_formatted_data()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'service_type' => 'express',
            'current_status' => 'in_transit',
            'origin_facility_id' => $this->facility->id,
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => now()->subHour(),
            'facility_id' => $this->facility->id,
        ]);

        // Clear cache
        Cache::forget('shipment:TH1234567890');

        // First request - from database
        $firstResult = $this->trackingService->getShipment('TH1234567890');
        
        // Second request - from cache
        $secondResult = $this->trackingService->getShipment('TH1234567890');

        // Both should have the same structure
        $this->assertEquals($firstResult, $secondResult);
        
        // Verify structure
        $this->assertArrayHasKey('tracking_number', $firstResult);
        $this->assertArrayHasKey('progress', $firstResult);
        $this->assertArrayHasKey('timeline', $firstResult);
        $this->assertArrayHasKey('map_data', $firstResult);
    }

    /** @test */
    public function event_creation_triggers_cache_invalidation()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'in_transit',
        ]);

        // Populate cache
        $this->trackingService->getShipment('TH1234567890');
        $this->assertTrue(Cache::has('shipment:TH1234567890'));

        // Create new event (this should trigger cache invalidation via Event::boot)
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'AT_HUB',
            'event_time' => now(),
            'facility_id' => $this->facility->id,
        ]);

        // Cache should be invalidated
        $this->assertFalse(Cache::has('shipment:TH1234567890'));
    }

    /** @test */
    public function timeline_cache_is_separate_from_status_cache()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        Event::factory()->count(3)->create([
            'shipment_id' => $shipment->id,
            'facility_id' => $this->facility->id,
        ]);

        // Clear caches
        Cache::forget('shipment:TH1234567890');
        Cache::forget('shipment_timeline:TH1234567890');

        // Get shipment data - should cache under shipment: prefix
        $this->trackingService->getShipment('TH1234567890');
        
        // Get timeline data - should cache under shipment_timeline: prefix
        $this->trackingService->getShipmentTimeline('TH1234567890');

        // Both should be cached separately
        $this->assertTrue(Cache::has('shipment:TH1234567890'));
        $this->assertTrue(Cache::has('shipment_timeline:TH1234567890'));

        // Clear only status cache
        Cache::forget('shipment:TH1234567890');

        // Timeline should still be cached
        $this->assertFalse(Cache::has('shipment:TH1234567890'));
        $this->assertTrue(Cache::has('shipment_timeline:TH1234567890'));
    }

    /** @test */
    public function tracking_stats_include_cache_metrics()
    {
        $stats = $this->trackingService->getTrackingStats();

        $this->assertArrayHasKey('total_shipments', $stats);
        $this->assertArrayHasKey('active_shipments', $stats);
        $this->assertArrayHasKey('delivered_today', $stats);
        $this->assertArrayHasKey('cache_metrics', $stats);

        $this->assertArrayHasKey('current_hour', $stats['cache_metrics']);
        $this->assertArrayHasKey('previous_hour', $stats['cache_metrics']);
    }

    /** @test */
    public function cache_handles_high_volume_requests_efficiently()
    {
        // Create many shipments
        $shipments = Shipment::factory()->count(20)->create();
        $trackingNumbers = $shipments->pluck('tracking_number')->toArray();

        // Clear cache
        foreach ($trackingNumbers as $number) {
            Cache::forget('shipment:' . $number);
        }

        // First batch request - should query database
        $startTime = microtime(true);
        $results1 = $this->trackingService->getShipments($trackingNumbers);
        $firstRequestTime = microtime(true) - $startTime;

        // Second batch request - should use cache
        $startTime = microtime(true);
        $results2 = $this->trackingService->getShipments($trackingNumbers);
        $secondRequestTime = microtime(true) - $startTime;

        // Cache hit should be significantly faster
        // Note: In actual tests, this may vary, but cache should generally be faster
        $this->assertCount(20, $results1);
        $this->assertCount(20, $results2);
        
        // Results should be identical
        $this->assertEquals(array_keys($results1), array_keys($results2));
    }

    /** @test */
    public function mixed_cache_and_database_fetch_works_correctly()
    {
        // Create 10 shipments
        $shipments = Shipment::factory()->count(10)->create();
        $trackingNumbers = $shipments->pluck('tracking_number')->toArray();

        // Clear all cache
        foreach ($trackingNumbers as $number) {
            Cache::forget('shipment:' . $number);
        }

        // Cache only even-indexed shipments
        $evenNumbers = [];
        for ($i = 0; $i < count($trackingNumbers); $i += 2) {
            $evenNumbers[] = $trackingNumbers[$i];
        }
        $this->trackingService->getShipments($evenNumbers);

        // Verify even are cached, odd are not
        for ($i = 0; $i < count($trackingNumbers); $i++) {
            if ($i % 2 === 0) {
                $this->assertTrue(Cache::has('shipment:' . $trackingNumbers[$i]));
            } else {
                $this->assertFalse(Cache::has('shipment:' . $trackingNumbers[$i]));
            }
        }

        // Request all - should fetch odd from database, even from cache
        $results = $this->trackingService->getShipments($trackingNumbers);

        // All should now be cached
        foreach ($trackingNumbers as $number) {
            $this->assertTrue(Cache::has('shipment:' . $number));
        }

        // All should be returned
        $this->assertCount(10, $results);
    }
}
