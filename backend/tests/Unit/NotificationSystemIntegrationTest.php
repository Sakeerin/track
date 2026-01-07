<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Facility;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationSystemIntegrationTest extends TestCase
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

    /**
     * Test notification delivery across all channels
     * Requirements: 4.1, 8.5, 8.6, 8.7
     */
    public function test_notification_delivery_across_all_channels()
    {
        Mail::fake();
        Http::fake([
            '*' => Http::response(['message_id' => 'test_123', 'status' => 'sent'], 200),
        ]);

        $facility = Facility::factory()->create(['name' => 'Bangkok Hub']);
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'Delivered',
            'service_type' => 'Express',
        ]);

        // Create subscriptions for all channels
        $emailSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'test@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $smsSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'sms',
            'destination' => '+66812345678',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $lineSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'line',
            'destination' => 'U1234567890abcdef',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $webhookSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'webhook',
            'destination' => 'https://example.com/webhook',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
            'description' => 'Package delivered successfully',
            'facility_id' => $facility->id,
        ]);

        // Send notifications for the event
        $results = $this->notificationService->notifyForEvent($event);

        // Verify all channels received notifications
        $this->assertCount(4, $results);
        
        $channels = collect($results)->pluck('channel')->toArray();
        $this->assertContains('email', $channels);
        $this->assertContains('sms', $channels);
        $this->assertContains('line', $channels);
        $this->assertContains('webhook', $channels);

        // Verify all notifications were successful
        foreach ($results as $result) {
            $this->assertTrue($result['success'], "Notification failed for channel: {$result['channel']}");
        }

        // Verify notification logs were created
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $emailSubscription->id,
            'channel' => 'email',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $smsSubscription->id,
            'channel' => 'sms',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $lineSubscription->id,
            'channel' => 'line',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $webhookSubscription->id,
            'channel' => 'webhook',
            'status' => 'sent',
        ]);
    }

    /**
     * Test template rendering with Thai/English content
     * Requirements: 8.6, 8.7
     */
    public function test_template_rendering_with_thai_english_content()
    {
        Mail::fake();

        $facility = Facility::factory()->create(['name' => 'ศูนย์กระจายสินค้ากรุงเทพ']);
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH9876543210',
            'current_status' => 'จัดส่งสำเร็จ',
            'service_type' => 'ด่วนพิเศษ',
        ]);

        // English subscription
        $englishSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'english@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        // Thai subscription (detected by Thai characters in destination)
        $thaiSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'ไทย@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
            'description' => 'จัดส่งพัสดุสำเร็จแล้ว',
            'facility_id' => $facility->id,
        ]);

        // Send notifications
        $englishResult = $this->notificationService->sendNotification($englishSubscription, $event);
        $thaiResult = $this->notificationService->sendNotification($thaiSubscription, $event);

        $this->assertTrue($englishResult['success']);
        $this->assertTrue($thaiResult['success']);

        // Verify both notifications were sent
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $englishSubscription->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $thaiSubscription->id,
            'status' => 'sent',
        ]);

        // Verify template manager handles both locales
        $templateManager = new TemplateManager();
        
        $englishTemplate = $templateManager->getTemplate('email', 'Delivered', 'en');
        $thaiTemplate = $templateManager->getTemplate('email', 'Delivered', 'th');

        $this->assertIsArray($englishTemplate);
        $this->assertIsArray($thaiTemplate);
        $this->assertArrayHasKey('subject', $englishTemplate);
        $this->assertArrayHasKey('subject', $thaiTemplate);

        // Thai template should contain Thai characters
        $this->assertMatchesRegularExpression('/[\x{0E00}-\x{0E7F}]/u', $thaiTemplate['subject']);
    }

    /**
     * Test throttling and delivery receipt handling
     * Requirements: 8.6, 8.7
     */
    public function test_throttling_and_delivery_receipt_handling()
    {
        Http::fake([
            '*/status*' => Http::response([
                'status' => 'delivered',
                'delivered_at' => '2024-01-15 10:30:00',
            ], 200),
            '*' => Http::response(['message_id' => 'sms_123', 'status' => 'sent'], 200),
        ]);

        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'sms',
            'destination' => '+66812345678',
            'events' => ['InTransit', 'Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $firstEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
        ]);

        // Send first notification
        $firstResult = $this->notificationService->sendNotification($subscription, $firstEvent);
        $this->assertTrue($firstResult['success']);

        // Create a second event immediately (should be throttled)
        $secondEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
        ]);

        $secondResult = $this->notificationService->sendNotification($subscription, $secondEvent);
        $this->assertFalse($secondResult['success']);
        $this->assertEquals('throttled', $secondResult['reason']);

        // Verify throttled log was created
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $subscription->id,
            'status' => 'throttled',
        ]);

        // Test critical event bypasses throttling
        $criticalEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered', // Critical event
        ]);

        $criticalResult = $this->notificationService->sendNotification($subscription, $criticalEvent);
        $this->assertTrue($criticalResult['success'], 'Critical event should bypass throttling');

        // Test delivery receipt handling
        $smsChannel = new \App\Services\Notification\SmsNotificationChannel();
        $deliveryStatus = $smsChannel->checkDeliveryStatus('sms_123');

        $this->assertEquals('delivered', $deliveryStatus['status']);
        $this->assertEquals('2024-01-15 10:30:00', $deliveryStatus['delivered_at']);

        // Test notification log delivery tracking
        $log = NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $firstEvent->id,
            'channel' => 'sms',
            'status' => 'sent',
        ]);

        $log->update([
            'delivered_at' => now(),
            'metadata' => array_merge($log->metadata ?? [], [
                'delivery_status' => 'delivered',
            ]),
        ]);

        $this->assertNotNull($log->fresh()->delivered_at);
        $this->assertEquals('delivered', $log->fresh()->metadata['delivery_status']);
    }

    /**
     * Test subscription consent and unsubscribe flows
     * Requirements: 4.1, 8.7
     */
    public function test_subscription_consent_and_unsubscribe_flows()
    {
        Mail::fake();

        $shipment = Shipment::factory()->create();
        
        // Test subscription without consent
        $noConsentSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'noconsent@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => false, // No consent
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
        ]);

        // Should not notify without consent
        $results = $this->notificationService->notifyForEvent($event);
        $this->assertCount(0, $results);

        // Test subscription with consent
        $consentSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'consent@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
            'consent_at' => now(),
            'consent_ip' => '192.168.1.1',
        ]);

        // Should notify with consent
        $results = $this->notificationService->notifyForEvent($event);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);

        // Test unsubscribe token generation
        $this->assertNotNull($consentSubscription->unsubscribe_token);
        $this->assertGreaterThan(50, strlen($consentSubscription->unsubscribe_token));

        // Test unsubscribe by token
        $token = $consentSubscription->unsubscribe_token;
        $unsubscribeResult = Subscription::unsubscribeByToken($token);
        $this->assertTrue($unsubscribeResult);

        // Verify subscription is now inactive
        $this->assertFalse($consentSubscription->fresh()->active);

        // Should not notify after unsubscribe
        $newEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'InTransit',
        ]);

        $results = $this->notificationService->notifyForEvent($newEvent);
        $this->assertCount(0, $results);

        // Test invalid unsubscribe token
        $invalidResult = Subscription::unsubscribeByToken('invalid_token');
        $this->assertFalse($invalidResult);

        // Test subscription consent tracking
        $this->assertTrue($consentSubscription->consent_given);
        $this->assertNotNull($consentSubscription->consent_at);
        $this->assertEquals('192.168.1.1', $consentSubscription->consent_ip);
    }

    /**
     * Test notification system error handling and recovery
     * Requirements: 8.5, 8.6, 8.7
     */
    public function test_notification_system_error_handling()
    {
        // Test email failure
        Mail::fake();
        
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'invalid_channel', // Invalid channel
            'destination' => 'test@example.com',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
        ]);

        $result = $this->notificationService->sendNotification($subscription, $event);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);

        // Verify error log was created
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $subscription->id,
            'status' => 'failed',
        ]);

        $log = NotificationLog::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($log->error_message);

        // Test SMS API failure
        Http::fake([
            '*' => Http::response(['error' => 'Invalid phone number'], 400),
        ]);

        $smsSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'channel' => 'sms',
            'destination' => 'invalid-phone',
            'events' => ['Delivered'],
            'active' => true,
            'consent_given' => true,
        ]);

        $smsResult = $this->notificationService->sendNotification($smsSubscription, $event);

        $this->assertFalse($smsResult['success']);
        $this->assertArrayHasKey('error', $smsResult);

        // Verify SMS error log
        $this->assertDatabaseHas('notification_logs', [
            'subscription_id' => $smsSubscription->id,
            'status' => 'failed',
        ]);
    }

    /**
     * Test notification statistics and analytics
     * Requirements: 8.6, 8.7
     */
    public function test_notification_statistics_and_analytics()
    {
        $subscription = Subscription::factory()->create([
            'active' => true,
            'consent_given' => true,
        ]);

        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);

        // Create various notification logs
        NotificationLog::factory()->count(5)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
            'delivered_at' => null,
        ]);

        NotificationLog::factory()->count(2)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'failed',
        ]);

        NotificationLog::factory()->count(1)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'throttled',
        ]);

        // Create delivered notifications
        NotificationLog::factory()->count(3)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
            'delivered_at' => now(),
        ]);

        $stats = $subscription->getStatistics();

        $this->assertEquals(8, $stats['total_sent']); // 5 + 3 delivered
        $this->assertEquals(2, $stats['total_failed']);
        $this->assertEquals(1, $stats['total_throttled']);
        $this->assertEquals(37.5, round($stats['delivery_rate'], 1)); // 3/8 * 100
        $this->assertNotNull($stats['last_sent_at']);
    }

    /**
     * Test multi-language notification content
     * Requirements: 8.6, 8.7
     */
    public function test_multi_language_notification_content()
    {
        $templateManager = new TemplateManager();

        // Test all supported event codes have both English and Thai templates
        $eventCodes = [
            'Created', 'PickedUp', 'InTransit', 'AtHub', 
            'OutForDelivery', 'Delivered', 'DeliveryAttempted', 
            'ExceptionRaised', 'Returned'
        ];

        foreach ($eventCodes as $eventCode) {
            // Test English templates
            $enTemplate = $templateManager->getTemplate('email', $eventCode, 'en');
            $this->assertIsArray($enTemplate);
            $this->assertArrayHasKey('subject', $enTemplate);

            // Test Thai templates
            $thTemplate = $templateManager->getTemplate('email', $eventCode, 'th');
            $this->assertIsArray($thTemplate);
            $this->assertArrayHasKey('subject', $thTemplate);

            // Test SMS templates
            $enSmsTemplate = $templateManager->getTemplate('sms', $eventCode, 'en');
            $this->assertIsArray($enSmsTemplate);

            $thSmsTemplate = $templateManager->getTemplate('sms', $eventCode, 'th');
            $this->assertIsArray($thSmsTemplate);
        }

        // Test template rendering with Thai content
        $variables = [
            'tracking_number' => 'TH1234567890',
            'event_description' => 'จัดส่งพัสดุสำเร็จแล้ว',
            'facility' => 'ศูนย์กระจายสินค้ากรุงเทพ',
            'current_status' => 'จัดส่งสำเร็จ',
            'eta' => '2024-01-15',
            'unsubscribe_url' => 'https://example.com/unsubscribe/token',
        ];

        $thaiTemplate = $templateManager->getTemplate('email', 'Delivered', 'th');
        $rendered = $templateManager->render($thaiTemplate, $variables);

        $this->assertStringContainsString('จัดส่งพัสดุสำเร็จแล้ว', $rendered);
        $this->assertStringContainsString('ศูนย์กระจายสินค้ากรุงเทพ', $rendered);
        $this->assertStringContainsString('UTF-8', $rendered);
    }

    /**
     * Test notification system performance under load
     * Requirements: 8.5, 8.6
     */
    public function test_notification_system_performance()
    {
        Mail::fake();
        Http::fake([
            '*' => Http::response(['message_id' => 'test', 'status' => 'sent'], 200),
        ]);

        $shipment = Shipment::factory()->create();
        
        // Create multiple subscriptions across different channels
        $subscriptions = collect();
        foreach (['email', 'sms', 'line', 'webhook'] as $channel) {
            for ($i = 0; $i < 5; $i++) {
                $subscriptions->push(Subscription::factory()->create([
                    'shipment_id' => $shipment->id,
                    'channel' => $channel,
                    'destination' => $channel === 'email' ? "test{$i}@example.com" : "dest{$i}",
                    'events' => ['Delivered'],
                    'active' => true,
                    'consent_given' => true,
                ]));
            }
        }

        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'Delivered',
        ]);

        $startTime = microtime(true);
        $results = $this->notificationService->notifyForEvent($event);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        // Should handle 20 notifications efficiently (under 5 seconds)
        $this->assertLessThan(5.0, $executionTime);
        $this->assertCount(20, $results);

        // All notifications should succeed
        foreach ($results as $result) {
            $this->assertTrue($result['success']);
        }

        // Verify all logs were created
        $this->assertEquals(20, NotificationLog::count());
    }
}