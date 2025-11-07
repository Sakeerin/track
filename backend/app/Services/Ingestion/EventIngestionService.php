<?php

namespace App\Services\Ingestion;

use App\Jobs\ProcessEventJob;
use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EventIngestionService
{
    /**
     * Queue an event for processing with idempotency checking
     */
    public function queueEvent(array $eventData, string $source): array
    {
        try {
            // Normalize event data
            $normalizedEvent = $this->normalizeEventData($eventData, $source);
            
            // Check for idempotency using eventId + trackingNo + timestamp
            $idempotencyKey = $this->generateIdempotencyKey($normalizedEvent);
            
            // Check if event already exists
            $existingEvent = Event::where('idempotency_key', $idempotencyKey)->first();
            
            if ($existingEvent) {
                Log::info('Duplicate event detected, skipping', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_event_id' => $existingEvent->id,
                    'tracking_number' => $normalizedEvent['tracking_number']
                ]);
                
                return [
                    'status' => 'duplicate',
                    'message' => 'Event already processed',
                    'event_id' => $existingEvent->id
                ];
            }

            // Validate event data
            $validationResult = $this->validateEventData($normalizedEvent);
            if (!$validationResult['valid']) {
                Log::warning('Event validation failed', [
                    'tracking_number' => $normalizedEvent['tracking_number'],
                    'errors' => $validationResult['errors']
                ]);
                
                return [
                    'status' => 'validation_failed',
                    'message' => 'Event validation failed',
                    'errors' => $validationResult['errors']
                ];
            }

            // Add idempotency key to event data
            $normalizedEvent['idempotency_key'] = $idempotencyKey;

            // Dispatch job for async processing
            ProcessEventJob::dispatch($normalizedEvent)
                ->onQueue('events')
                ->delay(now()->addSeconds(1)); // Small delay to handle burst processing

            Log::info('Event queued for processing', [
                'idempotency_key' => $idempotencyKey,
                'tracking_number' => $normalizedEvent['tracking_number'],
                'event_code' => $normalizedEvent['event_code'],
                'source' => $source
            ]);

            return [
                'status' => 'queued',
                'message' => 'Event queued for processing',
                'idempotency_key' => $idempotencyKey
            ];

        } catch (\Exception $e) {
            Log::error('Failed to queue event', [
                'error' => $e->getMessage(),
                'event_data' => $eventData,
                'source' => $source,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to queue event: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Normalize event data from different sources
     */
    private function normalizeEventData(array $eventData, string $source): array
    {
        return [
            'event_id' => $eventData['event_id'],
            'tracking_number' => strtoupper(trim($eventData['tracking_number'])),
            'event_code' => strtoupper(trim($eventData['event_code'])),
            'event_time' => Carbon::parse($eventData['event_time'])->utc(),
            'facility_code' => isset($eventData['facility_code']) ? strtoupper(trim($eventData['facility_code'])) : null,
            'location' => $eventData['location'] ?? null,
            'description' => $eventData['description'] ?? null,
            'remarks' => $eventData['remarks'] ?? null,
            'partner_reference' => $eventData['partner_reference'] ?? null,
            'source_system' => $eventData['source_system'] ?? $source,
            'source' => $source,
            'raw_payload' => $eventData,
            'created_at' => now(),
        ];
    }

    /**
     * Generate idempotency key for deduplication
     */
    private function generateIdempotencyKey(array $eventData): string
    {
        $keyData = [
            $eventData['event_id'],
            $eventData['tracking_number'],
            $eventData['event_time']->timestamp,
            $eventData['event_code']
        ];
        
        return hash('sha256', implode('|', $keyData));
    }

    /**
     * Validate event data
     */
    private function validateEventData(array $eventData): array
    {
        $errors = [];

        // Required fields validation
        $requiredFields = ['event_id', 'tracking_number', 'event_code', 'event_time'];
        foreach ($requiredFields as $field) {
            if (empty($eventData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Tracking number format validation
        if (!empty($eventData['tracking_number'])) {
            if (!preg_match('/^[A-Z0-9]{8,20}$/', $eventData['tracking_number'])) {
                $errors[] = 'Invalid tracking number format';
            }
        }

        // Event time validation
        if (!empty($eventData['event_time'])) {
            $eventTime = $eventData['event_time'];
            $now = now();
            
            // Event cannot be more than 1 year old or 1 hour in the future
            if ($eventTime->lt($now->subYear()) || $eventTime->gt($now->addHour())) {
                $errors[] = 'Event time is outside acceptable range';
            }
        }

        // Event code validation (basic format check)
        if (!empty($eventData['event_code'])) {
            if (!preg_match('/^[A-Z_]{2,20}$/', $eventData['event_code'])) {
                $errors[] = 'Invalid event code format';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get ingestion statistics
     */
    public function getIngestionStats(): array
    {
        return [
            'events_today' => Event::whereDate('created_at', today())->count(),
            'events_last_hour' => Event::where('created_at', '>=', now()->subHour())->count(),
            'unique_tracking_numbers_today' => Event::whereDate('created_at', today())
                ->distinct('tracking_number')
                ->count('tracking_number'),
            'top_event_codes_today' => Event::whereDate('created_at', today())
                ->select('event_code', DB::raw('count(*) as count'))
                ->groupBy('event_code')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
        ];
    }
}