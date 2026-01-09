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
        ]);

        $token = $request->user()->createToken($request->token_name);

        return back()->with('flash_token', $token->plainTextToken)
                     ->with('success', 'API Token created.');
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();
        return back()->with('success', 'Token revoked.');
    }
}
