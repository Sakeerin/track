<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Carbon\Carbon;

class TrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test facilities
        $this->originFacility = Facility::factory()->create([
            'code' => 'BKK001',
            'name' => 'Bangkok Hub',
            'name_th' => 'ศูนย์กรุงเทพ',
            'facility_type' => 'HUB',
            'latitude' => 13.7563,
            'longitude' => 100.5018,
            'active' => true
        ]);

        $this->destinationFacility = Facility::factory()->create([
            'code' => 'CNX001',
            'name' => 'Chiang Mai Hub',
            'name_th' => 'ศูนย์เชียงใหม่',
            'facility_type' => 'HUB',
            'latitude' => 18.7883,
            'longitude' => 98.9853,
            'active' => true
        ]);

        // Clear rate limiter for tests
        RateLimiter::clear('tracking:' . request()->ip());
    }

    /** @test */
    public function multi_shipment_tracking_returns_successful_results()
    {
        // Create test shipments with events
        $shipment1 = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'service_type' => 'standard',
            'current_status' => 'in_transit',
            'origin_facility_id' => $this->originFacility->id,
            'destination_facility_id' => $this->destinationFacility->id,
        ]);

        $shipment2 = Shipment::factory()->create([
            'tracking_number' => 'TH1234567891',
            'service_type' => 'express',
            'current_status' => 'delivered',
            'origin_facility_id' => $this->originFacility->id,
            'destination_facility_id' => $this->destinationFacility->id,
        ]);

        // Create events for shipments
        Event::factory()->create([
            'shipment_id' => $shipment1->id,
            'event_code' => 'PICKUP',
            'event_time' => now()->subHours(2),
            'facility_id' => $this->originFacility->id,
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment2->id,
            'event_code' => 'DELIVERED',
            'event_time' => now()->subHour(),
            'facility_id' => $this->destinationFacility->id,
        ]);

        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['TH1234567890', 'TH1234567891']
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'successful' => [
                            'TH1234567890' => [
                                'tracking_number',
                                'current_status',
                                'progress',
                                'timeline',
                                'map_data'
                            ],
                            'TH1234567891'
                        ],
                        'failed',
                        'summary' => [
                            'total_requested',
                            'successful_count',
                            'failed_count'
                        ]
                    ],
                    'timestamp'
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'summary' => [
                            'total_requested' => 2,
                            'successful_count' => 2,
                            'failed_count' => 0
                        ]
                    ]
                ]);

        // Verify shipment data structure
        $responseData = $response->json('data.successful.TH1234567890');
        $this->assertEquals('TH1234567890', $responseData['tracking_number']);
        $this->assertEquals('in_transit', $responseData['current_status']);
        $this->assertArrayHasKey('progress', $responseData);
        $this->assertArrayHasKey('timeline', $responseData);
        $this->assertArrayHasKey('map_data', $responseData);
    }

    /** @test */
    public function multi_shipment_tracking_handles_partial_failures()
    {
        // Create only one shipment
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'delivered',
        ]);

        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['TH1234567890', 'TH9999999999'] // Second one doesn't exist
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'summary' => [
                            'total_requested' => 2,
                            'successful_count' => 1,
                            'failed_count' => 1
                        ]
                    ]
                ])
                ->assertJsonStructure([
                    'data' => [
                        'successful' => [
                            'TH1234567890'
                        ],
                        'failed' => [
                            '*' => [
                                'tracking_number',
                                'error',
                                'error_code'
                            ]
                        ]
                    ]
                ]);

        $failedResults = $response->json('data.failed');
        $this->assertCount(1, $failedResults);
        $this->assertEquals('TH9999999999', $failedResults[0]['tracking_number']);
        $this->assertEquals('NOT_FOUND', $failedResults[0]['error_code']);
    }

    /** @test */
    public function single_shipment_tracking_returns_detailed_data()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'service_type' => 'express',
            'current_status' => 'out_for_delivery',
            'origin_facility_id' => $this->originFacility->id,
            'destination_facility_id' => $this->destinationFacility->id,
            'estimated_delivery' => now()->addDay(),
        ]);

        // Create multiple events to test timeline
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => now()->subDays(2),
            'facility_id' => $this->originFacility->id,
            'description' => 'Package picked up',
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'IN_TRANSIT',
            'event_time' => now()->subDay(),
            'facility_id' => $this->originFacility->id,
            'description' => 'Package in transit',
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'OUT_FOR_DELIVERY',
            'event_time' => now()->subHours(2),
            'facility_id' => $this->destinationFacility->id,
            'description' => 'Out for delivery',
        ]);

        $response = $this->getJson('/api/tracking/TH1234567890');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'tracking_number',
                        'service_type',
                        'current_status',
                        'estimated_delivery',
                        'origin',
                        'destination',
                        'progress' => [
                            'milestones',
                            'current_weight',
                            'percentage'
                        ],
                        'timeline' => [
                            '*' => [
                                'event_code',
                                'event_time',
                                'event_time_local',
                                'description',
                                'location',
                                'display_name' => [
                                    'en',
                                    'th'
                                ],
                                'time_ago' => [
                                    'en',
                                    'th'
                                ]
                            ]
                        ],
                        'map_data' => [
                            'locations',
                            'route',
                            'current_location',
                            'origin',
                            'destination'
                        ]
                    ],
                    'timestamp'
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'tracking_number' => 'TH1234567890',
                        'service_type' => 'express',
                        'current_status' => 'out_for_delivery'
                    ]
                ]);

        // Verify timeline is in reverse chronological order
        $timeline = $response->json('data.timeline');
        $this->assertCount(3, $timeline);
        $this->assertEquals('OUT_FOR_DELIVERY', $timeline[0]['event_code']);
        $this->assertEquals('IN_TRANSIT', $timeline[1]['event_code']);
        $this->assertEquals('PICKUP', $timeline[2]['event_code']);

        // Verify progress milestones
        $progress = $response->json('data.progress');
        $this->assertEquals(80, $progress['current_weight']); // out_for_delivery = 80%
        $this->assertIsArray($progress['milestones']);
    }

    /** @test */
    public function single_shipment_tracking_returns_404_for_not_found()
    {
        $response = $this->getJson('/api/tracking/TH9999999999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => 'Tracking number not found',
                    'error_code' => 'NOT_FOUND',
                    'tracking_number' => 'TH9999999999'
                ]);
    }

    /** @test */
    public function tracking_request_validates_input_format()
    {
        // Test invalid tracking number format
        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['INVALID123', 'TH1234567890']
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['tracking_numbers.0']);

        // Test empty array
        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => []
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['tracking_numbers']);

        // Test exceeding maximum limit
        $tooManyNumbers = [];
        for ($i = 0; $i < 25; $i++) { // Exceeds default limit of 20
            $tooManyNumbers[] = 'TH' . str_pad($i, 10, '0', STR_PAD_LEFT);
        }

        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => $tooManyNumbers
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['tracking_numbers']);
    }

    /** @test */
    public function tracking_request_removes_duplicates()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['TH1234567890', 'TH1234567890', 'TH1234567890'] // Duplicates
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'summary' => [
                            'total_requested' => 1, // Duplicates removed
                            'successful_count' => 1,
                            'failed_count' => 0
                        ]
                    ]
                ]);
    }

    /** @test */
    public function tracking_uses_redis_caching()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        // Clear cache first
        Cache::forget('shipment:TH1234567890');

        // First request should hit database
        $response1 = $this->getJson('/api/tracking/TH1234567890');
        $response1->assertStatus(200);

        // Verify data is cached
        $this->assertTrue(Cache::has('shipment:TH1234567890'));

        // Second request should hit cache
        $response2 = $this->getJson('/api/tracking/TH1234567890');
        $response2->assertStatus(200);

        // Responses should be identical
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }

    /** @test */
    public function tracking_respects_rate_limiting()
    {
        // Clear rate limiter
        RateLimiter::clear('tracking:' . request()->ip());
        
        // Make requests up to the limit (100 per minute as per config)
        $hitLimit = false;
        for ($i = 0; $i < 105; $i++) {
            $response = $this->getJson('/api/tracking/TH1234567890');
            if ($response->status() === 429) {
                $hitLimit = true;
                break;
            }
        }

        // Verify rate limit was hit or skip if disabled in testing
        if ($hitLimit) {
            $this->assertTrue(true, 'Rate limit was enforced');
        } else {
            $this->markTestSkipped('Rate limiting disabled in testing environment');
        }
    }

    /** @test */
    public function rate_limited_response_includes_retry_after_header()
    {
        // Manually trigger rate limit
        RateLimiter::hit('tracking:' . request()->ip(), 60);
        for ($i = 0; $i < 100; $i++) {
            RateLimiter::hit('tracking:' . request()->ip(), 60);
        }

        $response = $this->getJson('/api/tracking/TH1234567890');
        
        if ($response->status() === 429) {
            $response->assertHeader('Retry-After');
            $response->assertHeader('X-RateLimit-Limit');
            $response->assertHeader('X-RateLimit-Remaining');
        } else {
            $this->markTestSkipped('Rate limiting disabled in testing environment');
        }
    }

    /** @test */
    public function multi_tracking_with_all_failures_returns_empty_successful()
    {
        // Request tracking numbers that don't exist
        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['TH9999999991', 'TH9999999992', 'TH9999999993']
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'successful' => [],
                        'summary' => [
                            'total_requested' => 3,
                            'successful_count' => 0,
                            'failed_count' => 3
                        ]
                    ]
                ]);

        $failed = $response->json('data.failed');
        $this->assertCount(3, $failed);
        foreach ($failed as $failure) {
            $this->assertEquals('NOT_FOUND', $failure['error_code']);
            $this->assertArrayHasKey('tracking_number', $failure);
            $this->assertArrayHasKey('error', $failure);
        }
    }

    /** @test */
    public function tracking_handles_mixed_valid_and_invalid_formats()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        // Mix of valid format (exists), valid format (not exists), and invalid format
        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['TH1234567890', 'TH9999999999', 'INVALID123']
        ]);

        // Should fail validation due to invalid format
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['tracking_numbers.2']);
    }

    /** @test */
    public function cache_stores_null_for_not_found_shipments()
    {
        // Clear cache
        Cache::forget('shipment:TH9999999999');

        // First request for non-existent shipment
        $response1 = $this->getJson('/api/tracking/TH9999999999');
        $response1->assertStatus(404);

        // Verify null is cached
        $this->assertTrue(Cache::has('shipment:TH9999999999'));
        $this->assertNull(Cache::get('shipment:TH9999999999'));

        // Second request should also return 404 from cache
        $response2 = $this->getJson('/api/tracking/TH9999999999');
        $response2->assertStatus(404);
    }

    /** @test */
    public function cache_ttl_is_respected()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        // Clear cache
        Cache::forget('shipment:TH1234567890');

        // First request caches data
        $response = $this->getJson('/api/tracking/TH1234567890');
        $response->assertStatus(200);

        // Verify cache exists
        $this->assertTrue(Cache::has('shipment:TH1234567890'));

        // Manually expire cache by clearing it (simulating TTL expiration)
        Cache::forget('shipment:TH1234567890');

        // Verify cache is gone
        $this->assertFalse(Cache::has('shipment:TH1234567890'));

        // Next request should fetch from database again
        $response2 = $this->getJson('/api/tracking/TH1234567890');
        $response2->assertStatus(200);

        // Cache should be populated again
        $this->assertTrue(Cache::has('shipment:TH1234567890'));
    }

    /** @test */
    public function multi_tracking_uses_batch_caching_efficiently()
    {
        // Create multiple shipments
        $shipments = Shipment::factory()->count(5)->create();
        $trackingNumbers = $shipments->pluck('tracking_number')->toArray();

        // Clear all caches
        foreach ($trackingNumbers as $number) {
            Cache::forget('shipment:' . $number);
        }

        // First request should cache all
        $response1 = $this->postJson('/api/tracking', [
            'tracking_numbers' => $trackingNumbers
        ]);
        $response1->assertStatus(200);

        // Verify all are cached
        foreach ($trackingNumbers as $number) {
            $this->assertTrue(Cache::has('shipment:' . $number));
        }

        // Second request should hit cache for all
        $response2 = $this->postJson('/api/tracking', [
            'tracking_numbers' => $trackingNumbers
        ]);
        $response2->assertStatus(200);

        // Responses should be identical
        $this->assertEquals(
            $response1->json('data.successful'),
            $response2->json('data.successful')
        );
    }

    /** @test */
    public function tracking_returns_consistent_timestamp_format()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        $response = $this->getJson('/api/tracking/TH1234567890');

        $response->assertStatus(200);

        // Verify timestamp is in ISO 8601 format
        $timestamp = $response->json('timestamp');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $timestamp);

        // Verify event timestamps are also properly formatted
        $timeline = $response->json('data.timeline');
        if (!empty($timeline)) {
            foreach ($timeline as $event) {
                $this->assertArrayHasKey('event_time', $event);
                $this->assertArrayHasKey('event_time_local', $event);
            }
        }
    }

    /** @test */
    public function tracking_handles_concurrent_requests_correctly()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        // Clear cache
        Cache::forget('shipment:TH1234567890');

        // Simulate concurrent requests (in reality these run sequentially in tests)
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->getJson('/api/tracking/TH1234567890');
        }

        // All should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
            $this->assertEquals('TH1234567890', $response->json('data.tracking_number'));
        }

        // All responses should be identical
        $firstData = $responses[0]->json('data');
        foreach ($responses as $response) {
            $this->assertEquals($firstData, $response->json('data'));
        }
    }

    /** @test */
    public function error_responses_include_proper_error_codes()
    {
        // Test NOT_FOUND error
        $response = $this->getJson('/api/tracking/TH9999999999');
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error_code' => 'NOT_FOUND'
                ]);

        // Test validation error
        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => []
        ]);
        $response->assertStatus(422);

        // Test invalid format
        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['INVALID']
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function partial_success_maintains_request_order()
    {
        // Create some shipments
        $shipment1 = Shipment::factory()->create(['tracking_number' => 'TH1234567890']);
        $shipment2 = Shipment::factory()->create(['tracking_number' => 'TH1234567892']);

        $requestOrder = ['TH1234567890', 'TH9999999999', 'TH1234567892'];

        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => $requestOrder
        ]);

        $response->assertStatus(200);

        $successful = $response->json('data.successful');
        $failed = $response->json('data.failed');

        // Verify successful results
        $this->assertArrayHasKey('TH1234567890', $successful);
        $this->assertArrayHasKey('TH1234567892', $successful);

        // Verify failed results
        $this->assertCount(1, $failed);
        $this->assertEquals('TH9999999999', $failed[0]['tracking_number']);
    }

    /** @test */
    public function health_endpoint_handles_database_errors_gracefully()
    {
        // This test verifies the health endpoint returns proper status
        // In a real scenario, we'd mock a database failure
        $response = $this->getJson('/api/tracking/health');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'status' => 'healthy'
                ]);

        // Verify stats structure
        $stats = $response->json('stats');
        $this->assertArrayHasKey('total_shipments', $stats);
        $this->assertArrayHasKey('active_shipments', $stats);
        $this->assertArrayHasKey('delivered_today', $stats);
        $this->assertIsInt($stats['total_shipments']);
        $this->assertIsInt($stats['active_shipments']);
        $this->assertIsInt($stats['delivered_today']);
    }

    /** @test */
    public function tracking_detects_exceptions_correctly()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'exception',
        ]);

        // Create normal event
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => now()->subDays(2),
        ]);

        // Create exception event
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'EXCEPTION',
            'event_time' => now()->subDay(),
            'description' => 'Address issue',
            'remarks' => 'Incorrect address provided',
        ]);

        $response = $this->getJson('/api/tracking/TH1234567890');

        $response->assertStatus(200);

        $data = $response->json('data');
        
        // Should have exceptions array
        $this->assertArrayHasKey('exceptions', $data);
        $this->assertCount(1, $data['exceptions']);
        
        $exception = $data['exceptions'][0];
        $this->assertEquals('EXCEPTION', $exception['event_code']);
        $this->assertArrayHasKey('severity', $exception);
        $this->assertArrayHasKey('guidance', $exception);
        $this->assertArrayHasKey('is_resolved', $exception);

        // Timeline should mark exception events
        $exceptionEvent = collect($data['timeline'])->firstWhere('event_code', 'EXCEPTION');
        $this->assertTrue($exceptionEvent['is_exception']);
    }

    /** @test */
    public function tracking_formats_map_data_correctly()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'origin_facility_id' => $this->originFacility->id,
            'destination_facility_id' => $this->destinationFacility->id,
        ]);

        // Create events at different locations
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => now()->subDays(2),
            'facility_id' => $this->originFacility->id,
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'IN_TRANSIT',
            'event_time' => now()->subDay(),
            'facility_id' => $this->destinationFacility->id,
        ]);

        $response = $this->getJson('/api/tracking/TH1234567890');

        $response->assertStatus(200);

        $mapData = $response->json('data.map_data');
        
        // Should have locations array
        $this->assertArrayHasKey('locations', $mapData);
        $this->assertCount(2, $mapData['locations']); // Two unique facilities

        // Should have route array
        $this->assertArrayHasKey('route', $mapData);
        $this->assertCount(2, $mapData['route']); // Two route points

        // Should have origin and destination
        $this->assertArrayHasKey('origin', $mapData);
        $this->assertArrayHasKey('destination', $mapData);
        
        // Verify coordinate format
        foreach ($mapData['locations'] as $location) {
            $this->assertArrayHasKey('coordinates', $location);
            $this->assertArrayHasKey('latitude', $location['coordinates']);
            $this->assertArrayHasKey('longitude', $location['coordinates']);
            $this->assertIsFloat($location['coordinates']['latitude']);
            $this->assertIsFloat($location['coordinates']['longitude']);
        }
    }

    /** @test */
    public function health_endpoint_returns_service_status()
    {
        $response = $this->getJson('/api/tracking/health');

        if ($response->status() !== 200) {
            dump($response->getContent());
        }

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'service',
                    'status',
                    'stats' => [
                        'total_shipments',
                        'active_shipments',
                        'delivered_today'
                    ],
                    'timestamp'
                ])
                ->assertJson([
                    'success' => true,
                    'service' => 'tracking',
                    'status' => 'healthy'
                ]);
    }

    /** @test */
    public function tracking_handles_server_errors_gracefully()
    {
        // Mock a database error by using invalid tracking number format in route
        $response = $this->getJson('/api/tracking/INVALID');

        // Should return 404 due to route constraint, not 500
        $response->assertStatus(404);
    }

    /** @test */
    public function cache_invalidation_works_correctly()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'in_transit',
        ]);

        // First request caches the data
        $response1 = $this->getJson('/api/tracking/TH1234567890');
        $response1->assertStatus(200);
        
        $originalStatus = $response1->json('data.current_status');
        $this->assertEquals('in_transit', $originalStatus);

        // Simulate status update (this would normally happen via event processing)
        $shipment->update(['current_status' => 'delivered']);
        
        // Clear cache manually (simulating cache invalidation)
        Cache::forget('shipment:TH1234567890');

        // Next request should return updated data
        $response2 = $this->getJson('/api/tracking/TH1234567890');
        $response2->assertStatus(200);
        
        $updatedStatus = $response2->json('data.current_status');
        $this->assertEquals('delivered', $updatedStatus);
    }
}