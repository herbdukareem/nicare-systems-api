<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\BhcpfExecutiveTarget;
use App\Models\EnrollmentPhase;
use App\Models\Lga;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EnrollmentPhaseController extends BaseController
{
    public function index()
    {
        $phases = EnrollmentPhase::query()
            ->with('benefactor:id,name')
            ->withCount('enrollees')
            ->withSum('bhcpfExecutiveTargets as bhcpf_target_total', 'proposed_enrolments')
            ->withCount('bhcpfExecutiveTargets')
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (EnrollmentPhase $phase) => $this->phasePayload($phase));

        return $this->sendResponse($phases, 'Enrollment phases retrieved successfully.');
    }

    public function store(Request $request)
    {
        $data = $this->validatedPhase($request);

        $phase = DB::transaction(function () use ($data): EnrollmentPhase {
            if ($data['is_current']) {
                EnrollmentPhase::query()->where('is_current', true)->update(['is_current' => false]);
            }

            return EnrollmentPhase::query()->create($data);
        });

        return $this->sendResponse($this->phasePayload($phase->load('benefactor:id,name')), 'Enrollment phase created successfully.', 201);
    }

    public function update(Request $request, EnrollmentPhase $enrollmentPhase)
    {
        $data = $this->validatedPhase($request, $enrollmentPhase);

        DB::transaction(function () use ($data, $enrollmentPhase): void {
            if ($data['is_current']) {
                EnrollmentPhase::query()
                    ->whereKeyNot($enrollmentPhase->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            $enrollmentPhase->update($data);
        });

        return $this->sendResponse($this->phasePayload($enrollmentPhase->fresh()->load('benefactor:id,name')), 'Enrollment phase updated successfully.');
    }

    public function destroy(EnrollmentPhase $enrollmentPhase)
    {
        if ($enrollmentPhase->enrollees()->exists() || $enrollmentPhase->bhcpfExecutiveTargets()->exists()) {
            $enrollmentPhase->update(['status' => 0, 'is_current' => false]);

            return $this->sendResponse([], 'Enrollment phase is in use and was deactivated instead.');
        }

        $enrollmentPhase->delete();

        return $this->sendResponse([], 'Enrollment phase deleted successfully.');
    }

    public function targets(EnrollmentPhase $enrollmentPhase)
    {
        $targets = BhcpfExecutiveTarget::query()
            ->where('enrollment_phase_id', $enrollmentPhase->id)
            ->with('lga:id,name')
            ->orderBy('lga_id')
            ->get()
            ->keyBy('lga_id');

        $rows = Lga::query()->orderBy('name')->get(['id', 'name'])->map(function (Lga $lga) use ($targets): array {
            $target = $targets->get($lga->id);

            return [
                'id' => $target?->id,
                'lga_id' => $lga->id,
                'lga_name' => $lga->name,
                'proposed_enrolments' => (int) ($target?->proposed_enrolments ?? 0),
                'final_target' => (int) ($target?->final_target ?? 0),
                'plwd_target' => (int) ($target?->plwd_target ?? 0),
                'under_5_target' => (int) ($target?->under_5_target ?? 0),
                'female_reproductive_target' => (int) ($target?->female_reproductive_target ?? 0),
                'elderly_target' => (int) ($target?->elderly_target ?? 0),
                'others_target' => (int) ($target?->others_target ?? 0),
            ];
        })->values();

        return $this->sendResponse($rows, 'BHCPF targets retrieved successfully.');
    }

    public function updateTargets(Request $request, EnrollmentPhase $enrollmentPhase)
    {
        $validated = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.lga_id' => ['required', 'integer', 'exists:lgas,id', 'distinct'],
            'targets.*.proposed_enrolments' => ['required', 'integer', 'min:0'],
            'targets.*.final_target' => ['nullable', 'integer', 'min:0'],
            'targets.*.plwd_target' => ['nullable', 'integer', 'min:0'],
            'targets.*.under_5_target' => ['nullable', 'integer', 'min:0'],
            'targets.*.female_reproductive_target' => ['nullable', 'integer', 'min:0'],
            'targets.*.elderly_target' => ['nullable', 'integer', 'min:0'],
            'targets.*.others_target' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $enrollmentPhase): void {
            foreach ($validated['targets'] as $target) {
                $record = BhcpfExecutiveTarget::query()->firstOrNew([
                    'enrollment_phase_id' => $enrollmentPhase->id,
                    'lga_id' => $target['lga_id'],
                ]);

                if (!$record->exists) {
                    $record->fill($this->defaultTargetAttributes());
                }

                $record->fill(Arr::only($target, [
                    'proposed_enrolments', 'final_target', 'plwd_target', 'under_5_target',
                    'female_reproductive_target', 'elderly_target', 'others_target',
                ]));
                $record->save();
            }
        });

        return $this->targets($enrollmentPhase);
    }

    private function validatedPhase(Request $request, ?EnrollmentPhase $phase = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('enrollment_phases', 'name')->ignore($phase?->id)],
            'benefactor_id' => ['required', 'integer', 'exists:benefactors,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'boolean'],
            'is_current' => ['required', 'boolean'],
        ]);
    }

    private function phasePayload(EnrollmentPhase $phase): array
    {
        return [
            'id' => $phase->id,
            'name' => $phase->name,
            'benefactor_id' => $phase->benefactor_id,
            'benefactor_name' => $phase->benefactor?->name,
            'start_date' => $phase->start_date?->toDateString(),
            'end_date' => $phase->end_date?->toDateString(),
            'status' => (int) $phase->status,
            'is_current' => (bool) $phase->is_current,
            'enrollees_count' => (int) ($phase->enrollees_count ?? $phase->enrollees()->count()),
            'bhcpf_targets_count' => (int) ($phase->bhcpf_executive_targets_count ?? $phase->bhcpfExecutiveTargets()->count()),
            'bhcpf_target_total' => (int) ($phase->bhcpf_target_total ?? $phase->bhcpfExecutiveTargets()->sum('proposed_enrolments')),
        ];
    }

    private function defaultTargetAttributes(): array
    {
        return [
            'ward_count' => 0,
            'current_enrollee_count' => 0,
            'poverty_index' => 0,
            'proposed_enrolments' => 0,
            'final_target' => 0,
            'plwd_target' => 0,
            'under_5_target' => 0,
            'female_reproductive_target' => 0,
            'elderly_target' => 0,
            'others_target' => 0,
            'proposed_per_ward' => 0,
            'plwd_per_ward' => 0,
            'under_5_per_ward' => 0,
            'female_reproductive_per_ward' => 0,
            'elderly_per_ward' => 0,
            'others_per_ward' => 0,
        ];
    }
}
