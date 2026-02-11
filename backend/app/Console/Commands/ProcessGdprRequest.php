<?php

namespace App\Console\Commands;

use App\Services\Security\GdprDataService;
use Illuminate\Console\Command;

class ProcessGdprRequest extends Command
{
    protected $signature = 'gdpr:process {action : export|delete} {email : Data subject email}';

    protected $description = 'Process GDPR data export or deletion by email';

    public function handle(GdprDataService $gdprDataService): int
    {
        $action = strtolower((string) $this->argument('action'));
        $email = (string) $this->argument('email');

        if (!in_array($action, ['export', 'delete'], true)) {
            $this->error('Action must be one of: export, delete');
            return Command::INVALID;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');
            return Command::INVALID;
        }

        $result = $action === 'export'
            ? $gdprDataService->exportByEmail($email)
            : $gdprDataService->deleteByEmail($email);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
