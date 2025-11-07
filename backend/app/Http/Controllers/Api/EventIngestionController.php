<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookEventRequest;
use App\Http\Requests\BatchUploadRequest;
use App\Services\Ingestion\EventIngestionService;
use App\Services\Ingestion\BatchProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventIngestionController extends Controller
{
    public function __construct(
        private EventIngestionService $ingestionService,
        private BatchProcessingService $batchService
    ) {}

    /**
     * Receive webhook events from handhelds and partners
     */
    public function receiveWebhook(WebhookEventRequest $request): JsonResponse
    {
        try {
            $events = $request->validated()['events'] ?? [$request->validated()];
            
            $results = [];
            foreach ($events as $eventData) {
                $result = $this->ingestionService->queueEvent($eventData, 'webhook');
                $results[] = [
                    'event_id' => $eventData['event_id'] ?? null,
                    'tracking_number' => $eventData['tracking_number'] ?? null,
                    'status' => $result['status'],
                    'message' => $result['message'] ?? null
                ];
            }

            Log::info('Webhook events received', [
                'partner' => $request->header('X-Partner-ID'),
                'event_count' => count($events),
                'results' => $results
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Events received successfully',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'partner' => $request->header('X-Partner-ID'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook events',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle batch CSV file uploads via SFTP or direct upload
     */
    public function processBatch(BatchUploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('batch_file');
            $partnerId = $request->input('partner_id');
            
            $result = $this->batchService->processCsvFile($file, $partnerId);

            Log::info('Batch file processed', [
                'partner_id' => $partnerId,
                'filename' => $file->getClientOriginalName(),
                'processed_count' => $result['processed_count'],
                'failed_count' => $result['failed_count']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch file processed successfully',
                'processed_count' => $result['processed_count'],
                'failed_count' => $result['failed_count'],
                'errors' => $result['errors'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Batch processing failed', [
                'error' => $e->getMessage(),
                'partner_id' => $request->input('partner_id'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch file',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Health check endpoint for monitoring
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    }
}