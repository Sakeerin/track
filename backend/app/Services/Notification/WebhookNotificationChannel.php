<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookNotificationChannel implements NotificationChannelInterface
{
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct()
    {
        $this->timeout = config('services.webhook.timeout', 10);
        $this->retryAttempts = config('services.webhook.retry_attempts', 3);
        $this->retryDelay = config('services.webhook.retry_delay', 1000); // milliseconds
    }

    /**
     * Send webhook notification
     */
    public function send(string $destination, array $data): array
    {
        try {
            $variables = $data['variables'] ?? [];
            
            // Prepare webhook payload
            $payload = [
                'event' => 'shipment.updated',
                'timestamp' => now()->toIso8601String(),
                'data' => [
                    'tracking_number' => $variables['tracking_number'] ?? null,
                    'event_code' => $variables['event_code'] ?? null,
                    'event_description' => $variables['event_description'] ?? null,
                    'event_time' => $variables['event_time'] ?? null,
                    'facility' => $variables['facility'] ?? null,
                    'location' => $variables['location'] ?? null,
                    'current_status' => $variables['current_status'] ?? null,
                    'eta' => $variables['eta'] ?? null,
                    'service_type' => $variables['service_type'] ?? null,
                ],
            ];

            // Add HMAC signature for webhook authentication
            $signature = $this->generateSignature($payload);

            // Send webhook with retry logic
            $response = $this->sendWithRetry($destination, $payload, $signature);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message_id' => uniqid('webhook_', true),
                    'channel' => 'webhook',
                    'destination' => $destination,
                    'status_code' => $response['status_code'],
                ];
            }

            throw new \Exception($response['error'] ?? 'Webhook delivery failed');

        } catch (\Exception $e) {
            Log::error("Webhook notification failed", [
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'webhook',
                'destination' => $destination,
            ];
        }
    }

    /**
     * Send webhook with exponential backoff retry
     */
    protected function sendWithRetry(string $url, array $payload, string $signature): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Webhook-Signature' => $signature,
                        'User-Agent' => 'ParcelTracking/1.0',
                    ])
                    ->post($url, $payload);

                // Consider 2xx status codes as success
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'status_code' => $response->status(),
                        'response_body' => $response->body(),
                    ];
                }

                // Don't retry on 4xx errors (client errors)
                if ($response->clientError()) {
                    return [
                        'success' => false,
                        'error' => "Client error: {$response->status()}",
                        'status_code' => $response->status(),
                    ];
                }

                $lastError = "Server error: {$response->status()}";

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }

            $attempt++;
            
            // Exponential backoff: wait longer between each retry
            if ($attempt < $this->retryAttempts) {
                $delay = $this->retryDelay * pow(2, $attempt - 1);
                usleep($delay * 1000); // Convert to microseconds
            }
        }

        return [
            'success' => false,
            'error' => $lastError ?? 'All retry attempts failed',
            'attempts' => $attempt,
        ];
    }

    /**
     * Generate HMAC signature for webhook payload
     */
    protected function generateSignature(array $payload): string
    {
        $secret = config('services.webhook.secret', config('app.key'));
        $jsonPayload = json_encode($payload);
        
        return hash_hmac('sha256', $jsonPayload, $secret);
    }

    /**
     * Check webhook delivery status
     */
    public function checkDeliveryStatus(string $messageId): array
    {
        // Webhooks are fire-and-forget, status is determined at send time
        return [
            'message_id' => $messageId,
            'status' => 'delivered',
            'channel' => 'webhook',
            'note' => 'Webhook delivery status determined at send time',
        ];
    }
}
