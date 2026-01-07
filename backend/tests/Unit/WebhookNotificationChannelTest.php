<?php

namespace Tests\Unit;

use App\Services\Notification\WebhookNotificationChannel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookNotificationChannelTest extends TestCase
{
    protected WebhookNotificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new WebhookNotificationChannel();
    }

    public function test_send_webhook_notification_successfully()
    {
        Http::fake([
            '*' => Http::response(['received' => true], 200),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'event_code' => 'Delivered',
                'event_description' => 'Package delivered successfully',
                'event_time' => '2024-01-15 10:30:00',
                'facility' => 'Bangkok Hub',
                'current_status' => 'Delivered',
                'eta' => '2024-01-15',
                'service_type' => 'Express',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('webhook', $result['channel']);
        $this->assertEquals($destination, $result['destination']);
        $this->assertEquals(200, $result['status_code']);
        $this->assertArrayHasKey('message_id', $result);
    }

    public function test_send_webhook_includes_proper_payload_structure()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH9876543210',
                'event_code' => 'InTransit',
                'event_description' => 'Package in transit',
                'current_status' => 'InTransit',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);

        // Verify the webhook payload structure
        Http::assertSent(function ($request) use ($destination) {
            $body = $request->data();
            
            return $request->url() === $destination &&
                   $body['event'] === 'shipment.updated' &&
                   isset($body['timestamp']) &&
                   isset($body['data']) &&
                   $body['data']['tracking_number'] === 'TH9876543210' &&
                   $body['data']['event_code'] === 'InTransit' &&
                   $body['data']['current_status'] === 'InTransit';
        });
    }

    public function test_send_webhook_includes_hmac_signature()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1111111111',
                'event_code' => 'PickedUp',
            ],
        ];

        $this->channel->send($destination, $data);

        // Verify HMAC signature is included in headers
        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Webhook-Signature') &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('User-Agent', 'ParcelTracking/1.0');
        });
    }

    public function test_send_webhook_retries_on_server_error()
    {
        // First two attempts fail, third succeeds
        Http::fakeSequence()
            ->push([], 500)
            ->push([], 502)
            ->push(['success' => true], 200);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'event_code' => 'Delivered',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status_code']);

        // Verify 3 requests were made (2 retries + 1 success)
        Http::assertSentCount(3);
    }

    public function test_send_webhook_does_not_retry_on_client_error()
    {
        Http::fake([
            '*' => Http::response(['error' => 'Bad request'], 400),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'event_code' => 'Delivered',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Client error', $result['error']);

        // Verify only 1 request was made (no retries for 4xx errors)
        Http::assertSentCount(1);
    }

    public function test_send_webhook_fails_after_max_retries()
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'event_code' => 'Delivered',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);

        // Verify all retry attempts were made (default is 3)
        Http::assertSentCount(3);
    }

    public function test_send_webhook_handles_network_exception()
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'event_code' => 'Delivered',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection refused', $result['error']);
    }

    public function test_check_delivery_status_returns_delivered()
    {
        $messageId = 'webhook_12345';
        $result = $this->channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('delivered', $result['status']);
        $this->assertEquals('webhook', $result['channel']);
        $this->assertStringContainsString('determined at send time', $result['note']);
    }

    public function test_webhook_payload_includes_all_tracking_data()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH5555555555',
                'event_code' => 'OutForDelivery',
                'event_description' => 'Out for delivery',
                'event_time' => '2024-01-16 08:00:00',
                'facility' => 'Local Delivery Hub',
                'location' => 'Bangkok',
                'current_status' => 'OutForDelivery',
                'eta' => '2024-01-16',
                'service_type' => 'Standard',
            ],
        ];

        $this->channel->send($destination, $data);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $data = $body['data'];
            
            return $data['tracking_number'] === 'TH5555555555' &&
                   $data['event_code'] === 'OutForDelivery' &&
                   $data['event_description'] === 'Out for delivery' &&
                   $data['event_time'] === '2024-01-16 08:00:00' &&
                   $data['facility'] === 'Local Delivery Hub' &&
                   $data['location'] === 'Bangkok' &&
                   $data['current_status'] === 'OutForDelivery' &&
                   $data['eta'] === '2024-01-16' &&
                   $data['service_type'] === 'Standard';
        });
    }

    public function test_webhook_handles_missing_variables()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1234567890',
                // Missing other variables
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $data = $body['data'];
            
            return $data['tracking_number'] === 'TH1234567890' &&
                   $data['event_code'] === null &&
                   $data['facility'] === null;
        });
    }

    public function test_hmac_signature_generation()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'https://example.com/webhook';
        $data = [
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'event_code' => 'Delivered',
            ],
        ];

        $this->channel->send($destination, $data);

        Http::assertSent(function ($request) {
            $signature = $request->header('X-Webhook-Signature')[0];
            
            // Verify signature is a valid SHA256 hash (64 characters)
            return strlen($signature) === 64 && ctype_xdigit($signature);
        });
    }
}