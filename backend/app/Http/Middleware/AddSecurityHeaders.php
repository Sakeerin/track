<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = config('security.headers', []);

        $response->headers->set('X-Frame-Options', $headers['x_frame_options'] ?? 'DENY');
        $response->headers->set('X-Content-Type-Options', $headers['x_content_type_options'] ?? 'nosniff');
        $response->headers->set('Referrer-Policy', $headers['referrer_policy'] ?? 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', $headers['content_security_policy'] ?? "default-src 'self'");

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                $headers['strict_transport_security'] ?? 'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
