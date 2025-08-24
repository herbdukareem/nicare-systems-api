<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EnrolleeController;
use App\Http\Controllers\PremiumController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\LgaController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\EnrolleeTypeController;
use App\Http\Controllers\DashboardController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public routes for PIN validation
Route::post('/premiums/validate-pin', [PremiumController::class, 'validatePin']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [ApiAuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Enrollees
    Route::apiResource('enrollees', EnrolleeController::class);
    Route::post('/enrollees/{enrollee}/approve', [EnrolleeController::class, 'approve']);
    Route::post('/enrollees/bulk-import', [EnrolleeController::class, 'bulkImport']);
    Route::get('/enrollees/{enrollee}/audit-trail', [EnrolleeController::class, 'auditTrail']);

    // Premiums
    Route::apiResource('premiums', PremiumController::class)->only(['index', 'show']);
    Route::post('/premiums/generate-pins', [PremiumController::class, 'generatePins']);
    Route::post('/premiums/redeem-pin', [PremiumController::class, 'redeemPin']);
    Route::post('/premiums/bulk-upload', [PremiumController::class, 'bulkUpload']);
    Route::get('/premiums/stats', [PremiumController::class, 'getStats']);

    // Facilities
    Route::apiResource('facilities', FacilityController::class);
    Route::get('/facilities/by-location/{lga}/{ward}', [FacilityController::class, 'byLocation']);

    // LGAs and Wards
    Route::apiResource('lgas', LgaController::class);
    Route::get('/lgas/{lga}/wards', [WardController::class, 'byLga']);
    Route::apiResource('wards', WardController::class);

    // Enrollee Types
    Route::apiResource('enrollee-types', EnrolleeTypeController::class);
});

