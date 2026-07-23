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
use App\Http\Controllers\Api\V1\FundingTypeController;
use App\Http\Controllers\Api\V1\BenefactorController;
use App\Http\Controllers\Api\V1\BenefitPackageController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskCategoryController;
use App\Http\Controllers\Api\V1\LgaController;
use App\Http\Controllers\Api\V1\WardController;
use App\Http\Controllers\Api\V1\VillageController;
use App\Http\Controllers\Api\V1\AccountDetailController;
use App\Http\Controllers\Api\V1\EmploymentDetailController;
use App\Http\Controllers\Api\V1\AuditTrailController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DesignationController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\V1\DrugController;
use App\Http\Controllers\Api\V1\DrugDetailController;
use App\Http\Controllers\Api\V1\LaboratoryDetailController;
use App\Http\Controllers\Api\V1\ProfessionalServiceDetailController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\ServiceBundleController;
use App\Http\Controllers\Api\V1\BundleComponentController;

use App\Http\Controllers\Api\V1\CaseTypeController;
use App\Http\Controllers\Api\V1\DOFacilityController;
use App\Http\Controllers\Api\V1\DODashboardController;
use App\Http\Controllers\Api\V1\DocumentRequirementController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\ClaimLineController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentBatchController;
use App\Http\Controllers\Api\ReportingController;
use App\Http\Controllers\Api\EligibilityLookupController;
use App\Http\Controllers\Api\PayrollBatchController;
use App\Http\Controllers\Api\PremiumDashboardController;
use App\Http\Controllers\Api\PremiumMetadataController;
use App\Http\Controllers\Api\PremiumPinController;
use App\Http\Controllers\Api\PremiumPlanController;
use App\Http\Controllers\Api\PremiumPurchaseController;
use App\Http\Controllers\PAS\PACodeController;
use App\Http\Controllers\Api\FinanceDashboardController;
use App\Http\Controllers\Api\FacilityDashboardController;
use App\Http\Controllers\Api\ClaimsDashboardController;
use App\Http\Controllers\Api\CapitationController;
use App\Http\Controllers\Api\MobileSyncController;
use App\Http\Controllers\Api\NinProviderConfigurationController;
use App\Http\Controllers\Api\OrganizationSettingsController;
use App\Http\Controllers\Api\PaymentGatewayConfigurationController;
use App\Http\Controllers\Api\PublicEnrollmentController;
use App\Http\Controllers\Api\PublicEnrollmentPaymentController;
use App\Http\Controllers\Api\PublicPremiumPinController;
use App\Http\Controllers\Api\EnrolleeImportController;
use App\Http\Controllers\Api\EnrolleeController as EnrolleeApiController;
use App\Http\Controllers\Api\ExtendedReportingController;
use App\Http\Controllers\Api\EnrollmentFormSchemaController;
use App\Http\Controllers\Api\MobileEnrollmentMonitorController;
use App\Http\Controllers\Api\MobileV1Controller;
use App\Http\Controllers\Api\OfficerDeviceController;
use App\Http\Controllers\Api\OfficerEnrollmentAssignmentController;

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

// Test route to verify API is working
Route::get('test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'timestamp' => now(),
        'csrf_token' => csrf_token(),
    ]);
});

// ── Enrollee portal auth routes ────────────────────────────────────────────────
use App\Http\Controllers\Api\EnrolleeAuthController;

Route::get('organization-settings', [OrganizationSettingsController::class, 'show'])->middleware('throttle:60,1');

Route::post('enroll/login', [EnrolleeAuthController::class, 'login'])->middleware('security');
Route::get('enroll/plans', [EnrolleeAuthController::class, 'plans']);
Route::prefix('public/enrollment')->middleware(['security'])->group(function () {
    Route::get('metadata', [PublicEnrollmentController::class, 'metadata'])->middleware('throttle:30,1');
    Route::post('applications', [PublicEnrollmentController::class, 'store'])->middleware('throttle:10,1');
    Route::get('payments/{reference}/verify', [PublicEnrollmentPaymentController::class, 'verify'])->middleware('throttle:20,1');
    Route::post('pin-purchases', [PublicPremiumPinController::class, 'store'])->middleware('throttle:10,1');
    Route::get('pin-purchases/{reference}/verify', [PublicPremiumPinController::class, 'verify'])->middleware('throttle:20,1');
    Route::get('pin-purchases/{reference}/docket', [PublicPremiumPinController::class, 'docket'])->middleware('throttle:20,1');
});

Route::middleware(['auth:sanctum', 'enrollee'])->prefix('enroll')->group(function () {
    Route::post('logout',           [EnrolleeAuthController::class, 'logout']);
    Route::get('me',                [EnrolleeAuthController::class, 'me']);
    Route::post('change-password',  [EnrolleeAuthController::class, 'changePassword']);
});

