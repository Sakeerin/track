<?php

namespace Tests\Unit;

use App\Models\Facility;
use App\Services\Ingestion\EventNormalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Carbon\Carbon;

class EventNormalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventNormalizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventNormalizationService();
        
        // Create test facility
        Facility::factory()->create([
            'code' => 'BKK001',
            'name' => 'Bangkok Hub',
            'facility_type' => 'HUB',
            'active' => true
        ]);
    }

    /** @test */
    public function normalizes_webhook_event_codes()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'th1234567890',
            'event_code' => 'PU', // Partner code
            'event_time' => now(),
            'facility_code' => 'bkk001',
            'location' => 'bangkok hub',
            'description' => 'Package picked up',
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals('PICKED_UP', $normalized['event_code']);
        $this->assertEquals('TH1234567890', $normalized['tracking_number']);
        $this->assertEquals('BKK001', $normalized['facility_code']);
        $this->assertEquals('Bangkok Hub', $normalized['location']);
    }

    /** @test */
    public function normalizes_batch_event_codes()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PICKUP', // Batch format code
            'event_time' => now(),
            'source' => 'batch'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals('PICKED_UP', $normalized['event_code']);
    }

    /** @test */
    public function resolves_facility_by_code()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PU',
            'event_time' => now(),
            'facility_code' => 'BKK001',
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertNotNull($normalized['facility_id']);
        $this->assertEquals('Bangkok Hub', $normalized['location']);
    }

    /** @test */
    public function handles_unknown_facility_codes()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PU',
            'event_time' => now(),
            'facility_code' => 'UNKNOWN001',
            'location' => 'Unknown Location',
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertNull($normalized['facility_id']);
        $this->assertEquals('Unknown Location', $normalized['location']);
    }

    /** @test */
    public function normalizes_location_text()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PU',
            'event_time' => now(),
            'location' => '  bangkok   hub  ', // Multiple spaces
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals('Bangkok Hub', $normalized['location']);
    }

    /** @test */
    public function generates_default_description_for_empty_description()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PU',
            'event_time' => now(),
            'description' => '',
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals('Package picked up from sender', $normalized['description']);
    }

    /** @test */
    public function preserves_existing_description()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PU',
            'event_time' => now(),
            'description' => 'Custom pickup description',
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals('Custom pickup description', $normalized['description']);
    }

    /** @test */
    public function validates_normalized_event_data()
    {
        $validEventData = [
            'event_code' => 'PICKED_UP',
            'tracking_number' => 'TH1234567890',
            'event_time' => now(),
        ];

        $result = $this->service->validateNormalizedEvent($validEventData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function validates_canonical_event_codes()
    {
        $invalidEventData = [
            'event_code' => 'INVALID_CODE',
            'tracking_number' => 'TH1234567890',
            'event_time' => now(),
        ];

        $result = $this->service->validateNormalizedEvent($invalidEventData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid canonical event code: INVALID_CODE', $result['errors']);
    }

    /** @test */
    public function validates_required_fields()
    {
        $incompleteEventData = [
            'event_code' => 'PICKED_UP',
            // Missing tracking_number and event_time
        ];

        $result = $this->service->validateNormalizedEvent($incompleteEventData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Missing required field after normalization: tracking_number', $result['errors']);
        $this->assertContains('Missing required field after normalization: event_time', $result['errors']);
    }

    /** @test */
    public function caches_event_code_mappings()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('event_code_mapping:webhook', 3600, \Closure::class)
            ->andReturn(['PU' => 'PICKED_UP']);

        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PU',
            'event_time' => now(),
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals('PICKED_UP', $normalized['event_code']);
    }

    /** @test */
    public function handles_partner_specific_mappings()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'COLLECTED', // Partner API specific code
            'event_time' => now(),
            'source' => 'partner_api'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals('PICKED_UP', $normalized['event_code']);
    }

    /** @test */
    public function preserves_raw_payload()
    {
        $eventData = [
            'event_id' => 'EVT001',
            'tracking_number' => 'TH1234567890',
            'event_code' => 'PU',
            'event_time' => now(),
            'custom_field' => 'custom_value',
            'source' => 'webhook'
        ];

        $normalized = $this->service->normalizeEvent($eventData);

        $this->assertEquals($eventData, $normalized['raw_payload']);
    }

    /** @test */
    public function normalizes_common_location_abbreviations()
    {
        $testCases = [
            'Bkk Hub' => 'Bangkok Hub',
            'CNX Facility' => 'Chiang Mai Facility',
            'HKT Terminal' => 'Phuket Terminal',
        ];

        foreach ($testCases as $input => $expected) {
            $eventData = [
                'event_id' => 'EVT001',
                'tracking_number' => 'TH1234567890',
                'event_code' => 'PU',
                'event_time' => now(),
                'location' => $input,
                'source' => 'webhook'
            ];

            $normalized = $this->service->normalizeEvent($eventData);

            $this->assertEquals($expected, $normalized['location'], "Failed to normalize: {$input}");
        }
    }
}