<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\PublicPremiumPinPurchaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use RuntimeException;

class PublicPremiumPinController extends BaseController
{
    public function store(Request $request, PublicPremiumPinPurchaseService $service)
    {
        $validated = $request->validate([
            'premium_plan_id' => ['required', 'exists:premium_plans,id'],
            'purchaser_type' => ['required', 'in:beneficiary,agent'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_phone' => ['required', 'string', 'max:40'],
            'payer_email' => ['required', 'email', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            $result = $service->create($validated);
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return $this->sendResponse($result, 'Premium PIN purchase initialized successfully.', 201);
    }

    public function verify(Request $request, string $reference, PublicPremiumPinPurchaseService $service)
    {
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);

        try {
            return $this->sendResponse(
                $service->verify($reference, $validated['token']),
                'Premium PIN purchase verification completed successfully.'
            );
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }
    }

    public function docket(Request $request, string $reference, PublicPremiumPinPurchaseService $service)
    {
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);

        try {
            $purchase = $service->findAccessiblePurchase($reference, $validated['token']);
            $purchase = $service->ensurePinsGenerated($purchase);
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return Pdf::loadView('pdf.premium-pin-docket', compact('purchase'))
            ->setPaper('A4', 'portrait')
            ->download("premium_pin_docket_{$reference}.pdf");
    }
}
