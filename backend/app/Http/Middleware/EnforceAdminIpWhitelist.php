<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('security.admin_ip_whitelist', []);

        if (empty($allowedIps)) {
            return $next($request);
        }

        if (!IpUtils::checkIp($request->ip(), $allowedIps)) {
            return response()->json([
                'success' => false,
                'error' => 'Access denied from this IP address',
                'error_code' => 'IP_NOT_ALLOWED',
            ], 403);
        }

        return $next($request);
    }
}
