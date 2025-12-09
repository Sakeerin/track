<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Event $event;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('Processing notification job', [
            'event_id' => $this->event->id,
            'event_code' => $this->event->event_code,
            'shipment_id' => $this->event->shipment_id,
        ]);

        try {
            $results = $notificationService->notifyForEvent($this->event);

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failureCount = count($results) - $successCount;

            Log::info('Notification job completed', [
                'event_id' => $this->event->id,
                'total_notifications' => count($results),
                'successful' => $successCount,
                'failed' => $failureCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Notification job failed', [
                'event_id' => $this->event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job permanently failed', [
            'event_id' => $this->event->id,
            'event_code' => $this->event->event_code,
            'error' => $exception->getMessage(),
        ]);
    }
}
