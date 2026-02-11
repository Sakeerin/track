<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeploymentMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_compose_config_enables_wal_archiving_and_backups(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));

        $this->assertNotFalse($compose);
        $this->assertStringContainsString('archive_timeout=900', $compose);
        $this->assertStringContainsString('archive_command=test ! -f /backups/wal/%f && cp %p /backups/wal/%f', $compose);
        $this->assertStringContainsString('db-backup:', $compose);
        $this->assertStringContainsString('./docker/backup.sh:/scripts/backup.sh:ro', $compose);
        $this->assertStringContainsString('./docker/restore.sh:/scripts/restore.sh:ro', $compose);
    }

    public function test_backup_and_restore_scripts_exist_with_expected_commands(): void
    {
        $backupScript = file_get_contents(base_path('../docker/backup.sh'));
        $restoreScript = file_get_contents(base_path('../docker/restore.sh'));
        $verifyScript = file_get_contents(base_path('../docker/verify-backup.sh'));

        $this->assertNotFalse($backupScript);
        $this->assertNotFalse($restoreScript);
        $this->assertNotFalse($verifyScript);

        $this->assertStringContainsString('pg_dump', $backupScript);
        $this->assertStringContainsString('BACKUP_RETENTION_DAYS', $backupScript);
        $this->assertStringContainsString('pg_restore', $restoreScript);
        $this->assertStringContainsString('pg_restore', $verifyScript);
        $this->assertStringContainsString('createdb', $verifyScript);
        $this->assertStringContainsString('dropdb', $verifyScript);
    }

    public function test_monitoring_stack_configuration_exists(): void
    {
        $prometheusConfig = file_get_contents(base_path('../docker/prometheus.yml'));
        $alertRules = file_get_contents(base_path('../docker/alert-rules.yml'));
        $promtailConfig = file_get_contents(base_path('../docker/promtail.yml'));

        $this->assertNotFalse($prometheusConfig);
        $this->assertNotFalse($alertRules);
        $this->assertNotFalse($promtailConfig);

        $this->assertStringContainsString('blackbox-http', $prometheusConfig);
        $this->assertStringContainsString('PublicApiDown', $alertRules);
        $this->assertStringContainsString('HighApiLatency', $alertRules);
        $this->assertStringContainsString('docker_sd_configs', $promtailConfig);
    }

    public function test_tracking_endpoint_serves_stale_cache_on_database_failure(): void
    {
        $trackingNumber = 'TH1234567890';
        $staleKey = config('cache.shipment.stale_prefix', 'shipment_stale:') . $trackingNumber;

        Cache::put($staleKey, [
            'tracking_number' => $trackingNumber,
            'current_status' => 'in_transit',
            'status_label' => 'In Transit',
            'events' => [],
        ], 3600);

        config(['database.default' => 'missing']);

        $response = $this->getJson('/api/tracking/' . $trackingNumber);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tracking_number', $trackingNumber)
            ->assertJsonPath('data.current_status', 'in_transit');
    }

    public function test_logging_configuration_supports_structured_and_alert_channels(): void
    {
        $this->assertSame('stack', config('logging.default'));
        $this->assertContains('daily', config('logging.channels.stack.channels', []));
        $this->assertContains('stderr', config('logging.channels.stack.channels', []));
        $this->assertEquals('slack', config('logging.channels.slack.driver'));
    }
}
