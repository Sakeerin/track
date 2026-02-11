<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\SupportTicket;
use Illuminate\Console\Command;

class CleanupRetainedData extends Command
{
    protected $signature = 'data:cleanup {--force : Run cleanup without confirmation}';

    protected $description = 'Delete data that exceeds configured retention policies';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Delete expired retained data?')) {
            $this->info('Cleanup cancelled.');
            return Command::SUCCESS;
        }

        $auditCutoff = now()->subDays((int) config('security.retention.audit_logs_days', 365));
        $notificationCutoff = now()->subDays((int) config('security.retention.notification_logs_days', 180));
        $ticketCutoff = now()->subDays((int) config('security.retention.closed_tickets_days', 365));

        $deletedAuditLogs = AuditLog::query()
            ->where('created_at', '<', $auditCutoff)
            ->delete();

        $deletedNotificationLogs = NotificationLog::query()
            ->where('created_at', '<', $notificationCutoff)
            ->delete();

        $deletedSupportTickets = SupportTicket::query()
            ->whereIn('status', ['closed', 'resolved'])
            ->where('updated_at', '<', $ticketCutoff)
            ->delete();

        $this->info('Data cleanup completed.');
        $this->table(['Record Type', 'Deleted'], [
            ['audit_logs', $deletedAuditLogs],
            ['notification_logs', $deletedNotificationLogs],
            ['support_tickets', $deletedSupportTickets],
        ]);

        return Command::SUCCESS;
    }
}
