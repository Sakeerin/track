# Notification Services

This directory contains the notification system implementation for the parcel tracking system.

## Quick Start

### 1. Configure Environment Variables

Add to your `.env` file:

```env
# Email (using Laravel Mail)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password

# SMS Gateway
SMS_API_URL=https://api.sms-provider.com/send
SMS_API_KEY=your-api-key
SMS_SENDER_ID=TRACKING

# LINE Messaging API
LINE_CHANNEL_ACCESS_TOKEN=your-token
LINE_CHANNEL_SECRET=your-secret

# Webhook
WEBHOOK_TIMEOUT=10
WEBHOOK_RETRY_ATTEMPTS=3
WEBHOOK_SECRET=your-secret
```

### 2. Send Notifications

```php
use App\Services\Notification\NotificationService;
use App\Models\Event;

// Inject the service
$notificationService = app(NotificationService::class);

// Send notifications for an event
$event = Event::find($eventId);
$results = $notificationService->notifyForEvent($event);
```

### 3. Create Subscriptions

```php
use App\Models\Subscription;

Subscription::create([
    'shipment_id' => $shipment->id,
    'channel' => 'email',
    'destination' => 'customer@example.com',
    'events' => ['Delivered', 'OutForDelivery'],
    'active' => true,
    'consent_given' => true,
    'consent_ip' => request()->ip(),
    'consent_at' => now(),
]);
```

## Files

- **NotificationService.php** - Main orchestration service
- **NotificationChannelInterface.php** - Interface for all channels
- **EmailNotificationChannel.php** - Email delivery
- **SmsNotificationChannel.php** - SMS delivery
- **LineNotificationChannel.php** - LINE messaging delivery
- **WebhookNotificationChannel.php** - Webhook delivery
- **TemplateManager.php** - Template management and rendering

## Supported Channels

- **email** - HTML email notifications
- **sms** - SMS text messages (160 char limit)
- **line** - LINE messaging with rich cards
- **webhook** - JSON webhooks with HMAC signatures

## Supported Events

- Created
- PickedUp
- InTransit
- AtHub
- OutForDelivery
- Delivered
- DeliveryAttempted
- ExceptionRaised
- Returned

## API Endpoints

### Create Subscription
```
POST /api/subscriptions
{
  "tracking_number": "TH1234567890",
  "channel": "email",
  "destination": "customer@example.com",
  "events": ["Delivered", "OutForDelivery"],
  "consent_given": true
}
```

### Get Subscriptions
```
GET /api/subscriptions?tracking_number=TH1234567890
```

### Unsubscribe
```
GET /api/subscriptions/unsubscribe/{token}
```

## Testing

Run tests:
```bash
php artisan test --filter=NotificationServiceTest
```

Verify services:
```bash
php tests/verify_notification_services.php
```

## Documentation

See `backend/docs/notification-system.md` for complete documentation.
