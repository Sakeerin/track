<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Shipment;
use App\Services\Ingestion\EventNormalizationService;
use App\Services\Ingestion\EventOrderingService;
use App\Services\Ingestion\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // seconds
    public $timeout = 120; // 2 minutes

    private array $eventData;

    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
    }

    /**
     * Execute the job.
     */
    public function handle(
        EventNormalizationService $normalizationService,
        EventOrderingService $orderingService,
        GeocodingService $geocodingService
    ): void
    {
        try {
            DB::beginTransaction();

            Log::info('Processing event', [
                'tracking_number' => $this->eventData['tracking_number'],
                'event_code' => $this->eventData['event_code'],
                'idempotency_key' => $this->eventData['idempotency_key']
            ]);

            // Check for duplicate again (race condition protection)
            $existingEvent = Event::where('idempotency_key', $this->eventData['idempotency_key'])->first();
            if ($existingEvent) {
                Log::info('Duplicate event detected during processing, skipping', [
                    'idempotency_key' => $this->eventData['idempotency_key']
                ]);
                DB::rollBack();
                return;
            }

            // Normalize event data (partner codes to canonical codes)
            $normalizedData = $normalizationService->normalizeEvent($this->eventData);

            // Enhance with geocoding if needed
            if (!empty($normalizedData['facility_code']) && empty($normalizedData['facility_id'])) {
                $locationData = $geocodingService->resolveFacilityLocation(
                    $normalizedData['facility_code'],
                    $normalizedData['location']
                );
                
                if ($locationData) {
                    $normalizedData['facility_id'] = $locationData['facility_id'] ?? null;
                    $normalizedData['location'] = $normalizedData['location'] ?? $locationData['address'];
                }
            }

            // Find or create shipment
            $shipment = $this->findOrCreateShipment($normalizedData);

            // Create event record
            $event = $this->createEvent($normalizedData, $shipment);

            // Process event ordering and update shipment status
            $orderingResult = $orderingService->processEventOrdering($shipment, $event);

            DB::commit();

            Log::info('Event processed successfully', [
                'event_id' => $event->id,
                'tracking_number' => $shipment->tracking_number,
                'event_code' => $event->event_code,
                'shipment_status' => $shipment->current_status,
                'status_changed' => $orderingResult['status_changed'],
                'sequence_anomalies' => count($orderingResult['sequence_analysis']['anomalies'])
            ]);

            // Dispatch follow-up jobs
            $this->dispatchFollowUpJobs($shipment, $event, $orderingResult);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process event', [
                'tracking_number' => $this->eventData['tracking_number'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Find existing shipment or create new one
     */
    private function findOrCreateShipment(array $eventData): Shipment
    {
        $shipment = Shipment::where('tracking_number', $eventData['tracking_number'])->first();

        if (!$shipment) {
            $shipment = Shipment::create([
                'tracking_number' => $eventData['tracking_number'],
                'service_type' => $this->inferServiceType($eventData['tracking_number']),
                'current_status' => 'CREATED',
                'created_at' => $eventData['event_time'],
                'updated_at' => now()
            ]);

            Log::info('Created new shipment', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number
            ]);
        }

        return $shipment;
    }

    /**
     * Create event record
     */
    private function createEvent(array $eventData, Shipment $shipment): Event
    {
        return Event::create([
            'shipment_id' => $shipment->id,
            'event_id' => $eventData['event_id'],
            'event_code' => $eventData['event_code'],
            'event_time' => $eventData['event_time'],
            'facility_id' => $eventData['facility_id'] ?? null,
            'location' => $eventData['location'],
            'description' => $eventData['description'],
            'remarks' => $eventData['remarks'],
            'source' => $eventData['source'],
            'raw_payload' => $eventData['raw_payload'],
            'idempotency_key' => $eventData['idempotency_key'],
            'created_at' => now()
        ]);
    }



    /**
     * Dispatch follow-up jobs for notifications, ETA updates, etc.
     */
    private function dispatchFollowUpJobs(Shipment $shipment, Event $event, array $orderingResult): void
    {
        // These jobs would be implemented in later tasks
        // For now, just log the intent
        Log::info('Would dispatch follow-up jobs', [
            'shipment_id' => $shipment->id,
            'event_code' => $event->event_code,
            'status_changed' => $orderingResult['status_changed'],
            'jobs' => ['UpdateETAJob', 'SendNotificationJob']
        ]);

        // If there are sequence anomalies, we might want to alert operations
        if (!empty($orderingResult['sequence_analysis']['anomalies'])) {
            Log::warning('Event sequence anomalies detected', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'anomalies' => $orderingResult['sequence_analysis']['anomalies']
            ]);
            
            // Could dispatch AlertOperationsJob here
        }
    }

    /**
     * Infer service type from tracking number pattern
     */
    private function inferServiceType(string $trackingNumber): string
    {
        // Basic pattern matching - would be more sophisticated in production
        if (preg_match('/^TH\d+/', $trackingNumber)) {
            return 'STANDARD';
        } elseif (preg_match('/^EX\d+/', $trackingNumber)) {
            return 'EXPRESS';
        } elseif (preg_match('/^EC\d+/', $trackingNumber)) {
            return 'ECONOMY';
        }
        
        return 'STANDARD';
    }



    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessEventJob failed permanently', [
            'tracking_number' => $this->eventData['tracking_number'],
            'event_code' => $this->eventData['event_code'],
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could send to dead letter queue or alert monitoring system
    }
}