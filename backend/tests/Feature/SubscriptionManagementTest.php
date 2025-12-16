<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_subscription_analytics()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);
        
        // Create notification logs
        NotificationLog::factory()->sent()->count(3)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        
        NotificationLog::factory()->failed()->count(1)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        
        $response = $this->getJson("/api/subscriptions/{$subscription->id}/analytics");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'subscription',
                'statistics' => [
                    'total_sent',
                    'total_failed',
                    'total_throttled',
                    'last_sent_at',
                    'delivery_rate',
                ],
                'recent_notifications',
            ]);
        
        $this->assertEquals(3, $response->json('statistics.total_sent'));
        $this->assertEquals(1, $response->json('statistics.total_failed'));
    }

    public function test_analytics_returns_404_for_nonexistent_subscription()
    {
        $response = $this->getJson('/api/subscriptions/nonexistent-id/analytics');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Subscription not found',
            ]);
    }

    public function test_can_update_notification_preferences()
    {
        $subscription = Subscription::factory()->create([
            'events' => ['PickedUp', 'InTransit'],
        ]);
        
        $newEvents = ['Delivered', 'ExceptionRaised'];
        
        $response = $this->putJson("/api/subscriptions/{$subscription->id}/preferences", [
            'events' => $newEvents,
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification preferences updated successfully',
            ]);
        
        $this->assertEquals($newEvents, $subscription->fresh()->events);
    }

    public function test_update_preferences_validates_event_codes()
    {
        $subscription = Subscription::factory()->create();
        
        $response = $this->putJson("/api/subscriptions/{$subscription->id}/preferences", [
            'events' => ['InvalidEvent'],
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['events.0']);
    }

    public function test_update_preferences_requires_at_least_one_event()
    {
        $subscription = Subscription::factory()->create();
        
        $response = $this->putJson("/api/subscriptions/{$subscription->id}/preferences", [
            'events' => [],
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['events']);
    }

    public function test_can_get_delivery_tracking_for_shipment()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create(['shipment_id' => $shipment->id]);
        $event = Event::factory()->create(['shipment_id' => $shipment->id]);
        
        // Create notification logs
        NotificationLog::factory()->sent()->count(5)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'email',
        ]);
        
        NotificationLog::factory()->failed()->count(2)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'sms',
        ]);
        
        $response = $this->getJson('/api/subscriptions/delivery-tracking', [
            'tracking_number' => $shipment->tracking_number,
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tracking_number' => $shipment->tracking_number,
            ])
            ->assertJsonStructure([
                'success',
                'tracking_number',
                'statistics' => [
                    'total_sent',
                    'total_failed',
                    'total_throttled',
                    'delivery_rate',
                    'by_channel',
                ],
                'logs',
            ]);
        
        $this->assertEquals(5, $response->json('statistics.total_sent'));
        $this->assertEquals(2, $response->json('statistics.total_failed'));
    }

    public function test_delivery_tracking_filters_by_date_range()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create(['shipment_id' => $shipment->id]);
        $event = Event::factory()->create(['shipment_id' => $shipment->id]);
        
        // Create old notification
        NotificationLog::factory()->sent()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'sent_at' => now()->subDays(10),
        ]);
        
        // Create recent notification
        NotificationLog::factory()->sent()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'sent_at' => now()->subDay(),
        ]);
        
        $response = $this->getJson('/api/subscriptions/delivery-tracking', [
            'tracking_number' => $shipment->tracking_number,
            'start_date' => now()->subDays(5)->toDateString(),
        ]);
        
        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('logs')));
    }

    public function test_can_mark_notification_as_delivered()
    {
        $log = NotificationLog::factory()->sent()->create([
            'delivered_at' => null,
        ]);
        
        $deliveryTime = now()->toDateTimeString();
        
        $response = $this->postJson("/api/notifications/{$log->id}/delivered", [
            'delivered_at' => $deliveryTime,
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as delivered',
            ]);
        
        $this->assertNotNull($log->fresh()->delivered_at);
    }

    public function test_mark_delivered_uses_current_time_if_not_provided()
    {
        $log = NotificationLog::factory()->sent()->create([
            'delivered_at' => null,
        ]);
        
        $response = $this->postJson("/api/notifications/{$log->id}/delivered");
        
        $response->assertStatus(200);
        $this->assertNotNull($log->fresh()->delivered_at);
    }

    public function test_mark_delivered_returns_404_for_nonexistent_log()
    {
        $response = $this->postJson('/api/notifications/nonexistent-id/delivered');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Notification log not found',
            ]);
    }

    public function test_delivery_tracking_groups_statistics_by_channel()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create(['shipment_id' => $shipment->id]);
        $event = Event::factory()->create(['shipment_id' => $shipment->id]);
        
        // Create notifications for different channels
        NotificationLog::factory()->sent()->count(3)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'email',
        ]);
        
        NotificationLog::factory()->sent()->count(2)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'sms',
        ]);
        
        NotificationLog::factory()->failed()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'email',
        ]);
        
        $response = $this->getJson('/api/subscriptions/delivery-tracking', [
            'tracking_number' => $shipment->tracking_number,
        ]);
        
        $response->assertStatus(200);
        
        $byChannel = $response->json('statistics.by_channel');
        $this->assertArrayHasKey('email', $byChannel);
        $this->assertArrayHasKey('sms', $byChannel);
        $this->assertEquals(4, $byChannel['email']['total']);
        $this->assertEquals(2, $byChannel['sms']['total']);
    }
}
