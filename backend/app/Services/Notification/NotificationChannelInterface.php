<?php

namespace App\Services\Notification;

interface NotificationChannelInterface
{
    /**
     * Send notification through this channel
     *
     * @param string $destination The destination (email, phone, LINE ID, webhook URL)
     * @param array $data Notification data including subject, content, variables
     * @return array Result with success status and optional delivery tracking info
     */
    public function send(string $destination, array $data): array;

    /**
     * Verify delivery receipt if supported by channel
     *
     * @param string $messageId The message ID returned from send()
     * @return array Delivery status information
     */
    public function checkDeliveryStatus(string $messageId): array;
}
