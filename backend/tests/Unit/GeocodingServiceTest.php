<?php

namespace Tests\Unit;

use App\Models\Facility;
use App\Services\Ingestion\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeocodingService();
    }

    /** @test */
    public function resolves_existing_facility_by_code()
    {
        $facility = Facility::factory()->create([
            'code' => 'BKK001',
            'name' => 'Bangkok Hub',
            'latitude' => 13.7563,
            'longitude' => 100.5018,
            'address' => 'Bangkok, Thailand'
        ]);

        $result = $this->service->resolveFacilityLocation('BKK001');

        $this->assertNotNull($result);
        $this->assertEquals($facility->id, $result['facility_id']);
        $this->assertEquals(13.7563, $result['latitude']);
        $this->assertEquals(100.5018, $result['longitude']);
        $this->assertEquals('Bangkok, Thailand', $result['address']);
        $this->assertEquals('database', $result['source']);
    }

    /** @test */
    public function geocodes_location_when_facility_not_found()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '13.7563',
                    'lon' => '100.5018',
                    'display_name' => 'Bangkok, Thailand'
                ]
            ])
        ]);

        $result = $this->service->resolveFacilityLocation('UNKNOWN001', 'Bangkok Hub');

        $this->assertNotNull($result);
        $this->assertEquals(13.7563, $result['latitude']);
        $this->assertEquals(100.5018, $result['longitude']);
        $this->assertEquals('Bangkok, Thailand', $result['address']);
        $this->assertEquals('geocoded', $result['source']);
    }

    /** @test */
    public function creates_facility_from_successful_geocoding()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '18.7883',
                    'lon' => '98.9853',
                    'display_name' => 'Chiang Mai, Thailand'
                ]
            ])
        ]);

        $result = $this->service->resolveFacilityLocation('CNX001', 'Chiang Mai Hub');

        $this->assertNotNull($result);
        
        // Check that facility was created
        $facility = Facility::where('code', 'CNX001')->first();
        $this->assertNotNull($facility);
        $this->assertEquals('Chiang Mai Hub', $facility->name);
        $this->assertEquals(18.7883, $facility->latitude);
        $this->assertEquals(98.9853, $facility->longitude);
    }

    /** @test */
    public function cleans_location_names_for_geocoding()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '13.7563',
                    'lon' => '100.5018',
                    'display_name' => 'Bangkok, Thailand'
                ]
            ])
        ]);

        // Test with facility suffix that should be cleaned
        $result = $this->service->geocodeLocation('Bangkok Hub Facility');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'q=Bangkok%2C%20Thailand') ||
                   str_contains($request->url(), 'q=Bangkok');
        });

        $this->assertNotNull($result);
    }

    /** @test */
    public function tries_multiple_geocoding_strategies()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::sequence()
                ->push([], 200) // First strategy fails
                ->push([], 200) // Second strategy fails
                ->push([        // Third strategy succeeds
                    [
                        'lat' => '13.7563',
                        'lon' => '100.5018',
                        'display_name' => 'Bangkok, Thailand'
                    ]
                ], 200)
        ]);

        $result = $this->service->geocodeLocation('Bangkok Distribution Center');

        $this->assertNotNull($result);
        $this->assertEquals(13.7563, $result['latitude']);
        $this->assertEquals(100.5018, $result['longitude']);
    }

    /** @test */
    public function handles_geocoding_api_failure()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([], 500)
        ]);

        $result = $this->service->geocodeLocation('Unknown Location');

        $this->assertNull($result);
    }

    /** @test */
    public function caches_geocoding_results()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '13.7563',
                    'lon' => '100.5018',
                    'display_name' => 'Bangkok, Thailand'
                ]
            ])
        ]);

        // First call
        $result1 = $this->service->geocodeLocation('Bangkok');
        
        // Second call should use cache
        $result2 = $this->service->geocodeLocation('Bangkok');

        $this->assertEquals($result1, $result2);
        
        // Should only make one HTTP request due to caching
        Http::assertSentCount(1);
    }

    /** @test */
    public function reverse_geocodes_coordinates()
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Bangkok, Thailand'
            ])
        ]);

        $result = $this->service->reverseGeocode(13.7563, 100.5018);

        $this->assertEquals('Bangkok, Thailand', $result);
    }

    /** @test */
    public function calculates_distance_between_coordinates()
    {
        // Distance between Bangkok and Chiang Mai (approximately 585 km)
        $bangkokLat = 13.7563;
        $bangkokLon = 100.5018;
        $chiangMaiLat = 18.7883;
        $chiangMaiLon = 98.9853;

        $distance = $this->service->calculateDistance(
            $bangkokLat, $bangkokLon,
            $chiangMaiLat, $chiangMaiLon
        );

        // Should be approximately 585 km (allow 50km tolerance)
        $this->assertGreaterThan(535, $distance);
        $this->assertLessThan(635, $distance);
    }

    /** @test */
    public function extracts_city_names_from_facility_names()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '18.7883',
                    'lon' => '98.9853',
                    'display_name' => 'Chiang Mai, Thailand'
                ]
            ])
        ]);

        $result = $this->service->geocodeLocation('Chiang Mai Distribution Center');

        // Should try "Chiang Mai, Thailand" as one of the strategies
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Chiang%20Mai');
        });

        $this->assertNotNull($result);
    }

    /** @test */
    public function handles_empty_location_names()
    {
        $result = $this->service->geocodeLocation('');

        $this->assertNull($result);
    }

    /** @test */
    public function handles_whitespace_only_location_names()
    {
        $result = $this->service->geocodeLocation('   ');

        $this->assertNull($result);
    }

    /** @test */
    public function normalizes_common_city_abbreviations()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '13.7563',
                    'lon' => '100.5018',
                    'display_name' => 'Bangkok, Thailand'
                ]
            ])
        ]);

        $result = $this->service->geocodeLocation('Bkk Hub');

        // Should expand Bkk to Bangkok
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Bangkok');
        });

        $this->assertNotNull($result);
    }

    /** @test */
    public function respects_api_timeout()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => function () {
                sleep(15); // Simulate timeout
                return Http::response([]);
            }
        ]);

        $result = $this->service->geocodeLocation('Bangkok');

        $this->assertNull($result);
    }

    /** @test */
    public function includes_user_agent_in_requests()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '13.7563',
                    'lon' => '100.5018',
                    'display_name' => 'Bangkok, Thailand'
                ]
            ])
        ]);

        $this->service->geocodeLocation('Bangkok');

        Http::assertSent(function ($request) {
            return $request->hasHeader('User-Agent', 'ParcelTrackingSystem/1.0');
        });
    }

    /** @test */
    public function limits_search_to_thailand()
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([])
        ]);

        $this->service->geocodeLocation('Bangkok');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'countrycodes=th');
        });
    }
}