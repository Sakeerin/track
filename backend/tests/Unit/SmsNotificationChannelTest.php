<?php

namespace Tests\Unit;

use App\Services\Notification\SmsNotificationChannel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotificationChannelTest extends TestCase
{
    protected SmsNotificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new SmsNotificationChannel();
    }

    public function test_send_sms_notification_successfully()
    {
        Http::fake([
            '*' => Http::response([
                'message_id' => 'sms_12345',
                'status' => 'sent',
            ], 200),
        ]);

        $destination = '+66812345678';
        $data = [
            'content' => '<p>Your package TH1234567890 has been delivered</p>',
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'current_status' => 'Delivered',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('sms', $result['channel']);
        $this->assertEquals($destination, $result['destination']);
        $this->assertEquals('sms_12345', $result['message_id']);
        $this->assertEquals('sent', $result['delivery_status']);
    }

    public function test_send_sms_strips_html_tags()
    {
        Http::fake([
            '*' => Http::response([
                'message_id' => 'sms_67890',
                'status' => 'sent',
            ], 200),
        ]);

        $destination = '+66812345678';
        $data = [
            'content' => '<p><strong>Your package</strong> <em>TH1234567890</em> has been <u>delivered</u></p>',
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);

        // Verify the request was made with plain text
        Http::assertSent(function ($request) {
            $body = $request->data();
            return !str_contains($body['message'], '<') && !str_contains($body['message'], '>');
        });
    }

    public function test_send_sms_truncates_long_messages()
    {
        Http::fake([
            '*' => Http::response([
                'message_id' => 'sms_truncated',
                'status' => 'sent',
            ], 200),
        ]);

        $longMessage = str_repeat('This is a very long message that exceeds SMS limits. ', 10);
        $destination = '+66812345678';
        $data = [
            'content' => $longMessage,
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);

        // Verify the message was truncated to 160 characters
        Http::assertSent(function ($request) {
            $body = $request->data();
            return mb_strlen($body['message']) <= 160;
        });
    }

    public function test_send_sms_handles_api_error()
    {
        Http::fake([
            '*' => Http::response([
                'error' => 'Invalid phone number',
            ], 400),
        ]);

        $destination = 'invalid-phone';
        $data = [
            'content' => 'Test message',
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertFalse($result['success']);
        $this->assertEquals('sms', $result['channel']);
        $this->assertEquals($destination, $result['destination']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_send_sms_handles_network_exception()
    {
        Http::fake(function () {
            throw new \Exception('Network timeout');
        });

        $destination = '+66812345678';
        $data = [
            'content' => 'Test message',
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Network timeout', $result['error']);
    }

    public function test_check_delivery_status_successfully()
    {
        Http::fake([
            '*/status*' => Http::response([
                'status' => 'delivered',
                'delivered_at' => '2024-01-15 10:30:00',
            ], 200),
        ]);

        $messageId = 'sms_12345';
        $result = $this->channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('delivered', $result['status']);
        $this->assertEquals('sms', $result['channel']);
        $this->assertEquals('2024-01-15 10:30:00', $result['delivered_at']);
    }

    public function test_check_delivery_status_handles_api_error()
    {
        Http::fake([
            '*/status*' => Http::response([], 500),
        ]);

        $messageId = 'sms_12345';
        $result = $this->channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('unknown', $result['status']);
        $this->assertEquals('sms', $result['channel']);
    }

    public function test_check_delivery_status_handles_network_exception()
    {
        Http::fake(function () {
            throw new \Exception('Connection failed');
        });

        $messageId = 'sms_12345';
        $result = $this->channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('sms', $result['channel']);
        $this->assertStringContainsString('Connection failed', $result['error']);
    }

    public function test_send_sms_includes_proper_headers()
    {
        Http::fake([
            '*' => Http::response(['message_id' => 'test'], 200),
        ]);

        $this->channel->send('+66812345678', ['content' => 'Test']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization') &&
                   $request->hasHeader('Content-Type', 'application/json');
        });
    }
}