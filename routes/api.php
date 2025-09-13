<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Models\Enrollee;
use App\Http\Controllers\Api\V1\EnrolleeController;
use App\Exports\EnrolleesExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\EnrolleeTypeController;
use App\Http\Controllers\Api\V1\BankController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\PremiumController;
use App\Http\Controllers\Api\V1\FundingTypeController;
use App\Http\Controllers\Api\V1\BenefactorController;
use App\Http\Controllers\Api\V1\LgaController;
use App\Http\Controllers\Api\V1\WardController;
use App\Http\Controllers\Api\V1\VillageController;
use App\Http\Controllers\Api\V1\AccountDetailController;
use App\Http\Controllers\Api\V1\EmploymentDetailController;
use App\Http\Controllers\Api\V1\AuditTrailController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DesignationController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\PACodeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Auth routes
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('forget-password', [AuthController::class, 'forgetPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::get('user', [AuthController::class, 'user'])->middleware('auth:sanctum');

// Dashboard routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('dashboard/enrollee-stats', [DashboardController::class, 'enrolleeStats']);
    Route::get('dashboard/facility-stats', [DashboardController::class, 'facilityStats']);
    Route::get('dashboard/chart-data', [DashboardController::class, 'chartData']);
    Route::get('dashboard/recent-activities', [DashboardController::class, 'recentActivities']);
    Route::get('dashboard/status-options', [DashboardController::class, 'getStatusOptions']);
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Enrollee routes
    Route::apiResource('enrollees', EnrolleeController::class);
    Route::post('enrollees/{enrollee}/upload-passport', [EnrolleeController::class, 'uploadPassport']);
    Route::put('enrollees/{enrollee}/status', [EnrolleeController::class, 'updateStatus']);
    Route::get('enrollees/{enrollee}/statistics', [EnrolleeController::class, 'getStatistics']);
    Route::get('enrollees-export', function (Request $request) {
        $filename = 'enrollees_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        return Excel::download(new EnrolleesExport($request), $filename);
    });
    Route::get('enrollees/{enrollee}/export-pdf', function (Enrollee $enrollee) {
        $enrollee->load([
            'enrolleeType', 'facility', 'lga', 'ward', 'village',
            'premium', 'employmentDetail', 'fundingType', 'benefactor',
            'creator', 'approver'
        ]);

        $pdf = Pdf::loadView('enrollee-profile', compact('enrollee'));
        $filename = 'enrollee_' . $enrollee->enrollee_id . '_' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    });

    // Role and permission routes
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
    // Assign permissions to roles
    Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

    // User routes
    Route::apiResource('users', UserController::class);
    Route::get('users-with-roles', [UserController::class, 'withRoles']);
    Route::post('users/{user}/roles', [UserController::class, 'syncRoles']);

    Route::apiResource('enrollee-types', EnrolleeTypeController::class);
    Route::apiResource('banks', BankController::class);
    Route::apiResource('facilities', FacilityController::class);
    Route::get('facilities/{facility}/enrollees', [FacilityController::class, 'enrollees']);
    Route::apiResource('premiums', PremiumController::class);
    Route::apiResource('funding-types', FundingTypeController::class);
    Route::apiResource('benefactors', BenefactorController::class);
    Route::apiResource('lgas', LgaController::class);
    Route::apiResource('wards', WardController::class);
    Route::apiResource('villages', VillageController::class);
    Route::apiResource('account-details', AccountDetailController::class);
    Route::apiResource('employment-details', EmploymentDetailController::class);
    Route::apiResource('audit-trails', AuditTrailController::class);

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('designations', DesignationController::class);
    Route::apiResource('staff', StaffController::class);

    // PAS (Pre-Authorization System) routes
    Route::prefix('pas')->group(function () {
        // Referral routes
        Route::get('referrals', [ReferralController::class, 'index']);
        Route::post('referrals', [ReferralController::class, 'store']);
        Route::get('referrals/{referral}', [ReferralController::class, 'show']);
        Route::post('referrals/{referral}/approve', [ReferralController::class, 'approve']);
        Route::post('referrals/{referral}/deny', [ReferralController::class, 'deny']);
        Route::get('referrals-statistics', [ReferralController::class, 'statistics']);

        // PA Code routes
        Route::get('pa-codes', [PACodeController::class, 'index']);
        Route::post('referrals/{referral}/generate-pa-code', [PACodeController::class, 'generateFromReferral']);
        Route::get('pa-codes/{paCode}', [PACodeController::class, 'show']);
        Route::post('pa-codes/{paCode}/mark-used', [PACodeController::class, 'markAsUsed']);
        Route::post('pa-codes/{paCode}/cancel', [PACodeController::class, 'cancel']);
        Route::post('pa-codes/verify', [PACodeController::class, 'verify']);
        Route::get('pa-codes-statistics', [PACodeController::class, 'statistics']);
    });
});