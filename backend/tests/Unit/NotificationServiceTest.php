<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\LineNotificationChannel;
use App\Services\Notification\NotificationService;
use App\Services\Notification\SmsNotificationChannel;
use App\Services\Notification\TemplateManager;
use App\Services\Notification\WebhookNotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = new NotificationService(
            new EmailNotificationChannel(),
            new SmsNotificationChannel(),
            new LineNotificationChannel(),
            new WebhookNotificationChannel(),
            new TemplateManager()
        );
    }

    public function test_notify_for_event_sends_to_subscribed_channels()
    {
        Mail::fake();

        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'Delivered',
        ]);

        // Create subscription for Delivered event
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'events' => ['Delivered', 'InTransit'],
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
            'facility_id' => $facility->id,
        ]);

        $results = $this->notificationService->notifyForEvent($event);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('email', $results[0]['channel']);
    }

    public function test_does_not_notify_inactive_subscriptions()
    {
        Mail::fake();

        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create();

        // Create inactive subscription
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'events' => ['Delivered'],
            'active' => false,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
            'facility_id' => $facility->id,
        ]);

        $results = $this->notificationService->notifyForEvent($event);

        $this->assertCount(0, $results);
    }

    public function test_does_not_notify_without_consent()
    {
        Mail::fake();

        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create();

        // Create subscription without consent
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => false,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
            'facility_id' => $facility->id,
        ]);

        $results = $this->notificationService->notifyForEvent($event);

        $this->assertCount(0, $results);
    }

    public function test_does_not_notify_for_unsubscribed_events()
    {
        Mail::fake();

        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create();

        // Create subscription for different events
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'events' => ['InTransit', 'OutForDelivery'],
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
            'facility_id' => $facility->id,
        ]);

        $results = $this->notificationService->notifyForEvent($event);

        $this->assertCount(0, $results);
    }

    public function test_send_notification_prepares_correct_data()
    {
        Mail::fake();

        $facility = Facility::factory()->create(['name' => 'Bangkok Hub']);
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH9876543210',
            'current_status' => 'InTransit',
            'service_type' => 'Express',
        ]);

        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'customer@example.com',
            'events' => ['InTransit'],
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
            'description' => 'Package in transit',
            'facility_id' => $facility->id,
        ]);

        $result = $this->notificationService->sendNotification($subscription, $event);

        $this->assertTrue($result['success']);
        $this->assertEquals('email', $result['channel']);
        $this->assertEquals($subscription->id, $result['subscription_id']);
    }

    public function test_handles_invalid_channel_gracefully()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create();

        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'invalid_channel',
            'destination' => 'test@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
            'facility_id' => $facility->id,
        ]);

        $result = $this->notificationService->sendNotification($subscription, $event);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
}
