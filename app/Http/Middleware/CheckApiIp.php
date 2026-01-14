<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckApiIp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not authenticated or not via Sanctum/API
        if (!Auth::guard('sanctum')->check()) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();
        $token = $user->currentAccessToken();

        // Check if the token has 'allowed_ips' property (saved by ApiTokenController)
        if ($token && isset($token->allowed_ips) && !empty($token->allowed_ips)) {
             $ips = is_array($token->allowed_ips)
                    ? $token->allowed_ips
                    : array_map('trim', explode(',', $token->allowed_ips));

             if (!empty($ips) && !in_array($request->ip(), $ips)) {
                 return response()->json([
                     'status' => 'error',
                     'message' => "Access denied: IP not whitelisted. Please Firsts Whitelist your IP at n8n Panel. Your IP is {$request->ip()} "
                 ], 403);
             }
        }

        return $next($request);
    }
}
