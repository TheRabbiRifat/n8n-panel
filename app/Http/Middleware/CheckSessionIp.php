<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionIp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // If remember me is NOT checked, enforce IP check
            if (!Auth::viaRemember()) {
                $loginIp = $request->session()->get('login_ip');

                if ($loginIp && $loginIp !== $request->ip()) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors(['email' => 'Session expired due to IP address change.']);
                }
            }
        }

        return $next($request);
    }
}
