<?php

namespace App\Services\Notification;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected EmailNotificationChannel $emailChannel;
    protected SmsNotificationChannel $smsChannel;
    protected LineNotificationChannel $lineChannel;
    protected WebhookNotificationChannel $webhookChannel;
    protected TemplateManager $templateManager;

    public function __construct(
        EmailNotificationChannel $emailChannel,
        SmsNotificationChannel $smsChannel,
        LineNotificationChannel $lineChannel,
        WebhookNotificationChannel $webhookChannel,
        TemplateManager $templateManager
    ) {
        $this->emailChannel = $emailChannel;
        $this->smsChannel = $smsChannel;
        $this->lineChannel = $lineChannel;
        $this->webhookChannel = $webhookChannel;
        $this->templateManager = $templateManager;
    }

    /**
     * Send notification for an event to all subscribed channels
     */
    public function notifyForEvent(Event $event): array
    {
        $shipment = $event->shipment;
        $subscriptions = $shipment->subscriptions()
            ->active()
            ->withConsent()
            ->get();

        $results = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription->shouldNotifyForEvent($event->event_code)) {
                $result = $this->sendNotification($subscription, $event);
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Send notification to a specific subscription
     */
    public function sendNotification(Subscription $subscription, Event $event): array
    {
        try {
            $channel = $this->getChannel($subscription->channel);
            
            if (!$channel) {
                throw new \InvalidArgumentException("Invalid notification channel: {$subscription->channel}");
            }

            // Check throttling
            if ($subscription->shouldThrottle($event->event_code)) {
                Log::info("Notification throttled", [
                    'subscription_id' => $subscription->id,
                    'event_code' => $event->event_code,
                ]);

                // Log throttled notification
                NotificationLog::create([
                    'subscription_id' => $subscription->id,
                    'event_id' => $event->id,
                    'channel' => $subscription->channel,
                    'destination' => $subscription->destination,
                    'status' => 'throttled',
                    'sent_at' => now(),
                    'metadata' => [
                        'event_code' => $event->event_code,
                        'reason' => 'throttled_by_rate_limit',
                    ],
                ]);
                
                return [
                    'success' => false,
                    'subscription_id' => $subscription->id,
                    'channel' => $subscription->channel,
                    'reason' => 'throttled',
                ];
            }

            // Prepare notification data
            $data = $this->prepareNotificationData($subscription, $event);

            // Send through channel
            $result = $channel->send($subscription->destination, $data);

            // Log notification attempt
            $logData = [
                'subscription_id' => $subscription->id,
                'event_id' => $event->id,
                'channel' => $subscription->channel,
                'destination' => $subscription->destination,
                'status' => $result['success'] ? 'sent' : 'failed',
                'sent_at' => now(),
                'metadata' => [
                    'event_code' => $event->event_code,
                    'tracking_number' => $event->shipment->tracking_number,
                ],
            ];

            if (!$result['success']) {
                $logData['error_message'] = $result['error'] ?? 'Unknown error';
            }

            NotificationLog::create($logData);

            Log::info("Notification sent", [
                'subscription_id' => $subscription->id,
                'channel' => $subscription->channel,
                'event_code' => $event->event_code,
                'success' => $result['success'],
            ]);

            return array_merge($result, [
                'subscription_id' => $subscription->id,
                'channel' => $subscription->channel,
            ]);

        } catch (\Exception $e) {
            Log::error("Notification failed", [
                'subscription_id' => $subscription->id,
                'channel' => $subscription->channel,
                'error' => $e->getMessage(),
            ]);

            // Log failed notification
            NotificationLog::create([
                'subscription_id' => $subscription->id,
                'event_id' => $event->id,
                'channel' => $subscription->channel,
                'destination' => $subscription->destination,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
                'metadata' => [
                    'event_code' => $event->event_code,
                ],
            ]);

            return [
                'success' => false,
                'subscription_id' => $subscription->id,
                'channel' => $subscription->channel,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get notification channel instance
     */
    protected function getChannel(string $channelName): ?NotificationChannelInterface
    {
        switch ($channelName) {
            case 'email':
                return $this->emailChannel;
            case 'sms':
                return $this->smsChannel;
            case 'line':
                return $this->lineChannel;
            case 'webhook':
                return $this->webhookChannel;
            default:
                return null;
        }
    }



    /**
     * Prepare notification data with template rendering
     */
    protected function prepareNotificationData(Subscription $subscription, Event $event): array
    {
        $shipment = $event->shipment;
        $locale = $this->detectLocale($subscription->destination);

        // Get template for event type
        $template = $this->templateManager->getTemplate(
            $subscription->channel,
            $event->event_code,
            $locale
        );

        // Prepare variables for template
        $variables = [
            'tracking_number' => $shipment->tracking_number,
            'event_code' => $event->event_code,
            'event_description' => $event->description,
            'event_time' => $event->event_time->format('Y-m-d H:i:s'),
            'facility' => $event->facility ? $event->facility->name : 'N/A',
            'location' => $event->location ? $event->location->name : 'N/A',
            'current_status' => $shipment->current_status,
            'eta' => $shipment->estimated_delivery ? $shipment->estimated_delivery->format('Y-m-d') : 'N/A',
            'service_type' => $shipment->service_type,
            'unsubscribe_url' => route('unsubscribe', ['token' => $subscription->unsubscribe_token]),
        ];

        // Render template
        $content = $this->templateManager->render($template, $variables);

        return [
            'subject' => $template['subject'] ?? "Shipment Update: {$shipment->tracking_number}",
            'content' => $content,
            'variables' => $variables,
            'locale' => $locale,
        ];
    }

    /**
     * Detect locale from destination (simple heuristic)
     */
    protected function detectLocale(string $destination): string
    {
        // Simple detection: if contains Thai characters, use Thai
        if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $destination)) {
            return 'th';
        }
        
        return 'en';
    }
}
