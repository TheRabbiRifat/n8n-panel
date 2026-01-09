<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Container;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // List Instances
    Route::get('/instances', function (Request $request) {
        $user = $request->user();
        if ($user->hasRole('admin')) {
            return Container::with('package')->get();
        }
        return Container::with('package')->where('user_id', $user->id)->get();
    });
});
