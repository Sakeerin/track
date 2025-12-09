<?php

/**
 * Notification System Integration Examples
 * 
 * This file demonstrates how to integrate the notification system
 * into your application workflows.
 */

// Example 1: Send notifications when an event is processed
use App\Jobs\SendNotificationJob;
use App\Models\Event;

function processEventWithNotification($eventData)
{
    // Process the event (create/update shipment, etc.)
    $event = Event::create($eventData);
    
    // Dispatch notification job
    SendNotificationJob::dispatch($event)->onQueue('notifications');
    
    return $event;
}

// Example 2: Create a subscription via API
use App\Models\Shipment;
use App\Models\Subscription;

function createSubscriptionForCustomer($trackingNumber, $email, $events)
{
    $shipment = Shipment::where('tracking_number', $trackingNumber)->firstOrFail();
    
    $subscription = Subscription::create([
        'shipment_id' => $shipment->id,
        'channel' => 'email',
        'destination' => $email,
        'events' => $events, // e.g., ['Delivered', 'OutForDelivery']
        'active' => true,
        'consent_given' => true,
        'consent_ip' => request()->ip(),
        'consent_at' => now(),
    ]);
    
    return [
        'subscription' => $subscription,
        'unsubscribe_url' => route('unsubscribe', ['token' => $subscription->unsubscribe_token]),
    ];
}

// Example 3: Send immediate notification (bypass queue)
use App\Services\Notification\NotificationService;

function sendImmediateNotification($eventId)
{
    $notificationService = app(NotificationService::class);
    $event = Event::findOrFail($eventId);
    
    $results = $notificationService->notifyForEvent($event);
    
    return $results;
}

// Example 4: Preview notification template
use App\Services\Notification\TemplateManager;

function previewNotificationTemplate($channel, $eventCode, $locale = 'en')
{
    $templateManager = app(TemplateManager::class);
    
    $preview = $templateManager->preview($channel, $eventCode, $locale);
    
    return $preview;
}

// Example 5: Check subscription status
function getActiveSubscriptions($trackingNumber)
{
    $shipment = Shipment::where('tracking_number', $trackingNumber)->firstOrFail();
    
    $subscriptions = $shipment->subscriptions()
        ->active()
        ->withConsent()
        ->get();
    
    return $subscriptions;
}

// Example 6: Bulk unsubscribe
function unsubscribeAllForShipment($trackingNumber)
{
    $shipment = Shipment::where('tracking_number', $trackingNumber)->firstOrFail();
    
    $count = $shipment->subscriptions()
        ->active()
        ->update(['active' => false]);
    
    return $count;
}

// Example 7: Send test notification
function sendTestNotification($destination, $channel = 'email')
{
    $notificationService = app(NotificationService::class);
    
    // Create a test subscription
    $testSubscription = new Subscription([
        'channel' => $channel,
        'destination' => $destination,
        'events' => ['Delivered'],
        'active' => true,
        'consent_given' => true,
    ]);
    
    // Create a test event
    $testEvent = Event::factory()->make([
        'event_code' => 'Delivered',
        'description' => 'Test notification',
    ]);
    
    $result = $notificationService->sendNotification($testSubscription, $testEvent);
    
    return $result;
}

// Example 8: Get notification statistics
function getNotificationStats($shipmentId)
{
    $shipment = Shipment::findOrFail($shipmentId);
    
    $stats = [
        'total_subscriptions' => $shipment->subscriptions()->count(),
        'active_subscriptions' => $shipment->subscriptions()->active()->count(),
        'subscriptions_by_channel' => $shipment->subscriptions()
            ->active()
            ->groupBy('channel')
            ->map(function ($group) {
                return $group->count();
            }),
    ];
    
    return $stats;
}

// Example 9: Retry failed notification
function retryFailedNotification($eventId)
{
    $event = Event::findOrFail($eventId);
    
    // Re-dispatch the notification job
    SendNotificationJob::dispatch($event)
        ->onQueue('notifications')
        ->delay(now()->addMinutes(5)); // Delay retry by 5 minutes
    
    return true;
}

// Example 10: Custom notification for specific event
function sendCustomNotification($shipmentId, $message, $channels = ['email', 'sms'])
{
    $shipment = Shipment::findOrFail($shipmentId);
    
    $subscriptions = $shipment->subscriptions()
        ->active()
        ->withConsent()
        ->whereIn('channel', $channels)
        ->get();
    
    $notificationService = app(NotificationService::class);
    $results = [];
    
    foreach ($subscriptions as $subscription) {
        // Create a custom event for this notification
        $customEvent = new Event([
            'shipment_id' => $shipment->id,
            'event_code' => 'Custom',
            'description' => $message,
            'event_time' => now(),
        ]);
        
        $result = $notificationService->sendNotification($subscription, $customEvent);
        $results[] = $result;
    }
    
    return $results;
}
