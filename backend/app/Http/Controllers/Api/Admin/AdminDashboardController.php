<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AdminDashboardController extends Controller
{
    /**
     * Get system health overview
     */
    public function health(): JsonResponse
    {
        try {
            $health = [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'queue' => $this->checkQueue(),
            ];

            $overallStatus = collect($health)->every(fn($h) => $h['status'] === 'healthy')
                ? 'healthy'
                : (collect($health)->some(fn($h) => $h['status'] === 'unhealthy') ? 'unhealthy' : 'degraded');

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $overallStatus,
                    'services' => $health,
                    'server' => [
                        'php_version' => PHP_VERSION,
                        'laravel_version' => app()->version(),
                        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                        'uptime' => $this->getUptime(),
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Health check failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'data' => [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month',
        ]);

        try {
            $period = $request->input('period', 'today');
            $startDate = $this->getPeriodStart($period);

            $stats = Cache::remember("admin:stats:{$period}", 60, function () use ($startDate) {
                return [
                    'shipments' => [
                        'total' => Shipment::count(),
                        'period' => Shipment::where('created_at', '>=', $startDate)->count(),
                        'by_status' => Shipment::select('current_status', DB::raw('count(*) as count'))
                            ->groupBy('current_status')
                            ->pluck('count', 'current_status'),
                    ],
                    'events' => [
                        'total' => Event::count(),
                        'period' => Event::where('created_at', '>=', $startDate)->count(),
                        'per_hour' => $this->getEventsPerHour(),
                    ],
                    'subscriptions' => [
                        'total' => Subscription::count(),
                        'active' => Subscription::where('active', true)->count(),
                        'by_channel' => Subscription::select('channel', DB::raw('count(*) as count'))
                            ->groupBy('channel')
                            ->pluck('count', 'channel'),
                    ],
                    'users' => [
                        'total' => User::count(),
                        'active' => User::where('is_active', true)->count(),
                        'logged_in_today' => User::whereDate('last_login_at', today())->count(),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => $period,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get stats', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve statistics',
                'error_code' => 'STATS_ERROR',
            ], 500);
        }
    }

    /**
     * Get event processing metrics
     */
    public function eventMetrics(): JsonResponse
    {
        try {
            $metrics = Cache::remember('admin:event_metrics', 30, function () {
                $now = now();
                
                return [
                    'events_last_hour' => Event::where('created_at', '>=', $now->subHour())->count(),
                    'events_per_minute' => round(Event::where('created_at', '>=', $now->subMinutes(5))->count() / 5, 2),
                    'avg_processing_time' => $this->getAverageProcessingTime(),
                    'error_rate' => $this->getErrorRate(),
                    'top_event_codes' => Event::select('event_code', DB::raw('count(*) as count'))
                        ->where('created_at', '>=', $now->subDay())
                        ->groupBy('event_code')
                        ->orderByDesc('count')
                        ->limit(10)
                        ->pluck('count', 'event_code'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get event metrics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve metrics',
                'error_code' => 'METRICS_ERROR',
            ], 500);
        }
    }

    /**
     * Get SLA metrics
     */
    public function slaMetrics(): JsonResponse
    {
        try {
            $metrics = Cache::remember('admin:sla_metrics', 300, function () {
                $now = now();
                $lastWeek = $now->copy()->subWeek();

                // Calculate on-time delivery rate
                $delivered = Shipment::where('current_status', 'delivered')
                    ->where('updated_at', '>=', $lastWeek)
                    ->get();

                $onTime = $delivered->filter(function ($s) {
                    return $s->estimated_delivery && $s->updated_at <= $s->estimated_delivery;
                })->count();

                $onTimeRate = $delivered->count() > 0
                    ? round(($onTime / $delivered->count()) * 100, 2)
                    : 0;

                // Calculate exception rate
                $totalShipments = Shipment::where('created_at', '>=', $lastWeek)->count();
                $exceptions = Shipment::where('current_status', 'exception')
                    ->where('created_at', '>=', $lastWeek)
                    ->count();

                $exceptionRate = $totalShipments > 0
                    ? round(($exceptions / $totalShipments) * 100, 2)
                    : 0;

                return [
                    'on_time_delivery_rate' => $onTimeRate,
                    'exception_rate' => $exceptionRate,
                    'delivered_count' => $delivered->count(),
                    'on_time_count' => $onTime,
                    'exception_count' => $exceptions,
                    'period' => 'last_7_days',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get SLA metrics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve SLA metrics',
                'error_code' => 'METRICS_ERROR',
            ], 500);
        }
    }

    /**
     * Get queue status
     */
    public function queueStatus(): JsonResponse
    {
        try {
            $queues = ['default', 'events', 'notifications'];
            $status = [];

            foreach ($queues as $queue) {
                try {
                    $size = Queue::size($queue);
                    $status[$queue] = [
                        'size' => $size,
                        'status' => $size > 1000 ? 'high' : ($size > 100 ? 'moderate' : 'normal'),
                    ];
                } catch (\Exception $e) {
                    $status[$queue] = [
                        'size' => null,
                        'status' => 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $status,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queue status', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve queue status',
                'error_code' => 'QUEUE_ERROR',
            ], 500);
        }
    }

    /**
     * Get audit logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|uuid',
            'action' => 'nullable|string|max:100',
            'entity_type' => 'nullable|string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $query = AuditLog::with('user')->orderBy('created_at', 'desc');

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            if ($request->filled('entity_type')) {
                $query->where('entity_type', $request->entity_type);
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $perPage = $request->input('per_page', 50);
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => collect($logs->items())->map(fn($log) => [
                        'id' => $log->id,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                            'email' => $log->user->email,
                        ] : null,
                        'action' => $log->action,
                        'entity_type' => $log->entity_type,
                        'entity_id' => $log->entity_id,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'ip_address' => $log->ip_address,
                        'metadata' => $log->metadata,
                        'created_at' => $log->created_at?->toISOString(),
                    ]),
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get audit logs', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve audit logs',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Check database connection
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $latency = $this->measureLatency(fn() => DB::select('SELECT 1'));
            
            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis(): array
    {
        try {
            $latency = $this->measureLatency(fn() => Redis::ping());
            
            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue connection
     */
    private function checkQueue(): array
    {
        try {
            $size = Queue::size();
            
            return [
                'status' => 'healthy',
                'pending_jobs' => $size,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Measure operation latency
     */
    private function measureLatency(callable $operation): float
    {
        $start = microtime(true);
        $operation();
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get system uptime
     */
    private function getUptime(): string
    {
        $uptime = Cache::get('app:start_time');
        if (!$uptime) {
            Cache::put('app:start_time', now(), now()->addYear());
            return '0 seconds';
        }

        return $uptime->diffForHumans(now(), true);
    }

    /**
     * Get period start date
     */
    private function getPeriodStart(string $period): \Carbon\Carbon
    {
        return match ($period) {
            'today' => today(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => today(),
        };
    }

    /**
     * Get events per hour for the last 24 hours
     */
    private function getEventsPerHour(): array
    {
        $result = Event::select(
            DB::raw('DATE_TRUNC(\'hour\', created_at) as hour'),
            DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        return $result;
    }

    /**
     * Get average processing time (placeholder)
     */
    private function getAverageProcessingTime(): float
    {
        // This would typically come from metrics/logging
        return 0.0;
    }

    /**
     * Get error rate (placeholder)
     */
    private function getErrorRate(): float
    {
        // This would typically come from error tracking
        return 0.0;
    }
}
