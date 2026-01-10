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

// WHMCS / External Integration API
Route::middleware(['auth:sanctum', 'role:admin', 'log.api'])->prefix('integration')->group(function () {

    // Instance Management
    Route::post('/instances/create', [App\Http\Controllers\Api\WhmcsController::class, 'create']);
    Route::post('/instances/{id}/start', [App\Http\Controllers\Api\WhmcsController::class, 'start']);
    Route::post('/instances/{id}/stop', [App\Http\Controllers\Api\WhmcsController::class, 'stop']);
    Route::post('/instances/{id}/terminate', [App\Http\Controllers\Api\WhmcsController::class, 'terminate']);
    Route::post('/instances/{id}/suspend', [App\Http\Controllers\Api\WhmcsController::class, 'suspend']);
    Route::post('/instances/{id}/unsuspend', [App\Http\Controllers\Api\WhmcsController::class, 'unsuspend']);
    Route::get('/instances/{id}/stats', [App\Http\Controllers\Api\WhmcsController::class, 'stats']);

    // Packages
    Route::get('/packages', [App\Http\Controllers\Api\WhmcsController::class, 'listPackages']);
    Route::get('/packages/{id}', [App\Http\Controllers\Api\WhmcsController::class, 'getPackage']);

    // Reseller Management
    Route::post('/resellers', [App\Http\Controllers\Api\WhmcsController::class, 'createReseller']);
});
