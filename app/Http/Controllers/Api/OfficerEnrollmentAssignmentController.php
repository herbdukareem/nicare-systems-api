<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\OfficerEnrollmentAssignment;
use App\Models\User;
use Illuminate\Http\Request;

class OfficerEnrollmentAssignmentController extends BaseController
{
    public function index(Request $request)
    {
        $assignments = OfficerEnrollmentAssignment::with([
            'officer:id,name,username,email,status,mobile_enrollment_disabled_at',
            'lga:id,name,code',
            'schema:id,name,version,status,insurance_programme_id,premium_plan_id',
            'schema.programme:id,name,code',
            'schema.plan:id,name,code',
        ])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('enabled'), fn ($query) => $query->where('enabled', $request->boolean('enabled')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->sendResponse($assignments, 'Officer enrollment assignments retrieved.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'lga_ids' => ['nullable', 'array'],
            'lga_ids.*' => ['integer', 'exists:lgas,id'],
            'enrollment_form_schema_ids' => ['nullable', 'array'],
            'enrollment_form_schema_ids.*' => ['integer', 'exists:enrollment_form_schemas,id'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $lgaIds = $validated['lga_ids'] ?? [null];
        $schemaIds = $validated['enrollment_form_schema_ids'] ?? [null];
        $enabled = (bool) ($validated['enabled'] ?? true);
        $created = [];

        foreach ($lgaIds === [] ? [null] : $lgaIds as $lgaId) {
            foreach ($schemaIds === [] ? [null] : $schemaIds as $schemaId) {
                $created[] = OfficerEnrollmentAssignment::updateOrCreate(
                    [
                        'user_id' => $validated['user_id'],
                        'lga_id' => $lgaId,
                        'enrollment_form_schema_id' => $schemaId,
                    ],
                    [
                        'enabled' => $enabled,
                        'assigned_by' => $request->user()?->id,
                        'assigned_at' => now(),
                    ]
                )->fresh(['lga:id,name,code', 'schema:id,name,version,status']);
            }
        }

        return $this->sendResponse($created, 'Officer enrollment assignment saved.', 201);
    }

    public function update(Request $request, OfficerEnrollmentAssignment $assignment)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $assignment->forceFill(['enabled' => $validated['enabled']])->save();

        return $this->sendResponse($assignment->fresh(['lga:id,name,code', 'schema:id,name,version,status']), 'Officer enrollment assignment updated.');
    }

    public function destroy(OfficerEnrollmentAssignment $assignment)
    {
        $assignment->delete();

        return $this->sendResponse([], 'Officer enrollment assignment removed.');
    }

    public function setOfficerEnrollmentStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user->forceFill([
            'mobile_enrollment_disabled_at' => $validated['enabled'] ? null : now(),
        ])->save();

        if (!$validated['enabled']) {
            $user->tokens()->delete();
        }

        return $this->sendResponse($user->fresh(['roles:id,name,label']), $validated['enabled']
            ? 'Officer enrollment enabled.'
            : 'Officer enrollment disabled and active tokens revoked.');
    }
}
