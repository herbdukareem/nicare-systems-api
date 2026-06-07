<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\PremiumPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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

        $enrollee = Enrollee::where('enrollee_id', $request->enrollee_id)->first();

        if (!$enrollee) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Invalid enrollee ID or password.',
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
                'message' => 'Invalid enrollee ID or password.',
            ], 401);
        }

        if ((int) $enrollee->status !== Enrollee::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact the agency.',
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
        $plans = PremiumPlan::orderBy('amount')->get();

        return response()->json(['success' => true, 'data' => $plans]);
    }

    private function throttleKey(Request $request): string
    {
        return 'enrollee-login:' . strtolower((string) $request->input('enrollee_id')) . '|' . $request->ip();
    }
}
