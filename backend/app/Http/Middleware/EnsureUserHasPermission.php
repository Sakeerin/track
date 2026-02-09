<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$request->user()->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Account is disabled',
                'error_code' => 'ACCOUNT_DISABLED',
            ], 403);
        }

        if (!$request->user()->hasAnyPermission($permissions)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden - insufficient permissions',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
