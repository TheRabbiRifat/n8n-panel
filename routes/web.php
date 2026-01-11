<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\GlobalEnvironmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ServiceController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

use App\Http\Controllers\PackageController;
use App\Http\Controllers\ProfileController;

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // API Tokens
    Route::get('profile/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('profile/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::put('profile/api-tokens/{id}', [App\Http\Controllers\ApiTokenController::class, 'update'])->name('api-tokens.update');
    Route::delete('profile/api-tokens/{id}', [App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    // Package Management
    Route::resource('packages', PackageController::class)->except(['show']);

    // Instance Management (CRUD)
    Route::resource('instances', InstanceController::class)->except(['show', 'edit', 'update']);

    // User Management (Admin Only)
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('users', UserController::class);

        // Global Environment
        Route::get('settings/environment', [GlobalEnvironmentController::class, 'index'])->name('admin.environment.index');
        Route::put('settings/environment', [GlobalEnvironmentController::class, 'update'])->name('admin.environment.update');

        // Panel Settings
        Route::get('settings/panel', [App\Http\Controllers\AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::put('settings/panel', [App\Http\Controllers\AdminSettingsController::class, 'update'])->name('admin.settings.update');

        // Container Discovery (Must be before container details)
        Route::get('containers/orphans', [ContainerController::class, 'orphans'])->name('containers.orphans');
        Route::post('containers/import', [ContainerController::class, 'import'])->name('containers.import');
        Route::delete('containers/orphans', [ContainerController::class, 'deleteOrphan'])->name('containers.deleteOrphan');
    });

    // Detailed Instance Management (Actions)
    Route::get('containers/{id}', [ContainerController::class, 'show'])->name('containers.show');
    Route::put('containers/{id}', [ContainerController::class, 'update'])->name('containers.update');
    Route::post('containers/{id}/restart', [ContainerController::class, 'restart'])->name('containers.restart');
    Route::get('containers/{id}/logs', [ContainerController::class, 'logs'])->name('containers.logs');
    Route::get('containers/{id}/logs/download', [ContainerController::class, 'downloadLogs'])->name('containers.logs.download');
    Route::get('containers/{id}/stats', [ContainerController::class, 'stats'])->name('containers.stats');

    Route::post('containers/{id}/start', [ContainerController::class, 'start'])->name('containers.start');
    Route::post('containers/{id}/stop', [ContainerController::class, 'stop'])->name('containers.stop');
    Route::delete('containers/{id}', [ContainerController::class, 'destroy'])->name('containers.destroy');

    // Admin Routes Continued
    Route::middleware(['role:admin'])->group(function () {

        // Service Management
        Route::post('services/{service}/{action}', [ServiceController::class, 'handle'])->name('services.handle');

        // API Logs
        Route::get('api-logs', [App\Http\Controllers\ApiLogController::class, 'index'])->name('admin.api_logs.index');

        // Roles & Permissions
        Route::resource('roles', \App\Http\Controllers\RoleController::class);
        Route::resource('permissions', \App\Http\Controllers\PermissionController::class);

        // System Settings
        Route::get('system', [\App\Http\Controllers\SystemController::class, 'index'])->name('admin.system.index');
        Route::post('system/hostname', [\App\Http\Controllers\SystemController::class, 'updateHostname'])->name('admin.system.hostname');
        Route::post('system/reboot', [\App\Http\Controllers\SystemController::class, 'reboot'])->name('admin.system.reboot');
        Route::post('system/services/{service}/restart', [\App\Http\Controllers\SystemController::class, 'restartService'])->name('admin.system.service.restart');
    });
});
