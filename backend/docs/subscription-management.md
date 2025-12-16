# Subscription Management System

## Overview

The subscription management system provides comprehensive notification subscription capabilities with consent tracking, throttling, analytics, and delivery tracking. This system ensures users receive timely updates about their shipments while respecting their preferences and preventing notification fatigue.

## Features

### 1. Subscription API with Consent Tracking

All subscriptions require explicit user consent before notifications can be sent. The system tracks:
- Consent status (given/not given)
- Consent IP address
- Consent timestamp
- Unsubscribe token for easy opt-out

### 2. Notification Preference Management

Users can customize which events trigger notifications:
- Created
- PickedUp
- InTransit
- AtHub
- OutForDelivery
- Delivered
- DeliveryAttempted
- ExceptionRaised
- ExceptionResolved
- Customs
- Returned

### 3. Throttling Logic

To prevent notification fatigue, the system implements intelligent throttling:
- **Standard Events**: Maximum 1 notification per 2 hours
- **Critical Events**: Never throttled (DeliveryAttempted, Delivered, ExceptionRaised, Returned)
- Throttling is tracked per subscription

### 4. Subscription Analytics

Comprehensive statistics for each subscription:
- Total notifications sent
- Total notifications failed
- Total notifications throttled
- Last notification timestamp
- Delivery rate percentage
- Recent notification history (last 20)

### 5. Delivery Tracking

Track notification delivery across all channels:
- Delivery status by channel (email, SMS, LINE, webhook)
- Time-based filtering (date range)
- Aggregate statistics
- Per-channel breakdown

## API Endpoints

### Create Subscription

```http
POST /api/subscriptions
Content-Type: application/json

{
  "tracking_number": "TH1234567890",
  "channel": "email",
  "destination": "user@example.com",
  "events": ["PickedUp", "Delivered"],
  "consent_given": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Subscription created successfully",
  "subscription": {
    "id": "uuid",
    "shipment_id": "uuid",
    "channel": "email",
    "destination": "user@example.com",
    "events": ["PickedUp", "Delivered"],
    "active": true,
    "consent_given": true,
    "unsubscribe_token": "..."
  },
  "unsubscribe_url": "https://api.example.com/api/subscriptions/unsubscribe/{token}"
}
```

### Get Subscription Analytics

```http
GET /api/subscriptions/{id}/analytics
```

**Response:**
```json
{
  "success": true,
  "subscription": {...},
  "statistics": {
    "total_sent": 15,
    "total_failed": 2,
    "total_throttled": 5,
    "last_sent_at": "2024-12-09 10:30:00",
    "delivery_rate": 85.5
  },
  "recent_notifications": [...]
}
```

### Update Notification Preferences

```http
PUT /api/subscriptions/{id}/preferences
Content-Type: application/json

{
  "events": ["Delivered", "ExceptionRaised"]
}
```

### Get Delivery Tracking

```http
GET /api/subscriptions/delivery-tracking?tracking_number=TH1234567890&start_date=2024-12-01&end_date=2024-12-09
```

**Response:**
```json
{
  "success": true,
  "tracking_number": "TH1234567890",
  "statistics": {
    "total_sent": 20,
    "total_failed": 3,
    "total_throttled": 7,
    "delivery_rate": 90.0,
    "by_channel": {
      "email": {
        "total": 15,
        "sent": 13,
        "failed": 2,
        "throttled": 0
      },
      "sms": {
        "total": 5,
        "sent": 4,
        "failed": 1,
        "throttled": 0
      }
    }
  },
  "logs": [...]
}
```

### Mark Notification as Delivered

```http
POST /api/notifications/{logId}/delivered
Content-Type: application/json

{
  "delivered_at": "2024-12-09 10:35:00"
}
```

### Unsubscribe

```http
GET /api/subscriptions/unsubscribe/{token}
```

## Database Schema

### subscriptions Table

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| shipment_id | UUID | Foreign key to shipments |
| channel | VARCHAR(20) | Notification channel (email, sms, line, webhook) |
| destination | VARCHAR(255) | Destination address/number |
| events | JSON | Array of event codes to notify |
| active | BOOLEAN | Subscription active status |
| consent_given | BOOLEAN | User consent status |
| consent_ip | VARCHAR(45) | IP address when consent given |
| consent_at | TIMESTAMP | Timestamp when consent given |
| unsubscribe_token | VARCHAR(100) | Unique token for unsubscribe |

### notification_logs Table

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| subscription_id | UUID | Foreign key to subscriptions |
| event_id | UUID | Foreign key to events |
| channel | VARCHAR(20) | Notification channel |
| destination | VARCHAR(255) | Destination address/number |
| status | VARCHAR(20) | Status (sent, failed, throttled) |
| error_message | TEXT | Error message if failed |
| sent_at | TIMESTAMP | When notification was sent |
| delivered_at | TIMESTAMP | When notification was delivered |
| metadata | JSON | Additional metadata |

## Throttling Algorithm

```php
public function shouldThrottle(string $eventCode): bool
{
    // Critical events are never throttled
    $criticalEvents = ['DeliveryAttempted', 'Delivered', 'ExceptionRaised', 'Returned'];
    if (in_array($eventCode, $criticalEvents)) {
        return false;
    }

    // Check last successful notification
    $lastNotification = $this->notificationLogs()
        ->where('status', 'sent')
        ->orderBy('sent_at', 'desc')
        ->first();

    if (!$lastNotification) {
        return false;
    }

    // Throttle if last notification was within 2 hours
    $twoHoursAgo = now()->subHours(2);
    return $lastNotification->sent_at > $twoHoursAgo;
}
```

## Usage Examples

### Creating a Subscription with Consent

```php
$subscription = Subscription::create([
    'shipment_id' => $shipment->id,
    'channel' => 'email',
    'destination' => 'user@example.com',
    'events' => ['PickedUp', 'Delivered'],
    'active' => true,
    'consent_given' => true,
    'consent_ip' => request()->ip(),
    'consent_at' => now(),
]);
```

### Checking if Notification Should Be Sent

```php
if ($subscription->shouldNotifyForEvent($eventCode) && !$subscription->shouldThrottle($eventCode)) {
    // Send notification
    $notificationService->sendNotification($subscription, $event);
}
```

### Getting Subscription Statistics

```php
$stats = $subscription->getStatistics();
// Returns: ['total_sent' => 10, 'total_failed' => 1, 'total_throttled' => 3, ...]
```

## Testing

The subscription management system includes comprehensive tests:

### Unit Tests
- `SubscriptionThrottlingTest`: Tests throttling logic
- `NotificationServiceThrottlingTest`: Tests service integration

### Feature Tests
- `SubscriptionManagementTest`: Tests API endpoints

Run tests:
```bash
php artisan test --filter=Subscription
```

## Requirements Validation

This implementation satisfies the following requirements:

- **Requirement 4.1**: Subscription modal with consent tracking ✓
- **Requirement 8.6**: Throttling (max 1 per 2h unless critical) ✓
- **Requirement 8.7**: Notification templates and consent management ✓

## Future Enhancements

1. **Delivery Receipt Integration**: Webhook callbacks from email/SMS providers
2. **A/B Testing**: Test different notification templates
3. **Smart Throttling**: ML-based throttling based on user engagement
4. **Batch Notifications**: Group multiple events into digest notifications
5. **Channel Preferences**: Allow users to set different preferences per channel
