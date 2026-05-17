<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\PremiumPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EnrolleeAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'enrollee_id' => 'required|string',
            'password'    => 'required|string',
        ]);

        $enrollee = Enrollee::where('enrollee_id', $request->enrollee_id)->first();

        if (!$enrollee) {
            return response()->json(['success' => false, 'message' => 'Invalid enrollee ID or NIN.'], 401);
        }

        // Login priority: custom hashed password → plain NIN fallback
        if ($enrollee->password) {
            $authenticated = Hash::check($request->password, $enrollee->password);
        } else {
            $authenticated = $request->password === $enrollee->nin;
        }

        if (!$authenticated) {
            return response()->json(['success' => false, 'message' => 'Invalid enrollee ID or NIN.'], 401);
        }

        if ((int) $enrollee->status !== Enrollee::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact the agency.',
            ], 403);
        }

        $enrollee->tokens()->delete();
        $token = $enrollee->createToken('enrollee-portal')->plainTextToken;

        $enrollee->load(['facility', 'premiumPlan', 'benefitPackage', 'lga', 'ward', 'insuranceProgramme']);

        return response()->json([
            'success' => true,
            'data' => [
                'enrollee'           => $enrollee,
                'token'              => $token,
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
            'data'    => $enrollee,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $enrollee = $request->user();

        // Verify current credentials
        if ($enrollee->password) {
            $valid = Hash::check($request->current_password, $enrollee->password);
        } else {
            $valid = $request->current_password === $enrollee->nin;
        }

        if (!$valid) {
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
}
