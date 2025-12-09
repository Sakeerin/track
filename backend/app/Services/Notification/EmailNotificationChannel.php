<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationChannel implements NotificationChannelInterface
{
    /**
     * Send email notification
     */
    public function send(string $destination, array $data): array
    {
        try {
            $subject = $data['subject'] ?? 'Shipment Update';
            $content = $data['content'] ?? '';
            $variables = $data['variables'] ?? [];

            Mail::send([], [], function ($message) use ($destination, $subject, $content) {
                $message->to($destination)
                    ->subject($subject)
                    ->html($content);
            });

            return [
                'success' => true,
                'message_id' => uniqid('email_', true),
                'channel' => 'email',
                'destination' => $destination,
            ];

        } catch (\Exception $e) {
            Log::error("Email notification failed", [
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'email',
                'destination' => $destination,
            ];
        }
    }

    /**
     * Check email delivery status
     * Note: Actual implementation would integrate with email service provider API
     */
    public function checkDeliveryStatus(string $messageId): array
    {
        // Placeholder - would integrate with email service provider (Mailgun, SendGrid, etc.)
        return [
            'message_id' => $messageId,
            'status' => 'unknown',
            'channel' => 'email',
        ];
    }
}
