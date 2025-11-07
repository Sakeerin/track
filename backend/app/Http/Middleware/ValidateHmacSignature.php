<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ValidateHmacSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partnerId = $request->header('X-Partner-ID');
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');

        // Skip validation in testing environment
        if (app()->environment('testing')) {
            return $next($request);
        }

        if (!$partnerId || !$signature || !$timestamp) {
            Log::warning('Missing HMAC headers', [
                'partner_id' => $partnerId,
                'has_signature' => !empty($signature),
                'has_timestamp' => !empty($timestamp),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'Missing required headers: X-Partner-ID, X-Signature, X-Timestamp'
            ], 401);
        }

        // Validate timestamp (prevent replay attacks)
        $requestTime = (int) $timestamp;
        $currentTime = time();
        $maxAge = 300; // 5 minutes

        if (abs($currentTime - $requestTime) > $maxAge) {
            Log::warning('HMAC timestamp too old', [
                'partner_id' => $partnerId,
                'timestamp' => $timestamp,
                'age_seconds' => abs($currentTime - $requestTime),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'Request timestamp is too old'
            ], 401);
        }

        // Get partner secret from config
        $partnerSecrets = config('services.partners', []);
        $secret = $partnerSecrets[$partnerId] ?? null;

        if (!$secret) {
            Log::warning('Unknown partner ID', [
                'partner_id' => $partnerId,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'Unknown partner'
            ], 401);
        }

        // Validate HMAC signature
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp . $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Invalid HMAC signature', [
                'partner_id' => $partnerId,
                'expected_length' => strlen($expectedSignature),
                'received_length' => strlen($signature),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'Invalid signature'
            ], 401);
        }

        Log::info('HMAC validation successful', [
            'partner_id' => $partnerId,
            'ip' => $request->ip()
        ]);

        return $next($request);
    }
}