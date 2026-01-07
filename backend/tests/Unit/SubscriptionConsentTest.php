<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_requires_consent_to_notify()
    {
        $shipment = Shipment::factory()->create();
        
        // Create subscription without consent
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => false,
            'events' => ['Delivered'],
        ]);

        $this->assertFalse($subscription->shouldNotifyForEvent('Delivered'));
    }

    public function test_subscription_with_consent_can_notify()
    {
        $shipment = Shipment::factory()->create();
        
        // Create subscription with consent
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => true,
            'consent_at' => now(),
            'consent_ip' => '192.168.1.1',
            'events' => ['Delivered'],
        ]);

        $this->assertTrue($subscription->shouldNotifyForEvent('Delivered'));
    }

    public function test_inactive_subscription_cannot_notify_even_with_consent()
    {
        $shipment = Shipment::factory()->create();
        
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => false,
            'consent_given' => true,
            'events' => ['Delivered'],
        ]);

        $this->assertFalse($subscription->shouldNotifyForEvent('Delivered'));
    }

    public function test_subscription_only_notifies_for_subscribed_events()
    {
        $shipment = Shipment::factory()->create();
        
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => true,
            'events' => ['PickedUp', 'Delivered'], // Only these events
        ]);

        $this->assertTrue($subscription->shouldNotifyForEvent('PickedUp'));
        $this->assertTrue($subscription->shouldNotifyForEvent('Delivered'));
        $this->assertFalse($subscription->shouldNotifyForEvent('InTransit'));
        $this->assertFalse($subscription->shouldNotifyForEvent('OutForDelivery'));
    }

    public function test_subscription_generates_unsubscribe_token_on_creation()
    {
        $shipment = Shipment::factory()->create();
        
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $this->assertNotNull($subscription->unsubscribe_token);
        $this->assertGreaterThan(50, strlen($subscription->unsubscribe_token));
    }

    public function test_can_manually_generate_unsubscribe_token()
    {
        $subscription = Subscription::factory()->create([
            'unsubscribe_token' => null,
        ]);

        $token = $subscription->generateUnsubscribeToken();

        $this->assertNotNull($token);
        $this->assertGreaterThan(50, strlen($token));
        $this->assertEquals($token, $subscription->fresh()->unsubscribe_token);
    }

    public function test_unsubscribe_by_token_deactivates_subscription()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'unsubscribe_token' => 'valid_token_123',
        ]);

        $result = Subscription::unsubscribeByToken('valid_token_123');

        $this->assertTrue($result);
        $this->assertFalse($subscription->fresh()->active);
    }

    public function test_unsubscribe_by_invalid_token_returns_false()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'unsubscribe_token' => 'valid_token_123',
        ]);

        $result = Subscription::unsubscribeByToken('invalid_token');

        $this->assertFalse($result);
        $this->assertTrue($subscription->fresh()->active);
    }

    public function test_unsubscribe_by_token_handles_nonexistent_token()
    {
        $result = Subscription::unsubscribeByToken('nonexistent_token');

        $this->assertFalse($result);
    }

    public function test_scope_active_filters_active_subscriptions()
    {
        $shipment = Shipment::factory()->create();
        
        $activeSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
        ]);
        
        $inactiveSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => false,
        ]);

        $activeSubscriptions = Subscription::active()->get();

        $this->assertTrue($activeSubscriptions->contains($activeSubscription));
        $this->assertFalse($activeSubscriptions->contains($inactiveSubscription));
    }

    public function test_scope_with_consent_filters_consented_subscriptions()
    {
        $shipment = Shipment::factory()->create();
        
        $consentedSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'consent_given' => true,
        ]);
        
        $nonConsentedSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'consent_given' => false,
        ]);

        $consentedSubscriptions = Subscription::withConsent()->get();

        $this->assertTrue($consentedSubscriptions->contains($consentedSubscription));
        $this->assertFalse($consentedSubscriptions->contains($nonConsentedSubscription));
    }

    public function test_scope_for_channel_filters_by_channel()
    {
        $shipment = Shipment::factory()->create();
        
        $emailSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
        ]);
        
        $smsSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'sms',
        ]);

        $emailSubscriptions = Subscription::forChannel('email')->get();

        $this->assertTrue($emailSubscriptions->contains($emailSubscription));
        $this->assertFalse($emailSubscriptions->contains($smsSubscription));
    }

    public function test_combined_scopes_for_notification_eligibility()
    {
        $shipment = Shipment::factory()->create();
        
        // Eligible subscription
        $eligibleSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => true,
            'channel' => 'email',
        ]);
        
        // Ineligible subscriptions
        $inactiveSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => false,
            'consent_given' => true,
            'channel' => 'email',
        ]);
        
        $nonConsentedSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
            'consent_given' => false,
            'channel' => 'email',
        ]);

        $eligibleSubscriptions = Subscription::active()
            ->withConsent()
            ->forChannel('email')
            ->get();

        $this->assertTrue($eligibleSubscriptions->contains($eligibleSubscription));
        $this->assertFalse($eligibleSubscriptions->contains($inactiveSubscription));
        $this->assertFalse($eligibleSubscriptions->contains($nonConsentedSubscription));
    }

    public function test_consent_tracking_records_ip_and_timestamp()
    {
        $subscription = Subscription::factory()->create([
            'consent_given' => true,
            'consent_at' => now()->subHour(),
            'consent_ip' => '203.154.1.100',
        ]);

        $this->assertTrue($subscription->consent_given);
        $this->assertNotNull($subscription->consent_at);
        $this->assertEquals('203.154.1.100', $subscription->consent_ip);
        $this->assertInstanceOf(\Carbon\Carbon::class, $subscription->consent_at);
    }

    public function test_subscription_can_be_reactivated_after_unsubscribe()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'consent_given' => true,
            'events' => ['Delivered'],
        ]);

        // Unsubscribe
        $subscription->update(['active' => false]);
        $this->assertFalse($subscription->shouldNotifyForEvent('Delivered'));

        // Reactivate
        $subscription->update(['active' => true]);
        $this->assertTrue($subscription->shouldNotifyForEvent('Delivered'));
    }

    public function test_subscription_events_array_casting()
    {
        $events = ['Created', 'PickedUp', 'Delivered'];
        
        $subscription = Subscription::factory()->create([
            'events' => $events,
        ]);

        $this->assertIsArray($subscription->events);
        $this->assertEquals($events, $subscription->events);
        
        // Test database storage and retrieval
        $retrieved = Subscription::find($subscription->id);
        $this->assertIsArray($retrieved->events);
        $this->assertEquals($events, $retrieved->events);
    }

    public function test_subscription_handles_null_events_array()
    {
        $subscription = Subscription::factory()->create([
            'events' => [],
        ]);
        
        // Manually set events to null to test the behavior
        $subscription->events = null;

        $this->assertFalse($subscription->shouldNotifyForEvent('Delivered'));
    }

    public function test_subscription_handles_empty_events_array()
    {
        $subscription = Subscription::factory()->create([
            'events' => [],
        ]);

        $this->assertFalse($subscription->shouldNotifyForEvent('Delivered'));
    }

    public function test_multiple_subscriptions_for_same_shipment_different_channels()
    {
        $shipment = Shipment::factory()->create();
        
        $emailSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'active' => true,
            'consent_given' => true,
            'events' => ['Delivered'],
        ]);
        
        $smsSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'sms',
            'destination' => '+66812345678',
            'active' => true,
            'consent_given' => true,
            'events' => ['Delivered'],
        ]);

        $shipmentSubscriptions = $shipment->subscriptions()->active()->withConsent()->get();

        $this->assertCount(2, $shipmentSubscriptions);
        $this->assertTrue($shipmentSubscriptions->contains($emailSubscription));
        $this->assertTrue($shipmentSubscriptions->contains($smsSubscription));
    }
}