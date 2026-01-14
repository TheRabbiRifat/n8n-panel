<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function index()
    {
        $tokens = Auth::user()->tokens;
        return view('profile.api-tokens', compact('tokens'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'token_name' => 'required|string|max:255',
            'allowed_ips' => 'nullable|string',
        ]);

        $ips = $this->parseIps($request->allowed_ips);
        if (count($ips) > 5) {
            return back()->withErrors(['allowed_ips' => 'Maximum 5 IP addresses allowed.']);
        }

        $token = $request->user()->createToken($request->token_name);

        $personalAccessToken = $token->accessToken;
        $personalAccessToken->allowed_ips = !empty($ips) ? implode(',', $ips) : null;
        $personalAccessToken->save();

        return back()->with('flash_token', $token->plainTextToken)
                     ->with('success', 'API Token created.');
    }

    public function update(Request $request, $id)
    {
        $token = $request->user()->tokens()->findOrFail($id);

        $request->validate([
            'allowed_ips' => 'nullable|string',
        ]);

        $ips = $this->parseIps($request->allowed_ips);
        if (count($ips) > 5) {
            return back()->withErrors(['allowed_ips' => 'Maximum 5 IP addresses allowed.']);
        }

        $token->allowed_ips = !empty($ips) ? implode(',', $ips) : null;
        $token->save();

        return back()->with('success', 'Token updated.');
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();
        return back()->with('success', 'Token revoked.');
    }

    protected function parseIps($string)
    {
        if (empty($string)) return [];

        $ips = [];
        $rawIps = explode(',', $string);
        foreach ($rawIps as $ip) {
            $trimmed = trim($ip);
            if (filter_var($trimmed, FILTER_VALIDATE_IP)) {
                $ips[] = $trimmed;
            }
        }
        return array_unique($ips);
    }
}
