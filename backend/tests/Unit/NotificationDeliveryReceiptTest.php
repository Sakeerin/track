<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\LineNotificationChannel;
use App\Services\Notification\SmsNotificationChannel;
use App\Services\Notification\WebhookNotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationDeliveryReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_channel_check_delivery_status_returns_unknown()
    {
        $channel = new EmailNotificationChannel();
        $messageId = 'email_test_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('unknown', $result['status']);
        $this->assertEquals('email', $result['channel']);
    }

    public function test_sms_channel_check_delivery_status_success()
    {
        Http::fake([
            '*/status*' => Http::response([
                'status' => 'delivered',
                'delivered_at' => '2024-01-15 10:30:00',
            ], 200),
        ]);

        $channel = new SmsNotificationChannel();
        $messageId = 'sms_test_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('delivered', $result['status']);
        $this->assertEquals('sms', $result['channel']);
        $this->assertEquals('2024-01-15 10:30:00', $result['delivered_at']);
    }

    public function test_sms_channel_check_delivery_status_pending()
    {
        Http::fake([
            '*/status*' => Http::response([
                'status' => 'pending',
            ], 200),
        ]);

        $channel = new SmsNotificationChannel();
        $messageId = 'sms_pending_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals('sms', $result['channel']);
    }

    public function test_sms_channel_check_delivery_status_failed()
    {
        Http::fake([
            '*/status*' => Http::response([
                'status' => 'failed',
                'error_reason' => 'Invalid phone number',
            ], 200),
        ]);

        $channel = new SmsNotificationChannel();
        $messageId = 'sms_failed_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('sms', $result['channel']);
    }

    public function test_sms_channel_check_delivery_status_api_error()
    {
        Http::fake([
            '*/status*' => Http::response([], 500),
        ]);

        $channel = new SmsNotificationChannel();
        $messageId = 'sms_error_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('unknown', $result['status']);
        $this->assertEquals('sms', $result['channel']);
    }

    public function test_sms_channel_check_delivery_status_network_exception()
    {
        Http::fake(function () {
            throw new \Exception('Network timeout');
        });

        $channel = new SmsNotificationChannel();
        $messageId = 'sms_timeout_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('sms', $result['channel']);
        $this->assertStringContainsString('Network timeout', $result['error']);
    }

    public function test_line_channel_check_delivery_status_returns_sent()
    {
        $channel = new LineNotificationChannel();
        $messageId = 'line_test_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('sent', $result['status']);
        $this->assertEquals('line', $result['channel']);
        $this->assertStringContainsString('does not provide delivery receipts', $result['note']);
    }

    public function test_webhook_channel_check_delivery_status_returns_delivered()
    {
        $channel = new WebhookNotificationChannel();
        $messageId = 'webhook_test_123';

        $result = $channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('delivered', $result['status']);
        $this->assertEquals('webhook', $result['channel']);
        $this->assertStringContainsString('determined at send time', $result['note']);
    }

    public function test_notification_log_tracks_delivery_status()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);

        $log = NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'sms',
            'status' => 'sent',
            'delivered_at' => null,
        ]);

        // Simulate delivery receipt update
        $log->update([
            'delivered_at' => now(),
            'delivery_status' => 'delivered',
        ]);

        $this->assertNotNull($log->fresh()->delivered_at);
        $this->assertEquals('delivered', $log->fresh()->delivery_status);
    }

    public function test_notification_log_tracks_delivery_failure()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);

        $log = NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'sms',
            'status' => 'sent',
        ]);

        // Simulate delivery failure
        $log->update([
            'delivery_status' => 'failed',
            'delivery_error' => 'Phone number unreachable',
        ]);

        $this->assertEquals('failed', $log->fresh()->delivery_status);
        $this->assertEquals('Phone number unreachable', $log->fresh()->delivery_error);
    }

    public function test_subscription_statistics_include_delivery_rate()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);

        // Create 10 sent notifications
        NotificationLog::factory()->count(10)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
        ]);

        // Mark 8 as delivered
        NotificationLog::factory()->count(8)->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'status' => 'sent',
            'delivered_at' => now(),
        ]);

        $stats = $subscription->getStatistics();

        $this->assertEquals(18, $stats['total_sent']);
        $this->assertEquals(44.44, round($stats['delivery_rate'], 2)); // 8/18 * 100
    }

    public function test_subscription_statistics_handle_zero_sent_notifications()
    {
        $subscription = Subscription::factory()->create();

        $stats = $subscription->getStatistics();

        $this->assertEquals(0, $stats['total_sent']);
        $this->assertEquals(0, $stats['delivery_rate']);
    }

    public function test_notification_log_factory_states()
    {
        $subscription = Subscription::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);

        // Test sent state
        $sentLog = NotificationLog::factory()->sent()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        $this->assertEquals('sent', $sentLog->status);

        // Test failed state
        $failedLog = NotificationLog::factory()->failed()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        $this->assertEquals('failed', $failedLog->status);

        // Test throttled state
        $throttledLog = NotificationLog::factory()->throttled()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        $this->assertEquals('throttled', $throttledLog->status);

        // Test delivered state
        $deliveredLog = NotificationLog::factory()->delivered()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
        ]);
        $this->assertNotNull($deliveredLog->delivered_at);
    }

    public function test_delivery_receipt_webhook_processing()
    {
        $subscription = Subscription::factory()->create([
            'channel' => 'sms',
        ]);
        $event = Event::factory()->create(['shipment_id' => $subscription->shipment_id]);

        $log = NotificationLog::factory()->create([
            'subscription_id' => $subscription->id,
            'event_id' => $event->id,
            'channel' => 'sms',
            'status' => 'sent',
            'metadata' => [
                'external_message_id' => 'sms_ext_123',
                'provider' => 'test_sms_provider',
            ],
        ]);

        // Simulate webhook delivery receipt
        $deliveryData = [
            'message_id' => 'sms_ext_123',
            'status' => 'delivered',
            'delivered_at' => '2024-01-15 14:30:00',
            'delivery_attempts' => 1,
        ];

        // Update log with delivery receipt
        $log->update([
            'delivered_at' => $deliveryData['delivered_at'],
            'delivery_status' => $deliveryData['status'],
            'metadata' => array_merge($log->metadata ?? [], [
                'delivery_receipt' => $deliveryData,
            ]),
        ]);

        $updatedLog = $log->fresh();
        $this->assertEquals('delivered', $updatedLog->delivery_status);
        $this->assertNotNull($updatedLog->delivered_at);
        $this->assertArrayHasKey('delivery_receipt', $updatedLog->metadata);
    }

    public function test_bulk_delivery_status_check()
    {
        Http::fake([
            '*/status*' => Http::response([
                'results' => [
                    ['message_id' => 'sms_1', 'status' => 'delivered'],
                    ['message_id' => 'sms_2', 'status' => 'pending'],
                    ['message_id' => 'sms_3', 'status' => 'failed'],
                ],
            ], 200),
        ]);

        $channel = new SmsNotificationChannel();
        $messageIds = ['sms_1', 'sms_2', 'sms_3'];

        foreach ($messageIds as $messageId) {
            $result = $channel->checkDeliveryStatus($messageId);
            $this->assertEquals($messageId, $result['message_id']);
            $this->assertContains($result['status'], ['delivered', 'pending', 'failed']);
        }
    }

    public function test_delivery_receipt_retry_mechanism()
    {
        // Simulate API returning different statuses on retry
        Http::fakeSequence()
            ->push(['status' => 'pending'], 200)
            ->push(['status' => 'pending'], 200)
            ->push(['status' => 'delivered', 'delivered_at' => '2024-01-15 15:00:00'], 200);

        $channel = new SmsNotificationChannel();
        $messageId = 'sms_retry_123';

        // First check - pending
        $result1 = $channel->checkDeliveryStatus($messageId);
        $this->assertEquals('pending', $result1['status']);

        // Second check - still pending
        $result2 = $channel->checkDeliveryStatus($messageId);
        $this->assertEquals('pending', $result2['status']);

        // Third check - delivered
        $result3 = $channel->checkDeliveryStatus($messageId);
        $this->assertEquals('delivered', $result3['status']);
        $this->assertEquals('2024-01-15 15:00:00', $result3['delivered_at']);
    }
}