// Auth routes (without middleware to avoid CSRF issues)
Route::post('login', [AuthController::class, 'login'])->withoutMiddleware(['web'])->middleware('security');
Route::post('register', [AuthController::class, 'register']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('forget-password', [AuthController::class, 'forgetPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::get('user', [AuthController::class, 'user'])->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->prefix('mobile/v1')->group(function () {
    Route::post('devices/register', [MobileV1Controller::class, 'registerDevice']);
    Route::get('bootstrap', [MobileV1Controller::class, 'bootstrap'])->middleware('permission:any,mobile-sync.push,mobile-sync.status,enrollees.create');
    Route::get('metadata', [MobileV1Controller::class, 'metadata'])->middleware('permission:any,mobile-sync.push,mobile-sync.status,enrollees.create');
    Route::post('pin-purchases', [MobileV1Controller::class, 'createPinPurchase'])->middleware('permission:any,mobile-sync.push,enrollees.create,premium.pin.generate,premium.pin.sell');
    Route::get('pin-purchases/{reference}/verify', [MobileV1Controller::class, 'verifyPinPurchase'])->middleware('permission:any,mobile-sync.push,enrollees.create,premium.pin.generate,premium.pin.sell');
    Route::post('pins/validate', [MobileV1Controller::class, 'validatePin'])->middleware('permission:any,mobile-sync.push,enrollees.create,premium.pin.view');
    Route::post('nin/verify', [MobileV1Controller::class, 'verifyNin'])->middleware('permission:any,mobile-sync.push,enrollees.create,enrollee.nin.verify');
    Route::post('enrollments/sync', [MobileV1Controller::class, 'syncEnrollments'])->middleware('permission:any,mobile-sync.push,enrollees.create');
    Route::post('enrollments/status', [MobileV1Controller::class, 'enrollmentStatuses'])->middleware('permission:any,mobile-sync.status,enrollees.create');
    Route::get('enrollments/sync/{batch}', [MobileV1Controller::class, 'syncStatus'])->middleware('permission:any,mobile-sync.status,enrollees.create');
    Route::get('enrollments/failed', [MobileV1Controller::class, 'failed'])->middleware('permission:any,mobile-sync.status,enrollees.create');
    Route::post('enrollments/{record}/attachments', [MobileV1Controller::class, 'uploadAttachment'])->middleware('permission:any,mobile-sync.push,enrollees.create');
});

// Dashboard routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('dashboard/enrollee-stats', [DashboardController::class, 'enrolleeStats']);
    Route::get('dashboard/facility-stats', [DashboardController::class, 'facilityStats']);
    Route::get('dashboard/chart-data', [DashboardController::class, 'chartData']);
    Route::get('dashboard/recent-activities', [DashboardController::class, 'recentActivities']);
    Route::get('dashboard/status-options', [DashboardController::class, 'getStatusOptions']);
    Route::get('dashboard/enrollment-trend', [DashboardController::class, 'enrollmentTrend']);
    Route::get('dashboard/wards-by-lga', [DashboardController::class, 'wardsByLga']);
    Route::get('dashboard/capitation-summary', [DashboardController::class, 'capitationSummary']);
});

