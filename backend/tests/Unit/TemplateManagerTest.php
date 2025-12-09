<?php

namespace Tests\Unit;

use App\Services\Notification\TemplateManager;
use Tests\TestCase;

class TemplateManagerTest extends TestCase
{
    protected TemplateManager $templateManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateManager = new TemplateManager();
    }

    public function test_get_email_template_in_english()
    {
        $template = $this->templateManager->getTemplate('email', 'Delivered', 'en');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('subject', $template);
        $this->assertStringContainsString('Delivered', $template['subject']);
    }

    public function test_get_email_template_in_thai()
    {
        $template = $this->templateManager->getTemplate('email', 'Delivered', 'th');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('subject', $template);
        $this->assertStringContainsString('จัดส่งสำเร็จ', $template['subject']);
    }

    public function test_get_sms_template()
    {
        $template = $this->templateManager->getTemplate('sms', 'Delivered', 'en');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('template', $template);
    }

    public function test_fallback_to_english_when_thai_not_available()
    {
        $template = $this->templateManager->getTemplate('email', 'NonExistentEvent', 'th');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('subject', $template);
    }

    public function test_render_template_with_variables()
    {
        $template = [
            'subject' => 'Test Subject',
            'template' => null,
        ];

        $variables = [
            'tracking_number' => 'TH1234567890',
            'current_status' => 'Delivered',
            'event_description' => 'Package delivered successfully',
            'event_time' => '2024-01-15 10:30:00',
            'facility' => 'Bangkok Hub',
            'eta' => '2024-01-15',
            'unsubscribe_url' => 'https://example.com/unsubscribe/token123',
        ];

        $rendered = $this->templateManager->render($template, $variables);

        $this->assertStringContainsString('TH1234567890', $rendered);
        $this->assertStringContainsString('Delivered', $rendered);
        $this->assertStringContainsString('Bangkok Hub', $rendered);
        $this->assertStringContainsString('unsubscribe', $rendered);
    }

    public function test_preview_template_with_sample_data()
    {
        $preview = $this->templateManager->preview('email', 'Delivered', 'en');

        $this->assertIsString($preview);
        $this->assertStringContainsString('TH1234567890', $preview);
        $this->assertStringContainsString('Shipment Update', $preview);
    }

    public function test_variable_substitution_in_subject()
    {
        $template = [
            'subject' => 'Package {{tracking_number}} is {{current_status}}',
            'template' => null,
        ];

        $variables = [
            'tracking_number' => 'TH9999999999',
            'current_status' => 'InTransit',
            'event_description' => 'Test',
            'event_time' => '2024-01-15 10:30:00',
            'facility' => 'Test Hub',
            'eta' => '2024-01-16',
            'unsubscribe_url' => 'https://example.com/unsubscribe/token',
        ];

        $rendered = $this->templateManager->render($template, $variables);

        $this->assertStringContainsString('TH9999999999', $rendered);
        $this->assertStringContainsString('InTransit', $rendered);
    }
}
