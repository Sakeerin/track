<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'API key required'
            ], 401);
        }

        // Skip validation in testing environment
        if (app()->environment('testing')) {
            return $next($request);
        }

        // Get valid API keys from config
        $validKeys = config('services.api_keys', []);
        
        if (!in_array($apiKey, $validKeys)) {
            Log::warning('Invalid API key used', [
                'key_prefix' => substr($apiKey, 0, 8) . '...',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'error' => 'Invalid API key'
            ], 401);
        }

        // Rate limiting per API key
        $rateLimitKey = 'api_rate_limit:' . hash('sha256', $apiKey);
        $maxRequests = 100; // per minute
        $currentCount = Cache::get($rateLimitKey, 0);

        if ($currentCount >= $maxRequests) {
            Log::warning('API rate limit exceeded', [
                'key_prefix' => substr($apiKey, 0, 8) . '...',
                'current_count' => $currentCount,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => 60
            ], 429);
        }

        // Increment rate limit counter
        Cache::put($rateLimitKey, $currentCount + 1, 60);

        Log::info('API key validation successful', [
            'key_prefix' => substr($apiKey, 0, 8) . '...',
            'ip' => $request->ip()
        ]);

        return $next($request);
    }
}