Route::middleware('auth:sanctum')->group(function () {


    // DOFacility Management - Specific routes must come before apiResource
    Route::middleware('permission:facilities.assign')->group(function () {
        Route::get('do-facilities/desk-officers', [DOFacilityController::class, 'getDeskOfficers']);
        Route::get('do-facilities/facilities', [DOFacilityController::class, 'getFacilities']);
        Route::get('do-facilities/user/{userId}/facilities', [DOFacilityController::class, 'getUserFacilities']);
        Route::apiResource('do-facilities', DOFacilityController::class);
    });

    // DO Dashboard routes (desk officers, facility admins, facility users)
    Route::prefix('do-dashboard')->middleware('permission:dashboard.desk_officer.view,dashboard.facility.view')->group(function () {
        Route::get('overview', [DODashboardController::class, 'overview']);
        Route::get('referrals', [DODashboardController::class, 'getReferrals']);
        Route::get('pa-codes', [DODashboardController::class, 'getPACodes']);
        Route::post('validate-utn', [DODashboardController::class, 'validateUTN']);
    });

    // Enrollee routes
    Route::get('enrollees/pending-approval', [EnrolleeController::class, 'pendingApproval'])
        ->middleware('permission:any,enrollees.view,enrollee.approve,enrollee.nin.verify');
    Route::get('enrollees/bulk-enrollment-slip', [EnrolleeController::class, 'bulkEnrollmentSlip'])
        ->middleware('permission:any,enrollees.view,enrollee.print-bulk-slip');
    Route::get('enrollees/bulk-id-card', [EnrolleeController::class, 'bulkIdCard'])
        ->middleware('permission:any,enrollees.view,enrollee.print-id-card');
    Route::post('enrollees/{enrollee}/verify-nin', [EnrolleeController::class, 'verifyNin'])
        ->middleware('permission:any,enrollee.nin.verify,enrollee.approve');
    Route::post('enrollees/{enrollee}/approve', [EnrolleeController::class, 'approve'])
        ->middleware('permission:enrollee.approve');
    Route::get('enrollees/{enrollee}/id-card', [EnrolleeController::class, 'idCard'])
        ->middleware('permission:any,enrollees.view,enrollee.print-id-card');
    Route::get('enrollees', [EnrolleeController::class, 'index'])
        ->middleware('permission:any,enrollees.view,enrollee.status.change');
    Route::get('enrollees/integrity/summary', [EnrolleeController::class, 'integritySummary'])
        ->middleware('permission:any,enrollees.view,enrollees.update,enrollee.approve,enrollee.nin.verify,enrollee.status.change');
    Route::get('enrollees/duplicates', [EnrolleeController::class, 'listDuplicates'])
        ->middleware('permission:any,enrollees.view,enrollees.update,enrollee.approve,enrollee.nin.verify,enrollee.status.change');
    Route::post('enrollees/duplicates/{flag}/resolve', [EnrolleeController::class, 'resolveDuplicate'])
        ->middleware('permission:any,enrollees.update,enrollees.edit,enrollee.approve,enrollee.nin.verify');
    Route::post('enrollees/bulk-update-status', [EnrolleeController::class, 'bulkUpdateStatus'])
        ->middleware('permission:any,enrollees.update,enrollees.edit,enrollee.approve,enrollee.status.change');
    Route::post('enrollees', [EnrolleeController::class, 'store'])
        ->middleware('permission:enrollees.create');
    Route::get('enrollees/{enrollee}', [EnrolleeController::class, 'show'])
        ->middleware('permission:any,enrollees.view,enrollee.status.change');
    Route::match(['put', 'patch'], 'enrollees/{enrollee}', [EnrolleeController::class, 'update'])
        ->middleware('permission:any,enrollees.update,enrollees.edit');
    Route::delete('enrollees/{enrollee}', [EnrolleeController::class, 'destroy'])
        ->middleware('permission:any,enrollees.delete,enrollee.delete');
    Route::post('enrollees/{enrollee}/upload-passport', [EnrolleeController::class, 'uploadPassport'])
        ->middleware('permission:any,enrollees.update,enrollees.edit');
    Route::put('enrollees/{enrollee}/status', [EnrolleeController::class, 'updateStatus'])
        ->middleware('permission:any,enrollees.update,enrollees.edit,enrollee.status.change');
    Route::get('enrollees/{enrollee}/statistics', [EnrolleeController::class, 'getStatistics'])
        ->middleware('permission:enrollees.view');
    Route::middleware('permission:enrollees.export')->get('enrollees-export', function (Request $request) {
        $parts = ['enrollees'];
        $nameFor = function (string $model, string $id): string {
            $name = $model::whereKey($id)->value('name') ?: $id;
            return trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $name)), '-');
        };
        $models = [
            'lga_id' => ['lga', \App\Models\Lga::class],
            'facility_id' => ['facility', \App\Models\Facility::class],
            'funding_type_id' => ['funding', \App\Models\FundingType::class],
            'benefactor_id' => ['benefactor', \App\Models\Benefactor::class],
            'enrollment_phase_id' => ['phase', \App\Models\EnrollmentPhase::class],
        ];
        foreach ($models as $key => [$label, $model]) {
            if ($request->filled($key)) {
                $value = $nameFor($model, (string) $request->get($key));
                $parts[] = "{$label}-{$value}";
            }
        }
        $parts[] = now()->format('Y-m-d');
        $filename = implode('_', $parts) . '.xlsx';
        return Excel::download(new EnrolleesExport($request), $filename);
    });
    Route::get('enrollees/{enrollee}/export-pdf', function (Enrollee $enrollee) {
        $enrollee->load([
            'enrolleeType', 'facility', 'lga', 'ward', 'village',
            'premiumPlan', 'premiumPin', 'employmentDetail', 'fundingType', 'benefactor',
            'creator', 'approver'
        ]);

        $pdf = Pdf::loadView('enrollee-profile', compact('enrollee'));
        $filename = 'enrollee_' . $enrollee->enrollee_id . '_' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    });

    // Role and permission routes
    Route::middleware('permission:roles.view,permissions.view')->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);
        Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
        Route::get('roles-with-user-counts', [RoleController::class, 'withUserCounts']);
        Route::post('roles/{role}/clone', [RoleController::class, 'clone']);
        Route::delete('roles/bulk-delete', [RoleController::class, 'bulkDelete']);
        Route::get('permissions/by-category', [PermissionController::class, 'byCategory']);
        Route::post('permissions/bulk-create', [PermissionController::class, 'bulkCreate']);
        Route::delete('permissions/bulk-delete', [PermissionController::class, 'bulkDelete']);
    });

    // User routes
    Route::middleware('permission:users.view,users.create')->group(function () {
        Route::apiResource('users', UserController::class);
    Route::get('users-with-roles', [UserController::class, 'withRoles']);
    Route::post('users/{user}/roles', [UserController::class, 'syncRoles']);
    Route::post('users/{user}/switch-role', [UserController::class, 'switchRole']);
    Route::get('users/available-modules', [UserController::class, 'getAvailableModules']);
    Route::get('users/{user}/profile', [UserController::class, 'profile']);
    Route::patch('users/{user}/password', [UserController::class, 'updatePassword']);
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::post('users/bulk-update-status', [UserController::class, 'bulkUpdateStatus']);
    Route::delete('users/bulk-delete', [UserController::class, 'bulkDelete']);
    // User permissions routes
    Route::post('users/{user}/permissions', [UserController::class, 'syncPermissions']);
    Route::get('users/{user}/permissions', [UserController::class, 'getPermissions']);
    // Profile management routes
    Route::get('users/{user}/activities', [UserController::class, 'activities']);
    Route::post('users/{user}/avatar', [UserController::class, 'uploadAvatar']);
    Route::patch('users/{user}/toggle-2fa', [UserController::class, 'toggle2FA']);
    Route::post('users/{user}/revoke-sessions', [UserController::class, 'revokeAllSessions']);
    // Advanced user features
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate']);
    Route::post('users/stop-impersonation', [UserController::class, 'stopImpersonation']);
    Route::get('users/export', [UserController::class, 'export']);
    Route::post('users/import', [UserController::class, 'import']);
    Route::get('users/{user}/activity-stats', [UserController::class, 'activityStats']);
    }); // end users group

    Route::apiResource('enrollee-types', EnrolleeTypeController::class);
    Route::apiResource('banks', BankController::class);
    Route::get('facilities/{facility}/enrollees', [FacilityController::class, 'enrollees']);
    Route::apiResource('facilities', FacilityController::class);
    Route::get('referrals', [ReferralController::class, 'index'])->middleware('permission:any,referrals.view,referrals.manage,referrals.approve,referrals.reject');
    Route::post('referrals', [ReferralController::class, 'store'])->middleware('permission:any,referrals.create,referrals.submit,referrals.manage');
    Route::get('referrals/{referral}', [ReferralController::class, 'show'])->middleware('permission:any,referrals.view,referrals.manage,referrals.approve,referrals.reject');
    Route::post('referrals/{referral}/approve', [ReferralController::class, 'approve'])->middleware('permission:any,referrals.approve,referrals.manage');
    Route::post('referrals/{referral}/reject', [ReferralController::class, 'reject'])->middleware('permission:any,referrals.reject,referrals.deny,referrals.manage');
    Route::prefix('premium')->group(function () {
        Route::get('dashboard', [PremiumDashboardController::class, 'index']);
        Route::get('metadata', [PremiumMetadataController::class, 'index']);
        Route::apiResource('plans', PremiumPlanController::class)->parameters(['plans' => 'premiumPlan']);

        Route::get('pins', [PremiumPinController::class, 'index']);
        Route::post('pins/generate', [PremiumPinController::class, 'generate']);
        Route::post('pins/validate', [PremiumPinController::class, 'validatePin']);
        Route::get('pins/{premiumPin}', [PremiumPinController::class, 'show']);
        Route::post('pins/{premiumPin}/sell', [PremiumPinController::class, 'sell']);
        Route::post('pins/{premiumPin}/use', [PremiumPinController::class, 'use']);
        Route::post('pins/{premiumPin}/cancel', [PremiumPinController::class, 'cancel']);

        Route::apiResource('purchases', PremiumPurchaseController::class)->only(['index', 'store', 'show'])->parameters(['purchases' => 'premiumPurchase']);
        Route::post('purchases/{premiumPurchase}/confirm', [PremiumPurchaseController::class, 'confirm']);
        Route::post('purchases/{premiumPurchase}/cancel', [PremiumPurchaseController::class, 'cancel']);
        Route::post('purchases/{premiumPurchase}/checkout', [PremiumPurchaseController::class, 'initializeCheckout']);
        Route::post('purchases/{premiumPurchase}/verify', [PremiumPurchaseController::class, 'verify']);

        Route::apiResource('payroll-batches', PayrollBatchController::class)->only(['index', 'store'])->parameters(['payroll-batches' => 'payrollBatch']);
        Route::post('payroll-batches/{payrollBatch}/approve', [PayrollBatchController::class, 'approve']);

        Route::get('eligibility', [EligibilityLookupController::class, 'show']);
    });

    Route::apiResource('funding-types', FundingTypeController::class);
    Route::apiResource('benefactors', BenefactorController::class);
    Route::apiResource('benefit-packages', BenefitPackageController::class)->parameters(['benefit-packages' => 'benefitPackage']);
    Route::get('settings/nin-provider', [NinProviderConfigurationController::class, 'show'])
        ->middleware('permission:any,settings.nin.manage,settings.edit');
    Route::put('settings/nin-provider', [NinProviderConfigurationController::class, 'update'])
        ->middleware('permission:any,settings.nin.manage,settings.edit');
    Route::get('settings/organization', [OrganizationSettingsController::class, 'show'])
        ->middleware('permission:any,settings.organization.manage,settings.edit');
    Route::put('settings/organization', [OrganizationSettingsController::class, 'update'])
        ->middleware('permission:any,settings.organization.manage,settings.edit');
    Route::post('settings/organization/logo', [OrganizationSettingsController::class, 'uploadLogo'])
        ->middleware('permission:any,settings.organization.manage,settings.edit');
    Route::delete('settings/organization/logo', [OrganizationSettingsController::class, 'removeLogo'])
        ->middleware('permission:any,settings.organization.manage,settings.edit');
    Route::get('settings/payment-gateways', [PaymentGatewayConfigurationController::class, 'show'])
        ->middleware('permission:any,settings.payment-gateway.manage,settings.edit');
    Route::put('settings/payment-gateways', [PaymentGatewayConfigurationController::class, 'update'])
        ->middleware('permission:any,settings.payment-gateway.manage,settings.edit');
    Route::apiResource('enrollment-form-schemas', EnrollmentFormSchemaController::class)
        ->parameters(['enrollment-form-schemas' => 'schema'])
        ->middleware('permission:any,users.view,settings.edit,enrollees.create');
    Route::post('enrollment-form-schemas/{schema}/publish', [EnrollmentFormSchemaController::class, 'publish'])
        ->middleware('permission:any,users.view,settings.edit,enrollees.create');
    Route::post('enrollment-form-schemas/{schema}/revoke', [EnrollmentFormSchemaController::class, 'revoke'])
        ->middleware('permission:any,users.view,settings.edit,enrollees.create');
    Route::get('officer-devices', [OfficerDeviceController::class, 'index'])
        ->middleware('permission:any,users.view,settings.edit');
    Route::post('officer-devices/{device}/revoke', [OfficerDeviceController::class, 'revoke'])
        ->middleware('permission:any,users.view,settings.edit');
    Route::get('officer-enrollment-assignments', [OfficerEnrollmentAssignmentController::class, 'index'])
        ->middleware('permission:any,users.view,settings.edit,settings.mobile-device.manage');
    Route::post('officer-enrollment-assignments', [OfficerEnrollmentAssignmentController::class, 'store'])
        ->middleware('permission:any,users.view,settings.edit,settings.mobile-device.manage');
    Route::patch('officer-enrollment-assignments/{assignment}', [OfficerEnrollmentAssignmentController::class, 'update'])
        ->middleware('permission:any,users.view,settings.edit,settings.mobile-device.manage');
    Route::delete('officer-enrollment-assignments/{assignment}', [OfficerEnrollmentAssignmentController::class, 'destroy'])
        ->middleware('permission:any,users.view,settings.edit,settings.mobile-device.manage');
    Route::patch('users/{user}/mobile-enrollment-status', [OfficerEnrollmentAssignmentController::class, 'setOfficerEnrollmentStatus'])
        ->middleware('permission:any,users.view,settings.edit,settings.mobile-device.manage');
    Route::get('mobile-enrollment-monitor', [MobileEnrollmentMonitorController::class, 'index'])
        ->middleware('permission:any,mobile-sync.push,mobile-sync.status');
    Route::get('mobile-enrollment-monitor/{record}', [MobileEnrollmentMonitorController::class, 'show'])
        ->middleware('permission:any,mobile-sync.push,mobile-sync.status');
    Route::apiResource('lgas', LgaController::class);
    Route::get('lgas/{lga}/wards', [LgaController::class, 'wards']);
    Route::apiResource('wards', WardController::class);
    Route::apiResource('villages', VillageController::class);
    Route::apiResource('account-details', AccountDetailController::class);
    Route::apiResource('employment-details', EmploymentDetailController::class);
    Route::apiResource('audit-trails', AuditTrailController::class);

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('designations', DesignationController::class);
    Route::apiResource('staff', StaffController::class);

    // Drug Management routes (Legacy - will be deprecated)
    Route::apiResource('drugs', DrugController::class);
    Route::post('drugs/import', [DrugController::class, 'import']);
    Route::get('drugs-export', [DrugController::class, 'export']);
    Route::get('drugs-template', [DrugController::class, 'downloadTemplate']);
    Route::get('drugs-statistics', [DrugController::class, 'statistics']);

    // Drug Detail Management routes (New polymorphic model)
    Route::apiResource('drug-details', DrugDetailController::class);
    Route::get('drug-details-statistics', [DrugDetailController::class, 'statistics']);
    Route::get('drug-dosage-forms', [DrugDetailController::class, 'dosageForms']);
    Route::get('drug-routes', [DrugDetailController::class, 'routes']);

    // Laboratory Detail Management routes (New polymorphic model)
    Route::apiResource('laboratory-details', LaboratoryDetailController::class);
    Route::get('laboratory-details-statistics', [LaboratoryDetailController::class, 'statistics']);
    Route::get('laboratory-specimen-types', [LaboratoryDetailController::class, 'specimenTypes']);
    Route::get('laboratory-test-categories', [LaboratoryDetailController::class, 'testCategories']);

    // Professional Service Detail Management routes (New polymorphic model)
    Route::apiResource('professional-service-details', ProfessionalServiceDetailController::class);
    Route::get('professional-service-details-statistics', [ProfessionalServiceDetailController::class, 'statistics']);
    Route::get('professional-service-specialties', [ProfessionalServiceDetailController::class, 'specialties']);
    Route::get('professional-service-provider-types', [ProfessionalServiceDetailController::class, 'providerTypes']);
    Route::get('professional-service-anesthesia-types', [ProfessionalServiceDetailController::class, 'anesthesiaTypes']);

    // Case Management routes
    Route::apiResource('cases', CaseController::class);
    Route::post('cases/import', [CaseController::class, 'import']);
    Route::get('cases-export', [CaseController::class, 'export']);
    Route::get('cases-template', [CaseController::class, 'downloadTemplate']);
    Route::get('cases-statistics', [CaseController::class, 'statistics']);
    Route::get('cases-groups', [CaseController::class, 'getGroups']);

    // Service Bundle Management routes
    Route::apiResource('service-bundles', ServiceBundleController::class);
    Route::get('service-bundles-statistics', [ServiceBundleController::class, 'statistics']);

    // Bundle Component Management routes
    Route::apiResource('bundle-components', BundleComponentController::class);
    Route::post('bundle-components/bulk', [BundleComponentController::class, 'bulkStore']);
    Route::get('bundle-components-statistics', [BundleComponentController::class, 'statistics']);
    Route::get('bundle-components-export', [BundleComponentController::class, 'export']);
    Route::get('bundle-components-template', [BundleComponentController::class, 'downloadTemplate']);
    Route::post('bundle-components-import', [BundleComponentController::class, 'import']);

    // Service Type and Case Type routes
    Route::apiResource('case-types', CaseTypeController::class)->only(['index', 'show']);

    // Document Requirements Management
    Route::prefix('document-requirements')->group(function () {
        Route::get('/', [DocumentRequirementController::class, 'index']);
        Route::get('/for-referral', [DocumentRequirementController::class, 'forReferral']);
        Route::get('/for-pa-code', [DocumentRequirementController::class, 'forPACode']);
        Route::post('/', [DocumentRequirementController::class, 'store']);
        Route::get('/{documentRequirement}', [DocumentRequirementController::class, 'show']);
        Route::put('/{documentRequirement}', [DocumentRequirementController::class, 'update']);
        Route::delete('/{documentRequirement}', [DocumentRequirementController::class, 'destroy']);
        Route::post('/{documentRequirement}/toggle-status', [DocumentRequirementController::class, 'toggleStatus']);
        Route::post('/reorder', [DocumentRequirementController::class, 'reorder']);
    });

    // Claims Automation Routes
    Route::prefix('claims-automation')->group(function () {
        // Admission Management
        Route::prefix('admissions')->group(function () {
            Route::get('/', [AdmissionController::class, 'index'])->middleware('permission:any,admissions.view,admissions.manage');
            Route::post('/', [AdmissionController::class, 'store'])->middleware('permission:any,admissions.create,admissions.manage');
            Route::get('/check/{referralId}', [AdmissionController::class, 'checkAdmissionEligibility'])->middleware('permission:any,admissions.view,admissions.create,admissions.manage');
            Route::get('/enrollee/{enrolleeId}', [AdmissionController::class, 'getActiveAdmission'])->middleware('permission:any,admissions.view,admissions.manage');
            Route::get('/{admission}', [AdmissionController::class, 'show'])->middleware('permission:any,admissions.view,admissions.manage');
            Route::post('/{admission}/discharge', [AdmissionController::class, 'discharge'])->middleware('permission:any,admissions.discharge,admissions.manage');
        });

        // Claim Management
        Route::prefix('claims')->group(function () {
            Route::get('/', [ClaimController::class, 'index'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
            Route::post('/', [ClaimController::class, 'store'])->middleware('permission:any,claims.create,claims.process,claims.automate');
            Route::get('/{claim}', [ClaimController::class, 'show'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
            Route::get('/{claim}/full-details', [ClaimController::class, 'showFullDetails'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
            Route::get('/{claim}/slip', [ClaimController::class, 'downloadSlip'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
            Route::post('/{claim}/submit', [ClaimController::class, 'submit'])->middleware('permission:any,claims.submit,claims.process,claims.automate');
            Route::post('/{claim}/validate', [ClaimController::class, 'validateClaim'])->middleware('permission:any,claims.review,claims.process,claims.automate');
            Route::post('/{claim}/approve', [ClaimController::class, 'approve'])->middleware('permission:any,claims.approve,claims.reviewer.approve,claims.approver.approve');
            Route::post('/{claim}/reject', [ClaimController::class, 'reject'])->middleware('permission:any,claims.reject,claims.reviewer.reject,claims.approver.reject');
            Route::get('/{claim}/summary', [ClaimController::class, 'summary'])->middleware('permission:any,claims.view,claims.review,claims.approve');
            // Review and batch operations
            Route::post('/{claim}/review', [ClaimController::class, 'review'])->middleware('permission:any,claims.review,claims.reviewer.review,claims.approver.review');
            Route::post('/batch-approve', [ClaimController::class, 'batchApprove'])->middleware('permission:any,claims.approve,claims.reviewer.approve,claims.approver.approve');
            Route::post('/batch-reject', [ClaimController::class, 'batchReject'])->middleware('permission:any,claims.reject,claims.reviewer.reject,claims.approver.reject');

            // Claim Lines
            Route::post('/{claim}/lines/bundle', [ClaimLineController::class, 'addBundleTreatment'])->middleware('permission:any,claims.create,claims.process,claims.automate');
            Route::post('/{claim}/lines/ffs', [ClaimLineController::class, 'addFFSTreatment'])->middleware('permission:any,claims.create,claims.process,claims.automate');
            Route::get('/{claim}/classification', [ClaimLineController::class, 'getClassification'])->middleware('permission:any,claims.view,claims.review,claims.process');
        });

        // Claim Line Management
        Route::prefix('claim-lines')->group(function () {
            Route::get('/{claimLine}', [ClaimLineController::class, 'show'])->middleware('permission:any,claims.view,claims.review,claims.approve');
            Route::delete('/{claimLine}', [ClaimLineController::class, 'destroy'])->middleware('permission:any,claims.create,claims.process,claims.automate');
        });

        // Payment Batch Management
        Route::prefix('payment-batches')->group(function () {
            Route::get('/', [PaymentBatchController::class, 'index'])->middleware('permission:any,payment_batches.view,payment_batches.manage');
            Route::post('/', [PaymentBatchController::class, 'store'])->middleware('permission:any,payment_batches.create,payment_batches.manage');
            Route::get('/approved-claims', [PaymentBatchController::class, 'getApprovedClaims'])->middleware('permission:any,payment_batches.view,payment_batches.manage');
            Route::get('/{batch}', [PaymentBatchController::class, 'show'])->middleware('permission:any,payment_batches.view,payment_batches.manage');
            Route::post('/{batch}/process', [PaymentBatchController::class, 'process'])->middleware('permission:any,payment_batches.approve,payment_batches.manage,payments.process');
            Route::post('/{batch}/mark-paid', [PaymentBatchController::class, 'markPaid'])->middleware('permission:any,payment_batches.approve,payment_batches.manage,payments.process');
            Route::get('/{batch}/receipt', [PaymentBatchController::class, 'downloadReceipt'])->middleware('permission:any,payment_batches.view,payment_batches.manage,payments.view');
        });

        // Payment Management
        Route::prefix('payments')->group(function () {
            Route::get('/calculate/{claim}', [PaymentController::class, 'calculate'])->middleware('permission:any,payments.view,payments.process');
            Route::post('/process', [PaymentController::class, 'process'])->middleware('permission:any,payments.process,payments.finalise');
            Route::get('/track/{claim}', [PaymentController::class, 'track'])->middleware('permission:any,payments.view,payments.process');
            Route::get('/facility/{facilityId}/summary', [PaymentController::class, 'facilityPaymentSummary'])->middleware('permission:any,payments.view,payments.process');
        });

        // Reporting
        Route::prefix('reports')->group(function () {
            Route::get('/claims', [ReportingController::class, 'claimsReport'])->middleware('permission:any,reports.view,reports.export');
            Route::get('/payments', [ReportingController::class, 'paymentReport'])->middleware('permission:any,reports.view,reports.export');
            Route::get('/compliance', [ReportingController::class, 'complianceReport'])->middleware('permission:any,reports.view,reports.export');
        });
    });

    // ─── Phase 2: Specialist Dashboards ─────────────────────────────────────────
    Route::get('dashboard/finance', [FinanceDashboardController::class, 'index']);
    Route::get('dashboard/facility', [FacilityDashboardController::class, 'index']);
    Route::get('dashboard/claims', [ClaimsDashboardController::class, 'index']);

    // ─── Phase 2: Capitation ─────────────────────────────────────────────────────
    Route::prefix('capitation')->group(function () {
        Route::get('periods', [CapitationController::class, 'index'])->middleware('permission:capitation.view,capitation.create,capitation.review,capitation.approve,capitation.pay');
        Route::post('periods', [CapitationController::class, 'store'])->middleware('permission:capitation.create');
        Route::get('periods/{capitation}', [CapitationController::class, 'show'])->middleware('permission:capitation.view,capitation.create,capitation.review,capitation.approve,capitation.pay');
        Route::post('periods/{capitation}/compute', [CapitationController::class, 'compute'])->middleware('permission:capitation.compute');
        Route::get('periods/{capitation}/eligible-providers', [CapitationController::class, 'eligibleProviders'])->middleware('permission:capitation.compute');
        Route::get('periods/{capitation}/details', [CapitationController::class, 'details'])->middleware('permission:capitation.view,capitation.review,capitation.approve,capitation.pay');
        Route::post('periods/{capitation}/details/review', [CapitationController::class, 'reviewDetails'])->middleware('permission:capitation.review');
        Route::post('periods/{capitation}/details/approve', [CapitationController::class, 'approveDetails'])->middleware('permission:capitation.approve');
        Route::post('periods/{capitation}/details/pay', [CapitationController::class, 'payDetails'])->middleware('permission:capitation.pay');
        Route::get('periods/{capitation}/breakdown', [CapitationController::class, 'breakdown'])->middleware('permission:capitation.view,capitation.review,capitation.approve,capitation.pay');
        Route::post('periods/{capitation}/finalise', [CapitationController::class, 'finalise'])->middleware('permission:capitation.approve,capitation.finalise');
        Route::post('periods/{capitation}/pay', [CapitationController::class, 'pay'])->middleware('permission:capitation.pay');
        Route::get('periods/{capitation}/export', [CapitationController::class, 'export'])->middleware('permission:capitation.export');
        Route::get('facilities/{facility}/capitation-history', [CapitationController::class, 'facilityHistory'])->middleware('permission:capitation.view');
    });

    // ─── Phase 2: Mobile Sync ────────────────────────────────────────────────────
    Route::prefix('mobile-sync')->middleware('permission:mobile-sync.push,mobile-sync.status')->group(function () {
        Route::post('push', [MobileSyncController::class, 'push']);
        Route::get('status/{syncBatchId}', [MobileSyncController::class, 'status']);
        Route::get('failed', [MobileSyncController::class, 'failed']);
        Route::post('retry/{record}', [MobileSyncController::class, 'retry']);
    });

    // ─── Phase 2: Enrollee Import ────────────────────────────────────────────────
    // These must come BEFORE apiResource('enrollees') to avoid {enrollee} capture
    Route::post('enrollees/import', [EnrolleeImportController::class, 'upload']);
    Route::get('enrollees/import-template', [EnrolleeImportController::class, 'template']);
    Route::get('enrollees/import/{batch}', [EnrolleeImportController::class, 'status']);

    // ─── Phase 2: Enrollee Duplicates ────────────────────────────────────────────

    // ─── Phase 2: Enrollee Facility Transfers ────────────────────────────────────
    Route::post('enrollees/{enrollee}/transfer', [EnrolleeApiController::class, 'transfer']);
    Route::post('enrollees/transfers/{transfer}/approve', [EnrolleeApiController::class, 'approveTransfer']);
    Route::get('enrollees/{enrollee}/transfers', [EnrolleeApiController::class, 'getTransferHistory']);

    // ─── Phase 2: Extended Reports (unified endpoint) ────────────────────────────
    Route::get('reports/{type}', [ExtendedReportingController::class, '__invoke']);

    // Feedback Management
    Route::prefix('feedback')->middleware('permission:feedback.view,feedback.create')->group(function () {
        Route::get('/', [FeedbackController::class, 'index']);
        Route::post('/', [FeedbackController::class, 'store']);
        Route::get('/statistics', [FeedbackController::class, 'getStatistics']);
        Route::get('/search-enrollees', [FeedbackController::class, 'searchEnrollees']);
        Route::get('/officers', [FeedbackController::class, 'getFeedbackOfficers']);
        Route::get('/my-feedbacks', [FeedbackController::class, 'getMyFeedbacks']);
        Route::get('/approved-referrals', [FeedbackController::class, 'getApprovedReferrals']);
        Route::get('/enrollee/{enrolleeId}/comprehensive-data', [FeedbackController::class, 'getEnrolleeComprehensiveData']);
        Route::get('/{id}', [FeedbackController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [FeedbackController::class, 'update'])->whereNumber('id');
        Route::post('/{id}/assign', [FeedbackController::class, 'assignToOfficer'])->whereNumber('id');
    });

    // Task Management
    Route::prefix('task-management')->middleware('permission:tasks.view,tasks.create')->group(function () {
        // Task Categories
        Route::apiResource('categories', TaskCategoryController::class);
        Route::post('categories/sort-order', [TaskCategoryController::class, 'updateSortOrder']);
        Route::get('categories-dropdown', [TaskCategoryController::class, 'dropdown']);

        // Projects
        Route::apiResource('projects', ProjectController::class);
        Route::get('projects-statistics', [ProjectController::class, 'statistics']);

        // Tasks
        Route::apiResource('tasks', TaskController::class);
        Route::get('tasks-statistics', [TaskController::class, 'statistics']);
        Route::post('tasks/{task}/assign', [TaskController::class, 'assignUser']);
        Route::post('tasks/{task}/comments', [TaskController::class, 'addComment']);
        Route::post('tasks/{task}/attachments', [TaskController::class, 'addAttachment']);
        Route::delete('task-attachments/{attachment}', [TaskController::class, 'deleteAttachment']);
    });

});

// PAS (Pre-Authorization System) routes
Route::middleware(['auth:sanctum'])->prefix('pas')->group(function () {

    // PA Code Management
    Route::get('pa-codes', [PACodeController::class, 'index'])->middleware('permission:any,pa_codes.view,pa_codes.manage');
    Route::post('pa-codes', [PACodeController::class, 'store'])->middleware('permission:any,pa_codes.request,pa_codes.create,pa_codes.manage');
    Route::get('pa-codes/{paCode}', [PACodeController::class, 'show'])->middleware('permission:any,pa_codes.view,pa_codes.manage');
    Route::post('pa-codes/{paCode}/approve', [PACodeController::class, 'approve'])->middleware('permission:any,pa_codes.approve,pa_codes.manage');
    Route::post('pa-codes/{paCode}/reject', [PACodeController::class, 'reject'])->middleware('permission:any,pa_codes.reject,pa_codes.manage');

    // Claims Management (for review/approval)
    Route::prefix('claims')->group(function () {
        Route::get('/', [ClaimController::class, 'index'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
        Route::get('/{claim}', [ClaimController::class, 'show'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
        Route::get('/{claim}/full-details', [ClaimController::class, 'showFullDetails'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
        Route::get('/{claim}/slip', [ClaimController::class, 'downloadSlip'])->middleware('permission:any,claims.view,claims.review,claims.approve,claims.reject');
        Route::post('/{claim}/review', [ClaimController::class, 'review'])->middleware('permission:any,claims.review,claims.reviewer.review,claims.approver.review');
        Route::post('/batch-approve', [ClaimController::class, 'batchApprove'])->middleware('permission:any,claims.approve,claims.reviewer.approve,claims.approver.approve');
        Route::post('/batch-reject', [ClaimController::class, 'batchReject'])->middleware('permission:any,claims.reject,claims.reviewer.reject,claims.approver.reject');
    });

    // Security routes
    Route::prefix('security')->group(function () {
        Route::get('dashboard', [SecurityController::class, 'dashboard'])->middleware('permission:audit.view');
        Route::get('logs', [SecurityController::class, 'logs'])->middleware('permission:audit.view');
        Route::post('logs/{securityLog}/resolve', [SecurityController::class, 'resolve'])->middleware('permission:audit.view');
        Route::post('logs/bulk-resolve', [SecurityController::class, 'bulkResolve'])->middleware('permission:audit.view');
        Route::get('audit-trail', [SecurityController::class, 'auditTrail'])->middleware('permission:audit.view');
        Route::get('sessions', [SecurityController::class, 'sessions'])->middleware('permission:audit.view');
        Route::post('sessions/revoke', [SecurityController::class, 'revokeSessions'])->middleware('permission:audit.view');
    });
});
