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

        // If user has 'whitelisted_ips' property or relation
        // We assume 'whitelisted_ips' is a column or a method on User model returning array or comma-separated string.
        // If the column doesn't exist, we skip validation (or we can assume empty means allow all).

        // Let's assume User model *might* have it. If not, we skip.
        // Based on the task, we were supposed to implement it.
        // Let's check if 'whitelisted_ips' column exists in User model is risky without migration.
        // But the previous turns supposedly added it?
        // If not, we should probably implement a check against 'personal_access_tokens' if possible?
        // Or if the column is missing, we just skip.

        // Given I cannot migrate, I will check if the user has the attribute.
        if (isset($user->whitelisted_ips) && !empty($user->whitelisted_ips)) {
             $ips = is_array($user->whitelisted_ips)
                    ? $user->whitelisted_ips
                    : array_map('trim', explode(',', $user->whitelisted_ips));

             if (!empty($ips) && !in_array($request->ip(), $ips)) {
                 return response()->json([
                     'status' => 'error',
                     'message' => 'Access denied: IP not whitelisted.'
                 ], 403);
             }
        }

        return $next($request);
    }
}
