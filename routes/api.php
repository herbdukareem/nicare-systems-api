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
use App\Http\Controllers\Api\EnrolleeImportController;
use App\Http\Controllers\Api\EnrolleeController as EnrolleeApiController;
use App\Http\Controllers\Api\ExtendedReportingController;

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

// Auth routes (without middleware to avoid CSRF issues)
Route::post('login', [AuthController::class, 'login'])->withoutMiddleware(['web']);
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
    Route::get('dashboard/enrollment-trend', [DashboardController::class, 'enrollmentTrend']);
    Route::get('dashboard/wards-by-lga', [DashboardController::class, 'wardsByLga']);
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
    Route::middleware('permission:enrollees.view,enrollees.create,enrollees.update')->group(function () {
        Route::get('enrollees/pending-approval', [EnrolleeController::class, 'pendingApproval']);
        Route::get('enrollees/bulk-enrollment-slip', [EnrolleeController::class, 'bulkEnrollmentSlip']);
        Route::get('enrollees/bulk-id-card', [EnrolleeController::class, 'bulkIdCard']);
        Route::post('enrollees/{enrollee}/approve', [EnrolleeController::class, 'approve']);
        Route::get('enrollees/{enrollee}/id-card', [EnrolleeController::class, 'idCard']);
        Route::apiResource('enrollees', EnrolleeController::class);
        Route::post('enrollees/{enrollee}/upload-passport', [EnrolleeController::class, 'uploadPassport']);
        Route::put('enrollees/{enrollee}/status', [EnrolleeController::class, 'updateStatus']);
        Route::get('enrollees/{enrollee}/statistics', [EnrolleeController::class, 'getStatistics']);
    });
    Route::middleware('permission:enrollees.export')->get('enrollees-export', function (Request $request) {
        $filename = 'enrollees_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
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
    Route::apiResource('referrals', ReferralController::class)->only(['index', 'store', 'show']);
    Route::post('referrals/{referral}/approve', [ReferralController::class, 'approve']);
    Route::post('referrals/{referral}/reject', [ReferralController::class, 'reject']);
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

        Route::apiResource('payroll-batches', PayrollBatchController::class)->only(['index', 'store'])->parameters(['payroll-batches' => 'payrollBatch']);
        Route::post('payroll-batches/{payrollBatch}/approve', [PayrollBatchController::class, 'approve']);

        Route::get('eligibility', [EligibilityLookupController::class, 'show']);
    });

    Route::apiResource('funding-types', FundingTypeController::class);
    Route::apiResource('benefactors', BenefactorController::class);
    Route::apiResource('benefit-packages', BenefitPackageController::class)->parameters(['benefit-packages' => 'benefitPackage']);
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
            Route::get('/', [AdmissionController::class, 'index']);
            Route::post('/', [AdmissionController::class, 'store']);
            Route::get('/check/{referralId}', [AdmissionController::class, 'checkAdmissionEligibility']);
            Route::get('/enrollee/{enrolleeId}', [AdmissionController::class, 'getActiveAdmission']);
            Route::get('/{admission}', [AdmissionController::class, 'show']);
            Route::post('/{admission}/discharge', [AdmissionController::class, 'discharge']);
        });

        // Claim Management
        Route::prefix('claims')->group(function () {
            Route::get('/', [ClaimController::class, 'index']);
            Route::post('/', [ClaimController::class, 'store']);
            Route::get('/{claim}', [ClaimController::class, 'show']);
            Route::get('/{claim}/full-details', [ClaimController::class, 'showFullDetails']);
            Route::get('/{claim}/slip', [ClaimController::class, 'downloadSlip']);
            Route::post('/{claim}/submit', [ClaimController::class, 'submit']);
            Route::post('/{claim}/validate', [ClaimController::class, 'validateClaim']);
            Route::post('/{claim}/approve', [ClaimController::class, 'approve']);
            Route::post('/{claim}/reject', [ClaimController::class, 'reject']);
            Route::get('/{claim}/summary', [ClaimController::class, 'summary']);
            // Review and batch operations
            Route::post('/{claim}/review', [ClaimController::class, 'review']);
            Route::post('/batch-approve', [ClaimController::class, 'batchApprove']);
            Route::post('/batch-reject', [ClaimController::class, 'batchReject']);

            // Claim Lines
            Route::post('/{claim}/lines/bundle', [ClaimLineController::class, 'addBundleTreatment']);
            Route::post('/{claim}/lines/ffs', [ClaimLineController::class, 'addFFSTreatment']);
            Route::get('/{claim}/classification', [ClaimLineController::class, 'getClassification']);
        });

        // Claim Line Management
        Route::prefix('claim-lines')->group(function () {
            Route::get('/{claimLine}', [ClaimLineController::class, 'show']);
            Route::delete('/{claimLine}', [ClaimLineController::class, 'destroy']);
        });

        // Payment Batch Management
        Route::prefix('payment-batches')->group(function () {
            Route::get('/', [PaymentBatchController::class, 'index']);
            Route::post('/', [PaymentBatchController::class, 'store']);
            Route::get('/approved-claims', [PaymentBatchController::class, 'getApprovedClaims']);
            Route::get('/{batch}', [PaymentBatchController::class, 'show']);
            Route::post('/{batch}/process', [PaymentBatchController::class, 'process']);
            Route::post('/{batch}/mark-paid', [PaymentBatchController::class, 'markPaid']);
            Route::get('/{batch}/receipt', [PaymentBatchController::class, 'downloadReceipt']);
        });

        // Payment Management
        Route::prefix('payments')->group(function () {
            Route::get('/calculate/{claim}', [PaymentController::class, 'calculate']);
            Route::post('/process', [PaymentController::class, 'process']);
            Route::get('/track/{claim}', [PaymentController::class, 'track']);
            Route::get('/facility/{facilityId}/summary', [PaymentController::class, 'facilityPaymentSummary']);
        });

        // Reporting
        Route::prefix('reports')->group(function () {
            Route::get('/claims', [ReportingController::class, 'claimsReport']);
            Route::get('/payments', [ReportingController::class, 'paymentReport']);
            Route::get('/compliance', [ReportingController::class, 'complianceReport']);
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
    Route::get('enrollees/duplicates', [EnrolleeApiController::class, 'listDuplicates']);
    Route::post('enrollees/duplicates/{flag}/resolve', [EnrolleeApiController::class, 'resolveDuplicate']);

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
Route::middleware(['auth:sanctum', 'permission:referrals.view,pa_codes.view,admissions.view,utn.validate'])->prefix('pas')->group(function () {

    // PA Code Management
    Route::apiResource('pa-codes', PACodeController::class)->only(['index', 'store', 'show']);
    Route::post('pa-codes/{paCode}/approve', [PACodeController::class, 'approve']);
    Route::post('pa-codes/{paCode}/reject', [PACodeController::class, 'reject']);

    // Claims Management (for review/approval)
    Route::prefix('claims')->group(function () {
        Route::get('/', [ClaimController::class, 'index']);
        Route::get('/{claim}', [ClaimController::class, 'show']);
        Route::get('/{claim}/full-details', [ClaimController::class, 'showFullDetails']);
        Route::get('/{claim}/slip', [ClaimController::class, 'downloadSlip']);
        Route::post('/{claim}/review', [ClaimController::class, 'review']);
        Route::post('/batch-approve', [ClaimController::class, 'batchApprove']);
        Route::post('/batch-reject', [ClaimController::class, 'batchReject']);
    });

    // Security routes
    Route::prefix('security')->group(function () {
        Route::get('dashboard', [SecurityController::class, 'dashboard']);
        Route::get('logs', [SecurityController::class, 'logs']);
        Route::post('logs/{securityLog}/resolve', [SecurityController::class, 'resolve']);
        Route::post('logs/bulk-resolve', [SecurityController::class, 'bulkResolve']);
        Route::get('audit-trail', [SecurityController::class, 'auditTrail']);
        Route::get('sessions', [SecurityController::class, 'sessions']);
        Route::post('sessions/revoke', [SecurityController::class, 'revokeSessions']);
    });
});
