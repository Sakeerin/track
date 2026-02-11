<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateRecaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isEnabled()) {
            return $next($request);
        }

        $token = $request->header('X-Recaptcha-Token') ?? $request->input('recaptcha_token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'reCAPTCHA verification failed',
                'error_code' => 'RECAPTCHA_REQUIRED',
            ], 422);
        }

        $secret = (string) config('services.recaptcha.secret_key', '');
        if ($secret === '') {
            Log::warning('reCAPTCHA enabled without secret key');

            return response()->json([
                'success' => false,
                'error' => 'reCAPTCHA misconfiguration',
                'error_code' => 'RECAPTCHA_CONFIG_ERROR',
            ], 503);
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify'), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $exception) {
            Log::warning('reCAPTCHA verification request failed', ['error' => $exception->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'reCAPTCHA verification unavailable',
                'error_code' => 'RECAPTCHA_UNAVAILABLE',
            ], 503);
        }

        $payload = $response->json() ?? [];
        $score = (float) ($payload['score'] ?? 1.0);
        $threshold = (float) config('services.recaptcha.score_threshold', 0.5);

        if (!$response->ok() || !($payload['success'] ?? false) || $score < $threshold) {
            Log::info('reCAPTCHA verification rejected', [
                'score' => $payload['score'] ?? null,
                'errors' => $payload['error-codes'] ?? [],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'reCAPTCHA verification failed',
                'error_code' => 'RECAPTCHA_FAILED',
            ], 422);
        }

        return $next($request);
    }

    private function isEnabled(): bool
    {
        if (!config('services.recaptcha.enabled', false)) {
            return false;
        }

        if (app()->environment('testing') && !config('services.recaptcha.enforce_in_testing', false)) {
            return false;
        }

        return true;
    }
}
