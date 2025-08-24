<?php

namespace App\Http\Controllers;

use App\Models\Premium;
use App\Models\Lga;
use App\Models\Ward;
use App\Models\Enrollee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PremiumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Premium::with(['lga', 'ward', 'usedBy']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('pin', 'like', "%{$search}%")
                  ->orWhere('serial_no', 'like', "%{$search}%")
                  ->orWhere('pin_raw', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'expired') {
                $query->expired();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('pin_type')) {
            $query->byType($request->pin_type);
        }

        if ($request->filled('pin_category')) {
            $query->byCategory($request->pin_category);
        }

        if ($request->filled('lga_id')) {
            $query->where('lga_id', $request->lga_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_generated', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_generated', '<=', $request->date_to);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $premiums = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $premiums,
            'stats' => $this->getStats()
        ]);
    }

    public function show(Premium $premium): JsonResponse
    {
        $premium->load(['lga', 'ward', 'usedBy', 'enrollee']);

        return response()->json([
            'status' => 'success',
            'data' => $premium
        ]);
    }

    public function generatePins(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'count' => 'required|integer|min:1|max:1000',
            'pin_type' => 'required|in:individual,family,group',
            'pin_category' => 'required|in:formal,informal,vulnerable,retiree',
            'benefit_type' => 'required|in:basic,standard,premium',
            'amount' => 'required|numeric|min:0',
            'expiry_months' => 'integer|min:1|max:24'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $count = $request->count;
        $expiryMonths = $request->get('expiry_months', 12);
        $requestId = Str::uuid();

        try {
            DB::beginTransaction();

            $premiums = [];
            for ($i = 0; $i < $count; $i++) {
                $pinData = Premium::generatePin();
                
                $premiums[] = [
                    'pin' => $pinData['pin'],
                    'pin_raw' => $pinData['pin_raw'],
                    'serial_no' => Premium::generateSerialNumber(),
                    'pin_type' => $request->pin_type,
                    'pin_category' => $request->pin_category,
                    'benefit_type' => $request->benefit_type,
                    'amount' => $request->amount,
                    'date_generated' => now(),
                    'date_expired' => now()->addMonths($expiryMonths),
                    'request_id' => $requestId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Batch insert for better performance
            Premium::insert($premiums);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "{$count} premium PINs generated successfully",
                'data' => [
                    'count' => $count,
                    'request_id' => $requestId,
                    'amount_per_pin' => $request->amount,
                    'total_value' => $request->amount * $count
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PINs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function redeemPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string',
            'enrollee_data' => 'required|array',
            'enrollee_data.first_name' => 'required|string|max:255',
            'enrollee_data.last_name' => 'required|string|max:255',
            'enrollee_data.phone' => 'required|string|max:20',
            'enrollee_data.date_of_birth' => 'required|date',
            'enrollee_data.gender' => 'required|in:Male,Female',
            'enrollee_data.facility_id' => 'required|exists:facilities,id',
            'enrollee_data.enrollee_type_id' => 'required|exists:enrollee_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find premium by PIN (try both formats)
        $pinInput = str_replace('-', '', $request->pin);
        $premium = Premium::where('pin_raw', $pinInput)
                         ->orWhere('pin', $request->pin)
                         ->first();

        if (!$premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid PIN'
            ], 404);
        }

        if (!$premium->canBeUsed()) {
            $message = $premium->isExpired() ? 'PIN has expired' : 'PIN has already been used';
            return response()->json([
                'status' => 'error',
                'message' => $message
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Get facility details to determine LGA and Ward
            $facility = \App\Models\Facility::findOrFail($request->enrollee_data['facility_id']);
            
            // Create enrollee
            $enrolleeData = $request->enrollee_data;
            $enrolleeData['enrollee_id'] = $this->generateEnrolleeId();
            $enrolleeData['lga_id'] = $facility->lga_id;
            $enrolleeData['ward_id'] = $facility->ward_id;
            $enrolleeData['premium_id'] = $premium->id;
            $enrolleeData['created_by'] = auth()->id();
            $enrolleeData['status'] = 'approved'; // Auto-approve for premium redemption

            $enrollee = Enrollee::create($enrolleeData);

            // Mark premium as used
            $premium->markAsUsed(auth()->user(), $facility->lga_id, $facility->ward_id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'PIN redeemed successfully',
                'data' => [
                    'enrollee' => $enrollee->load(['facility', 'lga', 'ward']),
                    'premium' => $premium->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to redeem PIN: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validatePin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $pinInput = str_replace('-', '', $request->pin);
        $premium = Premium::where('pin_raw', $pinInput)
                         ->orWhere('pin', $request->pin)
                         ->first();

        if (!$premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid PIN'
            ], 404);
        }

        $isValid = $premium->canBeUsed();
        $message = $isValid ? 'PIN is valid' : 
                  ($premium->isExpired() ? 'PIN has expired' : 'PIN has already been used');

        return response()->json([
            'status' => $isValid ? 'success' : 'error',
            'message' => $message,
            'data' => [
                'pin_details' => [
                    'pin_type' => $premium->pin_type,
                    'pin_category' => $premium->pin_category,
                    'benefit_type' => $premium->benefit_type,
                    'amount' => $premium->amount,
                    'date_expired' => $premium->date_expired,
                    'is_valid' => $isValid
                ]
            ]
        ]);
    }

    public function getStats(): array
    {
        return [
            'total' => Premium::count(),
            'available' => Premium::available()->count(),
            'used' => Premium::used()->count(),
            'expired' => Premium::expired()->count(),
            'total_value' => Premium::sum('amount'),
            'used_value' => Premium::used()->sum('amount'),
        ];
    }

    private function generateEnrolleeId(): string
    {
        do {
            $id = 'NC' . date('Y') . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Enrollee::where('enrollee_id', $id)->exists());

        return $id;
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $data = array_map('str_getcsv', file($path));
            $header = array_shift($data);

            // Validate CSV structure
            $requiredColumns = ['pin_type', 'pin_category', 'benefit_type', 'amount'];
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $header)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Missing required column: {$column}"
                    ], 422);
                }
            }

            DB::beginTransaction();

            $requestId = Str::uuid();
            $successCount = 0;
            $errors = [];

            foreach ($data as $index => $row) {
                try {
                    $rowData = array_combine($header, $row);
                    $pinData = Premium::generatePin();
                    
                    Premium::create([
                        'pin' => $pinData['pin'],
                        'pin_raw' => $pinData['pin_raw'],
                        'serial_no' => Premium::generateSerialNumber(),
                        'pin_type' => $rowData['pin_type'],
                        'pin_category' => $rowData['pin_category'],
                        'benefit_type' => $rowData['benefit_type'],
                        'amount' => $rowData['amount'],
                        'date_generated' => now(),
                        'date_expired' => now()->addYear(),
                        'request_id' => $requestId,
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Bulk upload completed. {$successCount} PINs created.",
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => count($errors),
                    'errors' => $errors,
                    'request_id' => $requestId
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}