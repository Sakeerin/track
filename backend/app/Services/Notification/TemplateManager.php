<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TemplateManager
{
    protected array $templates = [];
    protected string $templatePath;

    public function __construct()
    {
        $this->templatePath = resource_path('notification-templates');
        $this->loadTemplates();
    }

    /**
     * Load all notification templates
     */
    protected function loadTemplates(): void
    {
        // Define default templates
        $this->templates = [
            'email' => [
                'en' => [
                    'Created' => [
                        'subject' => 'Shipment Created: {{tracking_number}}',
                        'template' => 'email.created.en',
                    ],
                    'PickedUp' => [
                        'subject' => 'Package Picked Up: {{tracking_number}}',
                        'template' => 'email.picked_up.en',
                    ],
                    'InTransit' => [
                        'subject' => 'Package In Transit: {{tracking_number}}',
                        'template' => 'email.in_transit.en',
                    ],
                    'AtHub' => [
                        'subject' => 'Package at Hub: {{tracking_number}}',
                        'template' => 'email.at_hub.en',
                    ],
                    'OutForDelivery' => [
                        'subject' => 'Out for Delivery: {{tracking_number}}',
                        'template' => 'email.out_for_delivery.en',
                    ],
                    'Delivered' => [
                        'subject' => 'Package Delivered: {{tracking_number}}',
                        'template' => 'email.delivered.en',
                    ],
                    'DeliveryAttempted' => [
                        'subject' => 'Delivery Attempted: {{tracking_number}}',
                        'template' => 'email.delivery_attempted.en',
                    ],
                    'ExceptionRaised' => [
                        'subject' => 'Shipment Exception: {{tracking_number}}',
                        'template' => 'email.exception.en',
                    ],
                    'Returned' => [
                        'subject' => 'Package Returned: {{tracking_number}}',
                        'template' => 'email.returned.en',
                    ],
                ],
                'th' => [
                    'Created' => [
                        'subject' => 'สร้างพัสดุแล้ว: {{tracking_number}}',
                        'template' => 'email.created.th',
                    ],
                    'PickedUp' => [
                        'subject' => 'รับพัสดุแล้ว: {{tracking_number}}',
                        'template' => 'email.picked_up.th',
                    ],
                    'InTransit' => [
                        'subject' => 'พัสดุกำลังขนส่ง: {{tracking_number}}',
                        'template' => 'email.in_transit.th',
                    ],
                    'AtHub' => [
                        'subject' => 'พัสดุถึงศูนย์กระจายสินค้า: {{tracking_number}}',
                        'template' => 'email.at_hub.th',
                    ],
                    'OutForDelivery' => [
                        'subject' => 'กำลังจัดส่ง: {{tracking_number}}',
                        'template' => 'email.out_for_delivery.th',
                    ],
                    'Delivered' => [
                        'subject' => 'จัดส่งสำเร็จ: {{tracking_number}}',
                        'template' => 'email.delivered.th',
                    ],
                    'DeliveryAttempted' => [
                        'subject' => 'พยายามจัดส่ง: {{tracking_number}}',
                        'template' => 'email.delivery_attempted.th',
                    ],
                    'ExceptionRaised' => [
                        'subject' => 'พัสดุมีปัญหา: {{tracking_number}}',
                        'template' => 'email.exception.th',
                    ],
                    'Returned' => [
                        'subject' => 'พัสดุถูกส่งคืน: {{tracking_number}}',
                        'template' => 'email.returned.th',
                    ],
                ],
            ],
            'sms' => [
                'en' => [
                    'Created' => [
                        'template' => 'sms.created.en',
                    ],
                    'PickedUp' => [
                        'template' => 'sms.picked_up.en',
                    ],
                    'OutForDelivery' => [
                        'template' => 'sms.out_for_delivery.en',
                    ],
                    'Delivered' => [
                        'template' => 'sms.delivered.en',
                    ],
                    'ExceptionRaised' => [
                        'template' => 'sms.exception.en',
                    ],
                ],
                'th' => [
                    'Created' => [
                        'template' => 'sms.created.th',
                    ],
                    'PickedUp' => [
                        'template' => 'sms.picked_up.th',
                    ],
                    'OutForDelivery' => [
                        'template' => 'sms.out_for_delivery.th',
                    ],
                    'Delivered' => [
                        'template' => 'sms.delivered.th',
                    ],
                    'ExceptionRaised' => [
                        'template' => 'sms.exception.th',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get template for specific channel, event, and locale
     */
    public function getTemplate(string $channel, string $eventCode, string $locale = 'en'): array
    {
        // Fallback to English if Thai not available
        if (!isset($this->templates[$channel][$locale][$eventCode])) {
            $locale = 'en';
        }

        // Get template definition
        $templateDef = $this->templates[$channel][$locale][$eventCode] ?? null;

        if (!$templateDef) {
            // Return default template
            return $this->getDefaultTemplate($channel, $locale);
        }

        return $templateDef;
    }

    /**
     * Render template with variables
     */
    public function render(array $template, array $variables): string
    {
        $templateName = $template['template'] ?? null;

        if (!$templateName) {
            return $this->renderInlineTemplate($template, $variables);
        }

        // Try to load template file
        $templateFile = $this->templatePath . '/' . $templateName . '.blade.php';

        if (File::exists($templateFile)) {
            return view('notification-templates.' . $templateName, $variables)->render();
        }

        // Fallback to inline rendering
        return $this->renderInlineTemplate($template, $variables);
    }

    /**
     * Render template inline (without blade file)
     */
    protected function renderInlineTemplate(array $template, array $variables): string
    {
        $content = $this->getDefaultContent($template, $variables);

        // Replace variables in content
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Get default template content
     */
    protected function getDefaultContent(array $template, array $variables): string
    {
        $trackingNumber = $variables['tracking_number'] ?? 'N/A';
        $eventDescription = $variables['event_description'] ?? 'Status updated';
        $eventTime = $variables['event_time'] ?? 'N/A';
        $facility = $variables['facility'] ?? 'N/A';
        $currentStatus = $variables['current_status'] ?? 'N/A';
        $eta = $variables['eta'] ?? 'N/A';
        $unsubscribeUrl = $variables['unsubscribe_url'] ?? '#';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .info-row { margin: 10px 0; }
        .label { font-weight: bold; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .unsubscribe { color: #999; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Shipment Update</h1>
        </div>
        <div class="content">
            <div class="info-row">
                <span class="label">Tracking Number:</span> {$trackingNumber}
            </div>
            <div class="info-row">
                <span class="label">Status:</span> {$currentStatus}
            </div>
            <div class="info-row">
                <span class="label">Event:</span> {$eventDescription}
            </div>
            <div class="info-row">
                <span class="label">Time:</span> {$eventTime}
            </div>
            <div class="info-row">
                <span class="label">Location:</span> {$facility}
            </div>
            <div class="info-row">
                <span class="label">Estimated Delivery:</span> {$eta}
            </div>
        </div>
        <div class="footer">
            <p>Thank you for using our tracking service.</p>
            <p><a href="{$unsubscribeUrl}" class="unsubscribe">Unsubscribe from notifications</a></p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get default template when specific template not found
     */
    protected function getDefaultTemplate(string $channel, string $locale): array
    {
        return [
            'subject' => 'Shipment Update: {{tracking_number}}',
            'template' => null,
        ];
    }

    /**
     * Preview template with sample data
     */
    public function preview(string $channel, string $eventCode, string $locale = 'en'): string
    {
        $template = $this->getTemplate($channel, $eventCode, $locale);
        
        $sampleVariables = [
            'tracking_number' => 'TH1234567890',
            'event_code' => $eventCode,
            'event_description' => 'Package has been picked up',
            'event_time' => now()->format('Y-m-d H:i:s'),
            'facility' => 'Bangkok Distribution Center',
            'location' => 'Bangkok',
            'current_status' => 'InTransit',
            'eta' => now()->addDays(2)->format('Y-m-d'),
            'service_type' => 'Standard',
            'unsubscribe_url' => 'https://example.com/unsubscribe/token123',
        ];

        return $this->render($template, $sampleVariables);
    }
}
