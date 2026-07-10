<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientTrackingController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\GeocodingController;
use App\Http\Controllers\OriginLocationController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes (public)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('auth.login');
    Route::post('/login', [LoginController::class, 'login'])->name('auth.login.post');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('auth.logout');

/*
|--------------------------------------------------------------------------
| Client Tracking Portal (public — no auth)
|--------------------------------------------------------------------------
*/
Route::get('/track', [ClientTrackingController::class, 'index'])->name('client.track');
Route::get('/api/track/{trackingCode}/status', [ClientTrackingController::class, 'status'])
    ->name('client.track.status');

/*
|--------------------------------------------------------------------------
| Fleet Dashboard (requires login + active account)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->prefix('fleet')->name('fleet.')->group(function () {

    // ── Pages (admin + manager) ──────────────────────────────────────────
    Route::get('/', [FleetController::class, 'dashboard'])->name('dashboard');
    Route::get('/shipments', [FleetController::class, 'shipments'])->name('shipments');

    // Vehicles page — admin + manager only
    Route::get('/vehicles', [FleetController::class, 'vehicles'])
        ->middleware('role:admin,manager')
        ->name('vehicles');

    // ── Vehicle CRUD (admin + manager) ────────────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('/vehicles', [FleetController::class, 'storeVehicle'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [FleetController::class, 'updateVehicle'])->name('vehicles.update');
        Route::patch('/vehicles/{vehicle}/toggle', [FleetController::class, 'toggleVehicle'])->name('vehicles.toggle');
        Route::delete('/vehicles/{vehicle}', [FleetController::class, 'destroyVehicle'])->name('vehicles.destroy');
    });

    // ── Live data APIs ────────────────────────────────────────────────────
    Route::get('/api/live', [FleetController::class, 'livePositions'])->name('api.live');
    Route::get('/api/alerts', [FleetController::class, 'unreadAlerts'])->name('api.alerts');
    Route::get('/api/vehicle/{vehicle}/history', [FleetController::class, 'tripHistory'])->name('api.history');
    Route::post('/api/alerts/{alert}/read', [FleetController::class, 'markAlertRead'])->name('api.alert.read');

    // ── Shipments ─────────────────────────────────────────────────────────
    Route::post('/api/shipments', [FleetController::class, 'storeShipment'])
        ->middleware('role:admin,manager')
        ->name('api.shipment.store');
    Route::get('/api/shipments/{shipment}', [FleetController::class, 'shipmentDetail'])->name('api.shipment.detail');
    Route::patch('/api/shipments/{shipment}/status', [FleetController::class, 'updateShipmentStatus'])
        ->middleware('role:admin,manager')
        ->name('api.shipment.status');

    // Address geocoding (self-hosted Nominatim proxy) — used by the shipment
    // create form's address search + two-way pin sync.
    Route::middleware(['role:admin,manager', 'throttle:30,1'])->group(function () {
        Route::get('/api/geocode', [GeocodingController::class, 'search'])->name('api.geocode');
        Route::get('/api/geocode/reverse', [GeocodingController::class, 'reverse'])->name('api.geocode.reverse');
    });

    // Delivery lifecycle — driver only
    Route::get('/api/delivery-status', [FleetController::class, 'deliveryStatus'])->name('api.delivery.status');
    Route::post('/api/shipments/{shipment}/start-delivery', [FleetController::class, 'startDelivery'])->name('api.shipment.start');
    Route::post('/api/shipments/{shipment}/confirm-delivery', [FleetController::class, 'confirmDelivery'])->name('api.shipment.confirm');

    // ── Activity Log (admin + manager) ────────────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/performance', [PerformanceController::class, 'index'])->name('performance');
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log');
        Route::get('/api/activity-log/latest', [ActivityLogController::class, 'latest'])->name('api.activity-log.latest');
        Route::get('/api/activity-log/{type}/{id}', [ActivityLogController::class, 'forSubject'])->name('api.activity-log.subject');
    });

    // ── Origin Locations (admin + manager) ───────────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/origins', [OriginLocationController::class, 'index'])->name('origins');
        Route::post('/origins', [OriginLocationController::class, 'store'])->name('origins.store');
        Route::put('/origins/{origin}', [OriginLocationController::class, 'update'])->name('origins.update');
        Route::delete('/origins/{origin}', [OriginLocationController::class, 'destroy'])->name('origins.destroy');
        Route::get('/api/origins', [OriginLocationController::class, 'list'])->name('api.origins.list');
    });

    // ── User Management (admin only) ──────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Root redirect
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => auth()->check()
    ? redirect()->route('fleet.dashboard')
    : redirect()->route('auth.login')
);
