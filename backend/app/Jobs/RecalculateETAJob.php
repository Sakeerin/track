<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\ETA\ETACalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateETAJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $shipmentId,
        private ?string $reason = null
    ) {
        $this->onQueue('eta');
    }

    /**
     * Execute the job.
     */
    public function handle(ETACalculationService $etaService): void
    {
        try {
            $shipment = Shipment::find($this->shipmentId);
            
            if (!$shipment) {
                Log::warning('ETA recalculation failed: Shipment not found', [
                    'shipment_id' => $this->shipmentId,
                ]);
                return;
            }

            $oldEta = $shipment->estimated_delivery;
            $newEta = $etaService->recalculateETA($shipment);

            if ($newEta) {
                Log::info('ETA recalculated successfully', [
                    'shipment_id' => $this->shipmentId,
                    'tracking_number' => $shipment->tracking_number,
                    'old_eta' => $oldEta?->toISOString(),
                    'new_eta' => $newEta->format('c'),
                    'reason' => $this->reason,
                ]);
            } else {
                Log::warning('ETA recalculation resulted in null ETA', [
                    'shipment_id' => $this->shipmentId,
                    'tracking_number' => $shipment->tracking_number,
                    'reason' => $this->reason,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ETA recalculation failed with exception', [
                'shipment_id' => $this->shipmentId,
                'reason' => $this->reason,
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
        Log::error('ETA recalculation job failed permanently', [
            'shipment_id' => $this->shipmentId,
            'reason' => $this->reason,
            'error' => $exception->getMessage(),
        ]);
    }
}