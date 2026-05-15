<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentGroup;
use App\Services\PremiumAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentGroupController extends Controller
{
    public function index(Request $request)
    {
        return EnrollmentGroup::with('benefactor')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate($request->get('per_page', 15));
    }

    public function store(Request $request, PremiumAuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:120'],
            'benefactor_id' => ['nullable', 'exists:benefactors,id'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);
        $group = EnrollmentGroup::create($data + ['created_by' => auth()->id()]);
        $audit->record($group, 'group_created', "Enrollment group {$group->name} created.", [], $group->toArray());

        return response()->json(['success' => true, 'data' => $group], 201);
    }

    public function show(EnrollmentGroup $group): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $group->load('benefactor', 'members')]);
    }

    public function addMembers(Request $request, EnrollmentGroup $group): JsonResponse
    {
        $data = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*.enrollee_id' => ['required', 'exists:enrollees,id'],
            'members.*.member_number' => ['nullable', 'string', 'max:120'],
            'members.*.role' => ['nullable', 'string', 'max:120'],
        ]);

        foreach ($data['members'] as $member) {
            $group->members()->syncWithoutDetaching([
                $member['enrollee_id'] => [
                    'member_number' => $member['member_number'] ?? null,
                    'role' => $member['role'] ?? null,
                    'status' => 'active',
                ],
            ]);
        }

        return response()->json(['success' => true, 'data' => $group->load('members')]);
    }
}
