<?php

namespace App\Console\Commands;

use App\Jobs\WarmShipmentCacheJob;
use Illuminate\Console\Command;

class WarmShipmentCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:warm-shipments {--sync : Run synchronously instead of dispatching to queue}';

    /**
     * The console command description.
     */
    protected $description = 'Warm the cache for frequently accessed shipments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting shipment cache warming...');

        if ($this->option('sync')) {
            app(\App\Services\Tracking\TrackingService::class)->warmCache();
            $this->info('Cache warming completed synchronously.');
        } else {
            WarmShipmentCacheJob::dispatch();
            $this->info('Cache warming job dispatched to queue.');
        }

        return Command::SUCCESS;
    }
}
