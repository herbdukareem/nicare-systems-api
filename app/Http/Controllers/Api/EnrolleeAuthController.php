<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\PremiumPlan;
use App\Services\EnrolleePortalRenewalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class EnrolleeAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'enrollee_id' => 'required|string',
            'password' => 'required|string',
        ]);

        $throttleKey = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed login attempts. Please try again later.',
                'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $identifier = trim((string) $request->enrollee_id);
        $enrollee = $this->findEnrolleeByIdentifier($identifier);

        if (!$enrollee) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Invalid enrollee ID, email, phone number, or password.',
            ], 401);
        }

        if (!$enrollee->password) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Password activation is required before enrollee portal access. Please use the secure activation/reset flow.',
            ], 403);
        }

        if (!Hash::check($request->password, $enrollee->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Invalid enrollee ID, email, phone number, or password.',
            ], 401);
        }

        if (!in_array((int) $enrollee->status, [Enrollee::STATUS_PENDING, Enrollee::STATUS_ACTIVE], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Your account cannot access the portal in its current status. Please contact the agency.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $enrollee->tokens()->delete();
        $token = $enrollee->createToken('enrollee-portal')->plainTextToken;

        $enrollee->load(['facility', 'premiumPlan', 'benefitPackage', 'lga', 'ward', 'insuranceProgramme']);

        return response()->json([
            'success' => true,
            'data' => [
                'enrollee' => $enrollee,
                'token' => $token,
                'has_custom_password' => !is_null($enrollee->password),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $enrollee = $request->user()->load([
            'facility', 'premiumPlan', 'benefitPackage', 'lga', 'ward',
            'insuranceProgramme', 'fundingType', 'benefactor',
        ]);

        return response()->json([
            'success' => true,
            'data' => $enrollee,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $enrollee = $request->user();
        if (!$enrollee->password) {
            return response()->json([
                'success' => false,
                'message' => 'Password activation is required before password changes are allowed.',
            ], 403);
        }

        if (!Hash::check($request->current_password, $enrollee->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $enrollee->update(['password' => Hash::make($request->new_password)]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    public function plans(): JsonResponse
    {
        $plans = PremiumPlan::with(['programme', 'benefitPackage', 'fundingType'])
            ->where('status', 'active');

        if (Schema::hasColumn('premium_plans', 'self_enrollment_enabled')) {
            $plans->where(function (Builder $query) {
                $query->where('self_enrollment_enabled', true)
                    ->orWhereNull('self_enrollment_enabled');
            });
        }

        $plans = $plans->orderBy('amount')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function renew(Request $request, EnrolleePortalRenewalService $service): JsonResponse
    {
        $validated = $request->validate([
            'premium_plan_id' => ['required', 'integer', 'exists:premium_plans,id'],
        ]);

        try {
            $result = $service->create($request->user(), (int) $validated['premium_plan_id']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 201);
    }

    public function verifyRenewal(Request $request, string $reference, EnrolleePortalRenewalService $service): JsonResponse
    {
        try {
            $result = $service->verify($request->user(), $reference);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return 'enrollee-login:' . strtolower(trim((string) $request->input('enrollee_id'))) . '|' . $request->ip();
    }

    private function findEnrolleeByIdentifier(string $identifier): ?Enrollee
    {
        $enrollee = Enrollee::where('enrollee_id', $identifier)->first();

        if ($enrollee) {
            return $enrollee;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return Enrollee::where('email', $identifier)->first();
        }

        return Enrollee::where('phone', $identifier)->first();
    }
}
