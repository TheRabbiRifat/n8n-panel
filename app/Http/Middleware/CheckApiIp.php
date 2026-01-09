<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiIp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && !empty($token->allowed_ips)) {
            $ip = $request->ip();
            if (!in_array($ip, $token->allowed_ips)) {
                return response()->json(['message' => 'IP address not authorized for this token.'], 403);
            }
        }

        return $next($request);
    }
}
