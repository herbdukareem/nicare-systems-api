<?php

namespace App\Http\Controllers;

use App\Models\Enrollee;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\Response;

class EnrollmentSlipVerificationController extends Controller
{
    public function __invoke(
        Enrollee $enrollee,
        OrganizationSettingsService $organizationSettings
    ): Response {
        $enrollee->load([
            'insuranceProgramme',
            'enrolleeCategory',
            'premiumPlan',
            'benefitPackage',
            'vulnerableGroup',
            'facility',
            'lga',
            'ward',
        ]);

        return response()->view('enrollment-slip-verification', [
            'enrollee' => $enrollee,
            'organization' => $organizationSettings->getSettings(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
