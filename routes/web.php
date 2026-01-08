<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ServiceController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

use App\Http\Controllers\PackageController;

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Package Management
    Route::resource('packages', PackageController::class)->except(['show']);

    // Container Management
    Route::get('containers/create', [ContainerController::class, 'create'])->name('containers.create');
    Route::post('containers', [ContainerController::class, 'store'])->name('containers.store');
    Route::post('containers/{id}/start', [ContainerController::class, 'start'])->name('containers.start');
    Route::post('containers/{id}/stop', [ContainerController::class, 'stop'])->name('containers.stop');
    Route::delete('containers/{id}', [ContainerController::class, 'destroy'])->name('containers.destroy');

    // User Management (Admin Only)
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('users', UserController::class);

        // Service Management
        Route::post('services/{service}/{action}', [ServiceController::class, 'handle'])->name('services.handle');
    });
});
