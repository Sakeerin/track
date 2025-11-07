<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SingleTrackingRequest;
use App\Http\Requests\TrackingRequest;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    public function __construct(
        private TrackingService $trackingService
    ) {}

    /**
     * Track multiple shipments
     */
    public function track(TrackingRequest $request): JsonResponse
    {
        try {
            $trackingNumbers = $request->validated()['tracking_numbers'];
            
            Log::info('Multi-shipment tracking request', [
                'tracking_numbers' => $trackingNumbers,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $results = $this->trackingService->getShipments($trackingNumbers);
            
            // Separate successful and failed lookups
            $successful = [];
            $failed = [];
            
            foreach ($results as $trackingNumber => $data) {
                if ($data !== null) {
                    $successful[$trackingNumber] = $data;
                } else {
                    $failed[] = [
                        'tracking_number' => $trackingNumber,
                        'error' => 'Tracking number not found',
                        'error_code' => 'NOT_FOUND',
                    ];
                }
            }

            $response = [
                'success' => true,
                'data' => [
                    'successful' => $successful,
                    'failed' => $failed,
                    'summary' => [
                        'total_requested' => count($trackingNumbers),
                        'successful_count' => count($successful),
                        'failed_count' => count($failed),
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ];

            // Log partial failures for monitoring
            if (!empty($failed)) {
                Log::warning('Partial tracking failure', [
                    'failed_tracking_numbers' => array_column($failed, 'tracking_number'),
                    'success_rate' => count($successful) / count($trackingNumbers),
                ]);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Tracking request failed', [
                'error' => $e->getMessage(),
                'tracking_numbers' => $request->input('tracking_numbers', []),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'error_code' => 'INTERNAL_ERROR',
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Track a single shipment (SEO-friendly endpoint)
     */
    public function trackSingle(SingleTrackingRequest $request, string $trackingNumber): JsonResponse
    {
        try {
            Log::info('Single shipment tracking request', [
                'tracking_number' => $trackingNumber,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $shipment = $this->trackingService->getShipment($trackingNumber);

            if ($shipment === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tracking number not found',
                    'error_code' => 'NOT_FOUND',
                    'tracking_number' => $trackingNumber,
                    'timestamp' => now()->toISOString(),
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $shipment,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Single tracking request failed', [
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'error_code' => 'INTERNAL_ERROR',
                'tracking_number' => $trackingNumber,
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Health check endpoint for tracking service
     */
    public function health(): JsonResponse
    {
        try {
            $stats = $this->trackingService->getTrackingStats();
            
            return response()->json([
                'success' => true,
                'service' => 'tracking',
                'status' => 'healthy',
                'stats' => $stats,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Tracking health check failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'service' => 'tracking',
                'status' => 'unhealthy',
                'error' => 'Service unavailable',
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }
}