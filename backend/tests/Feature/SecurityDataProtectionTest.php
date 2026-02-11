<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityDataProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_destination_is_encrypted_at_rest(): void
    {
        $shipment = Shipment::factory()->create();

        $subscription = Subscription::create([
            'shipment_id' => $shipment->id,
            'channel' => 'email',
            'destination' => 'customer@example.com',
            'events' => ['Delivered'],
            'consent_given' => true,
        ]);

        $rawDestination = DB::table('subscriptions')
            ->where('id', $subscription->id)
            ->value('destination');

        $destinationHash = DB::table('subscriptions')
            ->where('id', $subscription->id)
            ->value('destination_hash');

        $this->assertNotSame('customer@example.com', $rawDestination);
        $this->assertSame('customer@example.com', $subscription->fresh()->destination);
        $this->assertSame(hash('sha256', 'customer@example.com'), $destinationHash);
    }

    public function test_tracking_requires_recaptcha_when_enabled(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.enforce_in_testing' => true,
            'services.recaptcha.secret_key' => 'test-secret',
        ]);

        $response = $this->postJson('/api/tracking', [
            'tracking_numbers' => ['TH1234567890'],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'RECAPTCHA_REQUIRED',
            ]);
    }

    public function test_tracking_accepts_valid_recaptcha_token(): void
    {
        Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
        ]);

        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.enforce_in_testing' => true,
            'services.recaptcha.secret_key' => 'test-secret',
            'services.recaptcha.verify_url' => 'https://example.com/recaptcha/verify',
            'services.recaptcha.score_threshold' => 0.5,
        ]);

        Http::fake([
            'https://example.com/recaptcha/verify' => Http::response([
                'success' => true,
                'score' => 0.9,
            ], 200),
        ]);

        $response = $this->withHeader('X-Recaptcha-Token', 'valid-token')->postJson('/api/tracking', [
            'tracking_numbers' => ['TH1234567890'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/tracking/health');

        $response->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_cleanup_command_removes_expired_data(): void
    {
        config([
            'security.retention.audit_logs_days' => 365,
            'security.retention.notification_logs_days' => 180,
            'security.retention.closed_tickets_days' => 365,
        ]);

        $oldAudit = AuditLog::create([
            'action' => AuditLog::ACTION_LOGIN,
            'created_at' => now()->subDays(500),
        ]);

        $newAudit = AuditLog::create([
            'action' => AuditLog::ACTION_LOGIN,
            'created_at' => now()->subDays(10),
        ]);

        $oldNotification = NotificationLog::factory()->create([
            'created_at' => now()->subDays(250),
        ]);

        $newNotification = NotificationLog::factory()->create([
            'created_at' => now()->subDays(5),
        ]);

        $oldClosedTicket = SupportTicket::create([
            'ticket_number' => 'SUP-OLD-0001',
            'tracking_number' => null,
            'name' => 'Old User',
            'email' => 'old.user@example.com',
            'subject' => 'Old issue',
            'message' => 'This is an old closed ticket',
            'source' => 'public_web',
            'status' => 'closed',
            'created_at' => now()->subDays(500),
            'updated_at' => now()->subDays(500),
        ]);

        $openTicket = SupportTicket::create([
            'ticket_number' => 'SUP-OPEN-0001',
            'tracking_number' => null,
            'name' => 'Open User',
            'email' => 'open.user@example.com',
            'subject' => 'Open issue',
            'message' => 'This is an open ticket',
            'source' => 'public_web',
            'status' => 'open',
            'created_at' => now()->subDays(500),
            'updated_at' => now()->subDays(500),
        ]);

        $this->artisan('data:cleanup --force')->assertExitCode(0);

        $this->assertDatabaseMissing('audit_logs', ['id' => $oldAudit->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $newAudit->id]);

        $this->assertDatabaseMissing('notification_logs', ['id' => $oldNotification->id]);
        $this->assertDatabaseHas('notification_logs', ['id' => $newNotification->id]);

        $this->assertDatabaseMissing('support_tickets', ['id' => $oldClosedTicket->id]);
        $this->assertDatabaseHas('support_tickets', ['id' => $openTicket->id]);
    }
}
