<?php

namespace Tests\Unit;

use App\Services\Notification\EmailNotificationChannel;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationChannelTest extends TestCase
{
    protected EmailNotificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new EmailNotificationChannel();
    }

    public function test_send_email_notification_successfully()
    {
        Mail::fake();

        $destination = 'test@example.com';
        $data = [
            'subject' => 'Test Shipment Update',
            'content' => '<p>Your package has been delivered</p>',
            'variables' => [
                'tracking_number' => 'TH1234567890',
                'current_status' => 'Delivered',
            ],
        ];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('email', $result['channel']);
        $this->assertEquals($destination, $result['destination']);
        $this->assertArrayHasKey('message_id', $result);
    }

    public function test_send_email_with_minimal_data()
    {
        Mail::fake();

        $destination = 'minimal@example.com';
        $data = [];

        $result = $this->channel->send($destination, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('email', $result['channel']);
    }

    public function test_check_delivery_status_returns_unknown()
    {
        $messageId = 'email_test123';
        
        $result = $this->channel->checkDeliveryStatus($messageId);

        $this->assertEquals($messageId, $result['message_id']);
        $this->assertEquals('unknown', $result['status']);
        $this->assertEquals('email', $result['channel']);
    }
}
