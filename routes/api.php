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
use App\Http\Controllers\PAS\PACodeController;

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
});

Route::middleware('auth:sanctum')->group(function () {


    // DOFacility Management - Specific routes must come before apiResource
    Route::get('do-facilities/desk-officers', [DOFacilityController::class, 'getDeskOfficers']);
    Route::get('do-facilities/facilities', [DOFacilityController::class, 'getFacilities']);
    Route::get('do-facilities/user/{userId}/facilities', [DOFacilityController::class, 'getUserFacilities']);
    Route::apiResource('do-facilities', DOFacilityController::class);

    // DO Dashboard routes (for desk officers)
    Route::prefix('do-dashboard')->middleware('claims.role:desk_officer,facility_admin,facility_user')->group(function () {
        Route::get('overview', [DODashboardController::class, 'overview']);
        Route::get('referrals', [DODashboardController::class, 'getReferrals']);
        Route::get('pa-codes', [DODashboardController::class, 'getPACodes']);
        Route::post('validate-utn', [DODashboardController::class, 'validateUTN']);
    });

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

    // Role management routes
    Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    Route::get('roles-with-user-counts', [RoleController::class, 'withUserCounts']);
    Route::post('roles/{role}/clone', [RoleController::class, 'clone']);
    Route::delete('roles/bulk-delete', [RoleController::class, 'bulkDelete']);

    // Permission management routes
    Route::get('permissions/by-category', [PermissionController::class, 'byCategory']);
    Route::post('permissions/bulk-create', [PermissionController::class, 'bulkCreate']);
    Route::delete('permissions/bulk-delete', [PermissionController::class, 'bulkDelete']);

    // User routes
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
    // Profile management routes
    Route::get('users/{user}/activities', [UserController::class, 'activities']);
    Route::post('users/{user}/roles', [UserController::class, 'updateRoles']);
    Route::post('users/{user}/avatar', [UserController::class, 'uploadAvatar']);
    Route::patch('users/{user}/toggle-2fa', [UserController::class, 'toggle2FA']);
    Route::post('users/{user}/revoke-sessions', [UserController::class, 'revokeAllSessions']);
    // Advanced user features
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate']);
    Route::post('users/stop-impersonation', [UserController::class, 'stopImpersonation']);
    Route::get('users/export', [UserController::class, 'export']);
    Route::post('users/import', [UserController::class, 'import']);
    Route::get('users/{user}/activity-stats', [UserController::class, 'activityStats']);

    Route::apiResource('enrollee-types', EnrolleeTypeController::class);
    Route::apiResource('banks', BankController::class);
    // Route::apiResource('facilities', FacilityController::class);
    Route::get('facilities', [FacilityController::class, 'index']);
    Route::get('facilities/{facility}/enrollees', [FacilityController::class, 'enrollees']);
    Route::apiResource('referrals', ReferralController::class)->only(['index', 'store', 'show']);
    Route::post('referrals/{referral}/approve', [ReferralController::class, 'approve']);
    Route::post('referrals/{referral}/reject', [ReferralController::class, 'reject']);
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

    // Feedback Management
    Route::prefix('feedback')->group(function () {
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
    Route::prefix('task-management')->group(function () {
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
