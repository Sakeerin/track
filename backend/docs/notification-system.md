# Notification System Documentation

## Overview

The notification system provides multi-channel notification delivery for shipment updates. It supports Email, SMS, LINE messaging, and Webhook notifications with template management and variable substitution.

## Architecture

### Components

1. **NotificationService** - Main service that orchestrates notification delivery
2. **NotificationChannelInterface** - Interface that all channels must implement
3. **Channel Implementations**:
   - EmailNotificationChannel
   - SmsNotificationChannel
   - LineNotificationChannel
   - WebhookNotificationChannel
4. **TemplateManager** - Manages notification templates with Thai/English support

### Service Registration

The notification services are registered in `NotificationServiceProvider` and automatically loaded by Laravel.

## Usage

### Basic Usage

```php
use App\Services\Notification\NotificationService;
use App\Models\Event;

// Inject the service
public function __construct(NotificationService $notificationService)
{
    $this->notificationService = $notificationService;
}

// Send notifications for an event
$event = Event::find($eventId);
$results = $this->notificationService->notifyForEvent($event);
```

### Manual Notification

```php
use App\Models\Subscription;
use App\Models\Event;

$subscription = Subscription::find($subscriptionId);
$event = Event::find($eventId);

$result = $this->notificationService->sendNotification($subscription, $event);

if ($result['success']) {
    echo "Notification sent successfully";
} else {
    echo "Failed: " . $result['error'];
}
```

## Channels

### Email Channel

Sends HTML email notifications using Laravel's Mail facade.

**Configuration** (in `.env`):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@example.com
```

### SMS Channel

Sends SMS notifications through a configured SMS gateway.

**Configuration** (in `.env`):
```env
SMS_API_URL=https://api.sms-provider.com/send
SMS_STATUS_URL=https://api.sms-provider.com/status
SMS_API_KEY=your-api-key
SMS_SENDER_ID=TRACKING
```

**Features**:
- Automatic message truncation to 160 characters
- Delivery status tracking
- HTML tag stripping

### LINE Channel

Sends notifications through LINE Messaging API with rich formatting.

**Configuration** (in `.env`):
```env
LINE_CHANNEL_ACCESS_TOKEN=your-channel-access-token
LINE_CHANNEL_SECRET=your-channel-secret
LINE_API_URL=https://api.line.me/v2/bot/message/push
```

**Features**:
- Flex message support for rich formatting
- Automatic fallback to plain text
- Shipment detail cards

### Webhook Channel

Sends JSON payloads to external webhook URLs.

**Configuration** (in `.env`):
```env
WEBHOOK_TIMEOUT=10
WEBHOOK_RETRY_ATTEMPTS=3
WEBHOOK_RETRY_DELAY=1000
WEBHOOK_SECRET=your-webhook-secret
```

**Features**:
- Exponential backoff retry logic
- HMAC signature for authentication
- Configurable timeout and retry attempts

## Template Management

### Template Structure

Templates are organized by:
- Channel (email, sms, line, webhook)
- Locale (en, th)
- Event Code (Created, PickedUp, Delivered, etc.)

### Available Variables

All templates have access to these variables:
- `tracking_number` - Shipment tracking number
- `event_code` - Event code (e.g., "Delivered")
- `event_description` - Human-readable event description
- `event_time` - Event timestamp
- `facility` - Facility name
- `location` - Location name
- `current_status` - Current shipment status
- `eta` - Estimated delivery date
- `service_type` - Service type (Standard, Express, etc.)
- `unsubscribe_url` - Unsubscribe link

### Supported Event Codes

- `Created` - Shipment created
- `PickedUp` - Package picked up
- `InTransit` - Package in transit
- `AtHub` - Package at distribution hub
- `OutForDelivery` - Out for delivery
- `Delivered` - Package delivered
- `DeliveryAttempted` - Delivery attempted
- `ExceptionRaised` - Exception occurred
- `Returned` - Package returned

### Template Preview

```php
use App\Services\Notification\TemplateManager;

$templateManager = app(TemplateManager::class);

// Preview a template with sample data
$preview = $templateManager->preview('email', 'Delivered', 'en');
echo $preview;
```

## Subscription Management

### Creating Subscriptions

```php
use App\Models\Subscription;

$subscription = Subscription::create([
    'shipment_id' => $shipment->id,
    'channel' => 'email',
    'destination' => 'customer@example.com',
    'events' => ['PickedUp', 'OutForDelivery', 'Delivered'],
    'active' => true,
    'consent_given' => true,
    'consent_ip' => request()->ip(),
    'consent_at' => now(),
]);
```

### Unsubscribe

Users can unsubscribe using the token-based URL:
```
https://your-domain.com/unsubscribe/{token}
```

Programmatic unsubscribe:
```php
$success = Subscription::unsubscribeByToken($token);
```

## Throttling

The notification system implements throttling to prevent notification spam:

- **Default**: Maximum 1 notification per 2 hours
- **Critical Events**: Not throttled (Delivered, DeliveryAttempted, ExceptionRaised, Returned)

## Error Handling

All channels implement graceful error handling:

```php
$result = $notificationService->sendNotification($subscription, $event);

if (!$result['success']) {
    Log::error('Notification failed', [
        'subscription_id' => $subscription->id,
        'error' => $result['error'],
    ]);
}
```

## Testing

### Unit Tests

Run notification service tests:
```bash
php artisan test --filter=NotificationServiceTest
php artisan test --filter=EmailNotificationChannelTest
php artisan test --filter=TemplateManagerTest
```

### Manual Testing

Use the verification script:
```bash
php tests/verify_notification_services.php
```

## Extending the System

### Adding a New Channel

1. Create a new channel class implementing `NotificationChannelInterface`:

```php
namespace App\Services\Notification;

class CustomNotificationChannel implements NotificationChannelInterface
{
    public function send(string $destination, array $data): array
    {
        // Implementation
    }

    public function checkDeliveryStatus(string $messageId): array
    {
        // Implementation
    }
}
```

2. Register in `NotificationServiceProvider`:

```php
$this->app->singleton(CustomNotificationChannel::class);
```

3. Update `NotificationService::getChannel()` to include the new channel.

### Adding New Templates

Add templates to `TemplateManager::loadTemplates()`:

```php
'custom_event' => [
    'subject' => 'Custom Event: {{tracking_number}}',
    'template' => 'email.custom_event.en',
],
```

## Best Practices

1. **Always check consent** before sending notifications
2. **Use appropriate channels** for different notification types
3. **Implement retry logic** for transient failures
4. **Log all notification attempts** for debugging
5. **Respect throttling limits** to avoid spam
6. **Provide unsubscribe links** in all notifications
7. **Test templates** in both languages before deployment

## Troubleshooting

### Notifications Not Sending

1. Check subscription is active and has consent
2. Verify event code is in subscription's event list
3. Check channel configuration in `.env`
4. Review application logs for errors

### Template Issues

1. Verify template exists for channel/locale/event combination
2. Check all required variables are provided
3. Test template rendering with preview function

### Delivery Failures

1. Check external service credentials
2. Verify network connectivity
3. Review retry attempts and backoff settings
4. Check rate limits on external services
