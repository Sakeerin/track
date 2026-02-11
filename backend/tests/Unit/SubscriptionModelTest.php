<?php

namespace Tests\Unit;

use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_can_be_created_with_required_fields()
    {
        $shipment = Shipment::factory()->create();
        
        $subscription = Subscription::create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'events' => ['PICKUP', 'DELIVERED'],
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination_hash' => hash('sha256', 'test@example.com'),
        ]);

        $this->assertEquals('test@example.com', $subscription->fresh()->destination);
    }

    public function test_subscription_casts_events_to_array()
    {
        $events = ['PICKUP', 'IN_TRANSIT', 'DELIVERED'];
        
        $subscription = Subscription::factory()->create([
            'events' => $events,
        ]);

        $this->assertIsArray($subscription->events);
        $this->assertEquals($events, $subscription->events);
    }

    public function test_subscription_casts_boolean_fields()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'consent_given' => false,
        ]);

        $this->assertIsBool($subscription->active);
        $this->assertIsBool($subscription->consent_given);
        $this->assertTrue($subscription->active);
        $this->assertFalse($subscription->consent_given);
    }

    public function test_subscription_casts_consent_at_to_datetime()
    {
        $subscription = Subscription::factory()->create([
            'consent_at' => '2024-12-25 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $subscription->consent_at);
        $this->assertEquals('2024-12-25 10:00:00', $subscription->consent_at->format('Y-m-d H:i:s'));
    }

    public function test_subscription_has_shipment_relationship()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $this->assertEquals($shipment->id, $subscription->shipment->id);
    }

    public function test_active_scope_filters_active_subscriptions()
    {
        $activeSubscription = Subscription::factory()->create(['active' => true]);
        $inactiveSubscription = Subscription::factory()->create(['active' => false]);

        $activeSubscriptions = Subscription::active()->get();

        $this->assertTrue($activeSubscriptions->contains($activeSubscription));
        $this->assertFalse($activeSubscriptions->contains($inactiveSubscription));
    }

    public function test_with_consent_scope_filters_subscriptions_with_consent()
    {
        $consentedSubscription = Subscription::factory()->create(['consent_given' => true]);
        $nonConsentedSubscription = Subscription::factory()->create(['consent_given' => false]);

        $consentedSubscriptions = Subscription::withConsent()->get();

        $this->assertTrue($consentedSubscriptions->contains($consentedSubscription));
        $this->assertFalse($consentedSubscriptions->contains($nonConsentedSubscription));
    }

    public function test_for_channel_scope_filters_by_channel()
    {
        $emailSubscription = Subscription::factory()->create(['channel' => 'email']);
        $smsSubscription = Subscription::factory()->create(['channel' => 'sms']);

        $emailSubscriptions = Subscription::forChannel('email')->get();

        $this->assertTrue($emailSubscriptions->contains($emailSubscription));
        $this->assertFalse($emailSubscriptions->contains($smsSubscription));
    }

    public function test_should_notify_for_event_returns_true_when_conditions_met()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'consent_given' => true,
            'events' => ['PICKUP', 'DELIVERED'],
        ]);

        $this->assertTrue($subscription->shouldNotifyForEvent('PICKUP'));
        $this->assertTrue($subscription->shouldNotifyForEvent('DELIVERED'));
    }

    public function test_should_notify_for_event_returns_false_when_inactive()
    {
        $subscription = Subscription::factory()->create([
            'active' => false,
            'consent_given' => true,
            'events' => ['PICKUP', 'DELIVERED'],
        ]);

        $this->assertFalse($subscription->shouldNotifyForEvent('PICKUP'));
    }

    public function test_should_notify_for_event_returns_false_when_no_consent()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'consent_given' => false,
            'events' => ['PICKUP', 'DELIVERED'],
        ]);

        $this->assertFalse($subscription->shouldNotifyForEvent('PICKUP'));
    }

    public function test_should_notify_for_event_returns_false_when_event_not_subscribed()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'consent_given' => true,
            'events' => ['PICKUP', 'DELIVERED'],
        ]);

        $this->assertFalse($subscription->shouldNotifyForEvent('IN_TRANSIT'));
    }

    public function test_generate_unsubscribe_token_creates_and_saves_token()
    {
        $subscription = Subscription::factory()->create(['unsubscribe_token' => null]);

        $token = $subscription->generateUnsubscribeToken();

        $this->assertNotNull($token);
        $this->assertEquals(100, strlen($token));
        $this->assertEquals($token, $subscription->fresh()->unsubscribe_token);
    }

    public function test_unsubscribe_by_token_deactivates_subscription()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'unsubscribe_token' => 'test-token-123',
        ]);

        $result = Subscription::unsubscribeByToken('test-token-123');

        $this->assertTrue($result);
        $this->assertFalse($subscription->fresh()->active);
    }

    public function test_unsubscribe_by_token_returns_false_for_invalid_token()
    {
        $result = Subscription::unsubscribeByToken('invalid-token');

        $this->assertFalse($result);
    }

    public function test_subscription_generates_unsubscribe_token_on_creation()
    {
        $subscription = Subscription::factory()->create();

        $this->assertNotNull($subscription->unsubscribe_token);
        // Factory uses sha256 which generates 64 characters, but model boot method should generate 100
        // Let's test the model boot method by creating without factory
        $shipment = Shipment::factory()->create();
        $newSubscription = Subscription::create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'events' => ['PICKUP', 'DELIVERED'],
        ]);
        
        $this->assertEquals(100, strlen($newSubscription->unsubscribe_token));
    }

    public function test_subscription_preserves_existing_unsubscribe_token_on_creation()
    {
        $existingToken = 'existing-token-123';
        
        $subscription = Subscription::factory()->create([
            'unsubscribe_token' => $existingToken,
        ]);

        $this->assertEquals($existingToken, $subscription->unsubscribe_token);
    }

    public function test_unsubscribe_token_must_be_unique()
    {
        $token = 'unique-token-123';
        
        Subscription::factory()->create(['unsubscribe_token' => $token]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Subscription::factory()->create(['unsubscribe_token' => $token]);
    }
}
