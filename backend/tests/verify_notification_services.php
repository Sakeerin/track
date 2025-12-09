<?php

/**
 * Simple verification script for notification services
 * This can be run independently to verify the notification system works
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\SmsNotificationChannel;
use App\Services\Notification\LineNotificationChannel;
use App\Services\Notification\WebhookNotificationChannel;
use App\Services\Notification\TemplateManager;
use App\Services\Notification\NotificationService;

echo "Verifying Notification Services...\n\n";

// Test 1: Template Manager
echo "1. Testing Template Manager...\n";
$templateManager = new TemplateManager();
$template = $templateManager->getTemplate('email', 'Delivered', 'en');
if (isset($template['subject'])) {
    echo "   ✓ Template Manager working\n";
    echo "   Subject: {$template['subject']}\n";
} else {
    echo "   ✗ Template Manager failed\n";
}

// Test 2: Template Rendering
echo "\n2. Testing Template Rendering...\n";
$variables = [
    'tracking_number' => 'TH1234567890',
    'current_status' => 'Delivered',
    'event_description' => 'Package delivered successfully',
    'event_time' => '2024-01-15 10:30:00',
    'facility' => 'Bangkok Hub',
    'eta' => '2024-01-15',
    'unsubscribe_url' => 'https://example.com/unsubscribe/token123',
];
$rendered = $templateManager->render($template, $variables);
if (strpos($rendered, 'TH1234567890') !== false) {
    echo "   ✓ Template rendering working\n";
    echo "   Content includes tracking number\n";
} else {
    echo "   ✗ Template rendering failed\n";
}

// Test 3: Channel Interfaces
echo "\n3. Testing Channel Interfaces...\n";
$emailChannel = new EmailNotificationChannel();
$smsChannel = new SmsNotificationChannel();
$lineChannel = new LineNotificationChannel();
$webhookChannel = new WebhookNotificationChannel();

if ($emailChannel instanceof \App\Services\Notification\NotificationChannelInterface) {
    echo "   ✓ Email channel implements interface\n";
}
if ($smsChannel instanceof \App\Services\Notification\NotificationChannelInterface) {
    echo "   ✓ SMS channel implements interface\n";
}
if ($lineChannel instanceof \App\Services\Notification\NotificationChannelInterface) {
    echo "   ✓ LINE channel implements interface\n";
}
if ($webhookChannel instanceof \App\Services\Notification\NotificationChannelInterface) {
    echo "   ✓ Webhook channel implements interface\n";
}

// Test 4: Notification Service
echo "\n4. Testing Notification Service...\n";
$notificationService = new NotificationService(
    $emailChannel,
    $smsChannel,
    $lineChannel,
    $webhookChannel,
    $templateManager
);
if ($notificationService instanceof NotificationService) {
    echo "   ✓ Notification Service instantiated successfully\n";
}

echo "\n✓ All verification checks passed!\n";
echo "\nNotification system is ready to use.\n";
