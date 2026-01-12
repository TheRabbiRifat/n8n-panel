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

// External Integration API
Route::middleware(['auth:sanctum', 'role:admin|reseller', 'log.api', 'throttle:60,1'])->prefix('integration')->group(function () {

    // Instance Management
    Route::post('/instances/create', [App\Http\Controllers\Api\ApiController::class, 'create']);
    Route::post('/instances/{id}/start', [App\Http\Controllers\Api\ApiController::class, 'start']);
    Route::post('/instances/{id}/stop', [App\Http\Controllers\Api\ApiController::class, 'stop']);
    Route::post('/instances/{id}/terminate', [App\Http\Controllers\Api\ApiController::class, 'terminate']);
    Route::post('/instances/{id}/suspend', [App\Http\Controllers\Api\ApiController::class, 'suspend']);
    Route::post('/instances/{id}/unsuspend', [App\Http\Controllers\Api\ApiController::class, 'unsuspend']);
    Route::post('/instances/{id}/upgrade', [App\Http\Controllers\Api\ApiController::class, 'upgrade']);
    Route::get('/instances/{id}/stats', [App\Http\Controllers\Api\ApiController::class, 'stats']);

    // Packages
    Route::get('/packages', [App\Http\Controllers\Api\ApiController::class, 'listPackages']);
    Route::get('/packages/{id}', [App\Http\Controllers\Api\ApiController::class, 'getPackage']);

    // Reseller Management
    Route::post('/resellers', [App\Http\Controllers\Api\ApiController::class, 'createReseller']);

    // User SSO
    Route::post('/users/sso', [App\Http\Controllers\Api\ApiController::class, 'sso']);

    // System & Connection
    Route::get('/connection/test', [App\Http\Controllers\Api\ApiController::class, 'testConnection']);
    Route::get('/system/stats', [App\Http\Controllers\Api\ApiController::class, 'systemStats']);
});
