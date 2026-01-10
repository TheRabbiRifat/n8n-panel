<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Auth;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $payload = $request->all();
            $sensitiveKeys = ['password', 'password_confirmation', 'secret', 'key', 'token'];
            foreach ($sensitiveKeys as $key) {
                if (isset($payload[$key])) {
                    $payload[$key] = '[REDACTED]';
                }
            }

            ApiLog::create([
                'user_id' => Auth::id(),
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'request_payload' => $payload,
                'response_code' => $response->getStatusCode(),
                // Be careful logging full response if it contains sensitive data or is huge.
                // For now, logging it as requested for 'API logs'.
                'response_payload' => json_decode($response->getContent(), true),
            ]);
        } catch (\Exception $e) {
            // Do not fail the request if logging fails
            // Log::error('Failed to log API request: ' . $e->getMessage());
        }

        return $response;
    }
}
