<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BenefitPackage;
use App\Models\Benefactor;
use App\Models\EnrolleeCategory;
use App\Models\EnrollmentPhase;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\InsuranceProgramme;
use App\Models\Lga;
use App\Models\PremiumPlan;
use App\Models\VulnerableGroup;
use App\Models\Ward;
use App\Services\Billing\PaymentGatewayConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PremiumMetadataController extends Controller
{
    public function __construct(private PaymentGatewayConfigurationService $paymentGatewayConfigurationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $programmeId = $request->integer('insurance_programme_id') ?: null;
        $lgaId = $request->integer('lga_id') ?: null;
        $wardId = $request->integer('ward_id') ?: null;
        $benefactorId = $request->integer('benefactor_id') ?: null;
        $programmes = InsuranceProgramme::orderBy('name')->get();
        $categories = EnrolleeCategory::query()
            ->when($programmeId, fn ($query) => $query->where('insurance_programme_id', $programmeId))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'programmes' => $programmes,
                'insurance_programmes' => $programmes,
                'categories' => $categories,
                'enrollee_categories' => $categories,
                'benefit_packages' => BenefitPackage::orderBy('name')->get(),
                'premium_plans' => PremiumPlan::with(['programme', 'benefitPackage'])
                    ->when($programmeId, fn ($query) => $query->where('insurance_programme_id', $programmeId))
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(),
                'funding_types' => FundingType::orderBy('name')->get(),
                'benefactors' => Benefactor::orderBy('name')->get(),
                'enrollment_phases' => EnrollmentPhase::query()
                    ->when($benefactorId, fn ($query) => $query->where('benefactor_id', $benefactorId))
                    ->orderBy('name')
                    ->get(),
                'vulnerable_groups' => VulnerableGroup::orderBy('name')->get(),
                'lgas' => Lga::orderBy('name')->get(),
                'wards' => Ward::query()
                    ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))
                    ->orderBy('name')
                    ->get(),
                'facilities' => Facility::query()
                    ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))
                    ->when($wardId, fn ($query) => $query->where('ward_id', $wardId))
                    ->orderBy('name')
                    ->get(),
                'payment_gateways' => $this->paymentGatewayConfigurationService->availableGatewayOptions(),
                'active_payment_gateway' => $this->paymentGatewayConfigurationService->getActiveGatewayCode(),
                'payment_split_profiles' => $this->paymentGatewayConfigurationService->getSplitProfiles(),
                'merchants' => Schema::hasTable('merchants') ? DB::table('merchants')->orderBy('name')->get() : [],
                'merchant_service_types' => Schema::hasTable('merchant_service_types')
                    ? DB::table('merchant_service_types')->orderBy('type_name')->get()
                    : [],
            ],
        ]);
    }
}
