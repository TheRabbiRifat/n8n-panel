<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Container;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'check.api.ip'])->group(function () {
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
Route::middleware(['auth:sanctum', 'check.api.ip', 'role:admin|reseller', 'log.api', 'throttle:60,1'])->prefix('integration')->group(function () {

    // Instance Management
    Route::post('/instances/create', [App\Http\Controllers\Api\ApiController::class, 'create']);
    Route::post('/instances/{name}/start', [App\Http\Controllers\Api\ApiController::class, 'start']);
    Route::post('/instances/{name}/stop', [App\Http\Controllers\Api\ApiController::class, 'stop']);
    Route::post('/instances/{name}/terminate', [App\Http\Controllers\Api\ApiController::class, 'terminate']);
    Route::post('/instances/{name}/suspend', [App\Http\Controllers\Api\ApiController::class, 'suspend']);
    Route::post('/instances/{name}/unsuspend', [App\Http\Controllers\Api\ApiController::class, 'unsuspend']);
    Route::post('/instances/{name}/upgrade', [App\Http\Controllers\Api\ApiController::class, 'upgrade']);
    Route::get('/instances/{name}/stats', [App\Http\Controllers\Api\ApiController::class, 'stats']);

    // Packages
    Route::get('/packages', [App\Http\Controllers\Api\ApiController::class, 'listPackages']);
    Route::get('/packages/{id}', [App\Http\Controllers\Api\ApiController::class, 'getPackage']);

    // Reseller Management
    Route::get('/resellers', [App\Http\Controllers\Api\ApiController::class, 'indexResellers']);
    Route::post('/resellers', [App\Http\Controllers\Api\ApiController::class, 'createReseller']);
    Route::get('/resellers/{name}', [App\Http\Controllers\Api\ApiController::class, 'showReseller']);
    Route::put('/resellers/{name}', [App\Http\Controllers\Api\ApiController::class, 'updateReseller']);
    Route::post('/resellers/{name}/suspend', [App\Http\Controllers\Api\ApiController::class, 'suspendReseller']);
    Route::post('/resellers/{name}/unsuspend', [App\Http\Controllers\Api\ApiController::class, 'unsuspendReseller']);
    Route::delete('/resellers/{name}', [App\Http\Controllers\Api\ApiController::class, 'destroyReseller']);

    // User SSO
    Route::post('/users/sso', [App\Http\Controllers\Api\ApiController::class, 'sso']);

    // System & Connection
    Route::get('/connection/test', [App\Http\Controllers\Api\ApiController::class, 'testConnection']);
    Route::get('/system/stats', [App\Http\Controllers\Api\ApiController::class, 'systemStats']);
});
