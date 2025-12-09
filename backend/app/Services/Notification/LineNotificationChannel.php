<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineNotificationChannel implements NotificationChannelInterface
{
    protected string $channelAccessToken;
    protected string $apiUrl;

    public function __construct()
    {
        $this->channelAccessToken = config('services.line.channel_access_token', '');
        $this->apiUrl = config('services.line.api_url', 'https://api.line.me/v2/bot/message/push');
    }

    /**
     * Send LINE notification
     */
    public function send(string $destination, array $data): array
    {
        try {
            $content = $data['content'] ?? '';
            $variables = $data['variables'] ?? [];
            
            // Create LINE message payload
            $messages = $this->createLineMessage($content, $variables);

            // Make API call to LINE Messaging API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->channelAccessToken}",
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'to' => $destination, // LINE User ID
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => uniqid('line_', true),
                    'channel' => 'line',
                    'destination' => $destination,
                ];
            }

            throw new \Exception("LINE API returned error: " . $response->body());

        } catch (\Exception $e) {
            Log::error("LINE notification failed", [
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'line',
                'destination' => $destination,
            ];
        }
    }

    /**
     * Create LINE message format
     */
    protected function createLineMessage(string $content, array $variables): array
    {
        // Strip HTML tags for LINE text message
        $plainText = strip_tags($content);

        // Create a flex message for better formatting
        return [
            [
                'type' => 'text',
                'text' => $plainText,
            ],
            [
                'type' => 'flex',
                'altText' => 'Shipment Update',
                'contents' => [
                    'type' => 'bubble',
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => 'Shipment Update',
                                'weight' => 'bold',
                                'size' => 'xl',
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'lg',
                                'spacing' => 'sm',
                                'contents' => [
                                    [
                                        'type' => 'box',
                                        'layout' => 'baseline',
                                        'spacing' => 'sm',
                                        'contents' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'Tracking:',
                                                'color' => '#aaaaaa',
                                                'size' => 'sm',
                                                'flex' => 2,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $variables['tracking_number'] ?? 'N/A',
                                                'wrap' => true,
                                                'color' => '#666666',
                                                'size' => 'sm',
                                                'flex' => 5,
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'box',
                                        'layout' => 'baseline',
                                        'spacing' => 'sm',
                                        'contents' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'Status:',
                                                'color' => '#aaaaaa',
                                                'size' => 'sm',
                                                'flex' => 2,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $variables['current_status'] ?? 'N/A',
                                                'wrap' => true,
                                                'color' => '#666666',
                                                'size' => 'sm',
                                                'flex' => 5,
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'box',
                                        'layout' => 'baseline',
                                        'spacing' => 'sm',
                                        'contents' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'Location:',
                                                'color' => '#aaaaaa',
                                                'size' => 'sm',
                                                'flex' => 2,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $variables['facility'] ?? 'N/A',
                                                'wrap' => true,
                                                'color' => '#666666',
                                                'size' => 'sm',
                                                'flex' => 5,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Check LINE delivery status
     * Note: LINE Messaging API doesn't provide delivery receipts for push messages
     */
    public function checkDeliveryStatus(string $messageId): array
    {
        // LINE doesn't provide delivery status for push messages
        return [
            'message_id' => $messageId,
            'status' => 'sent',
            'channel' => 'line',
            'note' => 'LINE does not provide delivery receipts',
        ];
    }
}
