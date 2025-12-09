<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsNotificationChannel implements NotificationChannelInterface
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $senderId;

    public function __construct()
    {
        $this->apiUrl = config('services.sms.api_url', 'https://api.sms-provider.com/send');
        $this->apiKey = config('services.sms.api_key', '');
        $this->senderId = config('services.sms.sender_id', 'TRACKING');
    }

    /**
     * Send SMS notification
     */
    public function send(string $destination, array $data): array
    {
        try {
            // Extract plain text content (strip HTML if present)
            $content = $data['content'] ?? '';
            $plainText = strip_tags($content);
            
            // Truncate to SMS length limit (160 chars for single SMS)
            $message = mb_substr($plainText, 0, 160);

            // Make API call to SMS gateway
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'to' => $destination,
                'from' => $this->senderId,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                return [
                    'success' => true,
                    'message_id' => $responseData['message_id'] ?? uniqid('sms_', true),
                    'channel' => 'sms',
                    'destination' => $destination,
                    'delivery_status' => $responseData['status'] ?? 'sent',
                ];
            }

            throw new \Exception("SMS API returned error: " . $response->body());

        } catch (\Exception $e) {
            Log::error("SMS notification failed", [
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'sms',
                'destination' => $destination,
            ];
        }
    }

    /**
     * Check SMS delivery status
     */
    public function checkDeliveryStatus(string $messageId): array
    {
        try {
            $statusUrl = config('services.sms.status_url', 'https://api.sms-provider.com/status');
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->get($statusUrl, [
                'message_id' => $messageId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'message_id' => $messageId,
                    'status' => $data['status'] ?? 'unknown',
                    'delivered_at' => $data['delivered_at'] ?? null,
                    'channel' => 'sms',
                ];
            }

            return [
                'message_id' => $messageId,
                'status' => 'unknown',
                'channel' => 'sms',
            ];

        } catch (\Exception $e) {
            Log::error("SMS status check failed", [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'message_id' => $messageId,
                'status' => 'error',
                'error' => $e->getMessage(),
                'channel' => 'sms',
            ];
        }
    }
}
