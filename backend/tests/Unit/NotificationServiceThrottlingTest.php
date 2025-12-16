<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\LineNotificationChannel;
use App\Services\Notification\NotificationService;
use App\Services\Notification\SmsNotificationChannel;
use App\Services\Notification\TemplateManager;
use App\Services\Notification\WebhookNotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceThrottlingTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->notificationService = new NotificationService(
            $this->app->make(EmailNotificationChannel::class),
            $this->app->make(SmsNotificationChannel::class),
            $this->app->make(LineNotificationChannel::class),
            $this->app->make(WebhookNotificationChannel::class),
            $this->app->make(TemplateManager::class)
        );
    }

    public function test_notification_is_throttled_when_sent_within_two_hours()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => true,
            'events' => ['InTransit'],
        ]);
        
        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
        ]);
        
        // Create a recent notification
        NotificationLog::factory()->sent()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'sent_at' => now()->subHour(),
        ]);
        
        $result = $this->notificationService->sendNotification($subscription, $event);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('throttled', $result['reason']);
        
        // Verify throttled log was created
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $subscription->id,
            'status' => 'throttled',
        ]);
    }

    public function test_critical_events_bypass_throttling()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => true,
            'events' => ['Delivered', 'InTransit'],
        ]);
        
        $transitEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
        ]);
        
        // Create a recent notification for non-critical event
        NotificationLog::factory()->sent()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $transitEvent->id,
            'sent_at' => now()->subMinutes(30),
        ]);
        
        // Try to send critical event
        $deliveredEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
        ]);
        
        $result = $this->notificationService->sendNotification($subscription, $deliveredEvent);
        
        // Critical event should not be throttled
        $this->assertTrue($result['success']);
    }

    public function test_notification_log_is_created_on_successful_send()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'active' => true,
            'consent_given' => true,
            'events' => ['PickedUp'],
        ]);
        
        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PickedUp',
        ]);
        
        $result = $this->notificationService->sendNotification($subscription, $event);
        
        $this->assertTrue($result['success']);
        
        // Verify log was created
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    public function test_notification_log_captures_error_on_failure()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'invalid_channel',
            'active' => true,
            'consent_given' => true,
            'events' => ['PickedUp'],
        ]);
        
        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PickedUp',
        ]);
        
        $result = $this->notificationService->sendNotification($subscription, $event);
        
        $this->assertFalse($result['success']);
        
        // Verify error log was created
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'failed',
        ]);
        
        $log = NotificationLog::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($log->error_message);
    }

    public function test_throttling_resets_after_two_hours()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => true,
            'events' => ['InTransit'],
        ]);
        
        $oldEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
        ]);
        
        // Create an old notification (more than 2 hours ago)
        NotificationLog::factory()->sent()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $oldEvent->id,
            'sent_at' => now()->subHours(3),
        ]);
        
        $newEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
        ]);
        
        $result = $this->notificationService->sendNotification($subscription, $newEvent);
        
        // Should not be throttled
        $this->assertTrue($result['success']);
    }
}
