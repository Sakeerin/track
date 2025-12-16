<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionThrottlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_throttle_returns_false_for_critical_events()
    {
        $subscription = Subscription::factory()->create();
        
        $criticalEvents = ['DeliveryAttempted', 'Delivered', 'ExceptionRaised', 'Returned'];
        
        foreach ($criticalEvents as $eventCode) {
            $this->assertFalse(
                $subscription->shouldThrottle($eventCode),
                "Critical event {$eventCode} should not be throttled"
            );
        }
    }

    public function test_should_throttle_returns_false_when_no_previous_notifications()
    {
        $subscription = Subscription::factory()->create();
        
        $this->assertFalse($subscription->shouldThrottle('InTransit'));
    }

    public function test_should_throttle_returns_true_when_notification_sent_within_two_hours()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        // Create a notification log from 1 hour ago
        NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);
        
        $this->assertTrue($subscription->shouldThrottle('InTransit'));
    }

    public function test_should_throttle_returns_false_when_notification_sent_over_two_hours_ago()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        // Create a notification log from 3 hours ago
        NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
            'sent_at' => now()->subHours(3),
        ]);
        
        $this->assertFalse($subscription->shouldThrottle('InTransit'));
    }

    public function test_should_throttle_ignores_failed_notifications()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        // Create a failed notification log from 1 hour ago
        NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'failed',
            'sent_at' => now()->subHour(),
        ]);
        
        $this->assertFalse($subscription->shouldThrottle('InTransit'));
    }

    public function test_should_throttle_ignores_throttled_notifications()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        // Create a throttled notification log from 1 hour ago
        NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'throttled',
            'sent_at' => now()->subHour(),
        ]);
        
        $this->assertFalse($subscription->shouldThrottle('InTransit'));
    }

    public function test_get_last_notification_time_returns_null_when_no_notifications()
    {
        $subscription = Subscription::factory()->create();
        
        $this->assertNull($subscription->getLastNotificationTime());
    }

    public function test_get_last_notification_time_returns_most_recent_sent_notification()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        $oldNotification = NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
            'sent_at' => now()->subHours(5),
        ]);
        
        $recentNotification = NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);
        
        $lastTime = $subscription->getLastNotificationTime();
        $this->assertEquals($recentNotification->sent_at->toDateTimeString(), $lastTime->toDateTimeString());
    }

    public function test_get_statistics_returns_correct_counts()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        // Create various notification logs
        NotificationLog::factory()->sent()->count(5)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        
        NotificationLog::factory()->failed()->count(2)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        
        NotificationLog::factory()->throttled()->count(3)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        
        $stats = $subscription->getStatistics();
        
        $this->assertEquals(5, $stats['total_sent']);
        $this->assertEquals(2, $stats['total_failed']);
        $this->assertEquals(3, $stats['total_throttled']);
        $this->assertNotNull($stats['last_sent_at']);
    }

    public function test_get_statistics_calculates_delivery_rate()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        // Create 10 sent notifications, 8 delivered
        NotificationLog::factory()->sent()->delivered()->count(8)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        
        NotificationLog::factory()->sent()->count(2)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'delivered_at' => null,
        ]);
        
        $stats = $subscription->getStatistics();
        
        $this->assertEquals(80, $stats['delivery_rate']);
    }

    public function test_notification_logs_relationship()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        $log = NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        
        $this->assertTrue($subscription->notificationLogs->contains($log));
    }
}
