<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTrackingSeoTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function shared_tracking_url_renders_ssr_page_with_meta_and_structured_data()
    {
        $facility = Facility::factory()->create([
            'code' => 'BKK001',
            'name' => 'Bangkok Hub',
            'active' => true,
        ]);

        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'in_transit',
            'service_type' => 'express',
            'origin_facility_id' => $facility->id,
            'destination_facility_id' => $facility->id,
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'IN_TRANSIT',
            'description' => 'Package is in transit',
            'event_time' => now()->subHour(),
            'facility_id' => $facility->id,
        ]);

        $response = $this->get('/track/TH1234567890');

        $response->assertOk();
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('Copy Share Link');
        $response->assertSee('Track Parcel TH1234567890');
    }

    /** @test */
    public function faq_route_supports_search_query()
    {
        $response = $this->get('/faq?q=Out%20for%20Delivery');

        $response->assertOk();
        $response->assertSee('Out for Delivery');
        $response->assertDontSee('How accurate is ETA?');
    }

    /** @test */
    public function contact_form_auto_attaches_tracking_number_and_creates_ticket()
    {
        $this->get('/contact?tracking_number=TH1234567890')
            ->assertOk()
            ->assertSee('TH1234567890');

        $response = $this->post('/contact', [
            'tracking_number' => 'TH1234567890',
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'subject' => 'Delivery question',
            'message' => 'Please help me confirm the latest delivery scan.',
        ]);

        $response->assertRedirect('/contact?tracking_number=TH1234567890');

        $this->assertDatabaseHas('support_tickets', [
            'tracking_number' => 'TH1234567890',
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'subject' => 'Delivery question',
            'source' => 'public_web',
            'status' => 'open',
        ]);
    }

    /** @test */
    public function sitemap_contains_public_and_tracking_urls()
    {
        Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('/faq', false);
        $response->assertSee('/contact', false);
        $response->assertSee('/track/TH1234567890', false);
    }
}
