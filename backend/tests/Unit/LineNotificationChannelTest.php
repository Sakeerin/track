<?php

namespace Tests\Unit;

use App\Services\Notification\LineNotificationChannel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineNotificationChannelTest extends TestCase
{
    protected LineNotificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock LINE configuration
        config([
            'services.line.channel_access_token' => 'test_channel_access_token',
            'services.line.api_url' => 'https://api.line.me/v2/bot/message/push',
        ]);
        
        $this->channel = new LineNotificationChannel();
    }

    public function test_send_line_notification_successfully()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'U1234567890abcdef';
        $data = [
            'content' => '<p>Your package <strong>TH1234567890</strong> has been delivered</p>',
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'current_status' => 'Delivered',
                'facility' => 'Bangkok Hub',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('line', $result['channel']);
        $this->assertEquals($destination, $result['destination']);
        $this->assertArrayHasKey('message_id', $result);
    }

    public function test_send_line_creates_flex_message_with_tracking_info()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'U1234567890abcdef';
        $data = [
            'content' => 'Package update',
            'variables' => [
                'tracking_number' => 'TH9876543210',
                'current_status' => 'InTransit',
                'facility' => 'Chiang Mai Hub',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);

        // Verify the request contains flex message with tracking info
        Http::assertSent(function ($request) use ($destination) {
            $body = $request->data();
            
            return $body['to'] === $destination &&
                   isset($body['messages']) &&
                   count($body['messages']) === 2 &&
                   $body['messages'][0]['type'] === 'text' &&
                   $body['messages'][1]['type'] === 'flex' &&
                   str_contains(json_encode($body['messages'][1]), 'TH9876543210') &&
                   str_contains(json_encode($body['messages'][1]), 'InTransit') &&
                   str_contains(json_encode($body['messages'][1]), 'Chiang Mai Hub');
        });
    }

    public function test_send_line_strips_html_from_text_message()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'U1234567890abcdef';
        $data = [
            'content' => '<p><strong>Your package</strong> <em>TH1234567890</em> has been <u>delivered</u></p>',
            'variables' => [],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);

        // Verify HTML tags are stripped from text message
        Http::assertSent(function ($request) {
            $body = $request->data();
            $textMessage = $body['messages'][0]['text'];
            
            return !str_contains($textMessage, '<') && 
                   !str_contains($textMessage, '>') &&
                   str_contains($textMessage, 'Your package TH1234567890 has been delivered');
        });
    }

    public function test_send_line_handles_api_error()
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Invalid user ID',
            ], 400),
        ]);

        $destination = 'invalid-user-id';
        $data = [
            'content' => 'Test message',
            'variables' => [],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertFalse($result['success']);
        $this->assertEquals('line', $result['channel']);
        $this->assertEquals($destination, $result['destination']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_send_line_handles_network_exception()
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        $destination = 'U1234567890abcdef';
        $data = [
            'content' => 'Test message',
            'variables' => [],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection timeout', $result['error']);
    }

    public function test_check_delivery_status_returns_sent_status()
    {
        $messageId = 'line_12345';
        $result = $this->channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('sent', $result['status']);
        $this->assertEquals('line', $result['channel']);
        $this->assertStringContainsString('does not provide delivery receipts', $result['note']);
    }

    public function test_send_line_includes_proper_headers()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $result = $this->channel->send('U1234567890abcdef', [
            'content' => 'Test',
            'variables' => [],
        ]);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            $hasAuth = $request->hasHeader('Authorization');
            $hasContentType = $request->hasHeader('Content-Type');
            $authHeader = $request->header('Authorization');
            
            return $hasAuth && $hasContentType && 
                   is_array($authHeader) && count($authHeader) > 0 &&
                   str_starts_with($authHeader[0], 'Bearer ');
        });
    }

    public function test_flex_message_structure_is_valid()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'U1234567890abcdef';
        $data = [
            'content' => 'Test message',
            'variables' => [
                'tracking_number' => 'TH1111111111',
                'current_status' => 'OutForDelivery',
                'facility' => 'Test Facility',
            ],
        ];

        $this->channel->send($destination, $data);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $flexMessage = $body['messages'][1];
            
            // Verify flex message structure
            return $flexMessage['type'] === 'flex' &&
                   $flexMessage['altText'] === 'Shipment Update' &&
                   isset($flexMessage['contents']['type']) &&
                   $flexMessage['contents']['type'] === 'bubble' &&
                   isset($flexMessage['contents']['body']['contents']) &&
                   is_array($flexMessage['contents']['body']['contents']);
        });
    }

    public function test_handles_missing_variables_gracefully()
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $destination = 'U1234567890abcdef';
        $data = [
            'content' => 'Test message',
            'variables' => [], // No variables provided
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);

        // Verify request was made (simplified assertion)
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.line.me/v2/bot/message/push';
        });
    }
}