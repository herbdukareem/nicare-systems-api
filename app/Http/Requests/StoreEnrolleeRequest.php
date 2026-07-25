<?php

namespace App\Http\Requests;

use App\Models\Enrollee;
use App\Models\PremiumPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Class StoreEnrolleeRequest
 *
 * Handles validation when creating an enrollee.
 */
class StoreEnrolleeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (!isset($data['sex']) && isset($data['gender'])) {
            $data['sex'] = match (strtolower((string) $data['gender'])) {
                'male', 'm' => 1,
                'female', 'f' => 2,
                default => null,
            };
        }

        if (array_key_exists('occupation', $data)) {
            $data['occupation'] = Enrollee::normalizeOccupation($data['occupation']);
        }

        if (($data['relationship_to_principal'] ?? 1) == 1) {
            $data['principal_enrollee_id'] = null;
        }

        unset($data['enrollee_id']);

        $this->merge($data + [
            'created_by' => auth()->id(),
            'status' => 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'nin' => 'nullable|string|max:255|unique:enrollees,nin',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'date_of_birth' => 'required|date',
            'sex' => 'required|integer|in:1,2',
            'marital_status' => ['nullable', 'integer', Rule::in(array_keys(Enrollee::MARITAL_STATUS_OPTIONS))],
            'address' => 'nullable|string',
            'insurance_programme_id' => 'nullable|exists:insurance_programmes,id',
            'enrollee_category_id' => 'nullable|exists:enrollee_categories,id',
            'facility_id' => 'required|exists:facilities,id',
            'lga_id' => 'required|exists:lgas,id',
            'ward_id' => 'required|exists:wards,id',
            'village' => 'nullable|string|max:255',
            'pregnant' => 'nullable|boolean',
            'disability' => ['nullable', 'string', 'max:255', Rule::in(Enrollee::DISABILITY_OPTIONS)],
            'occupation' => ['nullable', 'string', 'max:255', Rule::in(Enrollee::OCCUPATION_OPTIONS)],
            'premium_plan_id' => 'nullable|exists:premium_plans,id',
            'premium_pin_id' => 'nullable|exists:premium_pins,id',
            'principal_enrollee_id' => 'nullable|exists:enrollees,id',
            'relationship_to_principal' => 'nullable|integer|in:1,2,3,4',
            'benefit_package_id' => 'nullable|exists:benefit_packages,id',
            'vulnerable_group_id' => 'nullable|exists:vulnerable_groups,id',
            'funding_type_id' => 'nullable|exists:funding_types,id',
            'benefactor_id' => 'nullable|exists:benefactors,id',
            'capitation_start_date' => 'nullable|date',
            'coverage_start_date' => 'nullable|date',
            'coverage_end_date' => 'nullable|date|after_or_equal:coverage_start_date',
            'approval_date' => 'nullable|date',
            'status' => 'nullable|integer|in:0,1,2,3,4',
            'created_by' => 'required|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('lga_id') && $this->filled('ward_id')) {
                $wardBelongsToLga = \App\Models\Ward::whereKey($this->input('ward_id'))
                    ->where('lga_id', $this->input('lga_id'))
                    ->exists();

                if (!$wardBelongsToLga) {
                    $validator->errors()->add('ward_id', 'The selected ward does not belong to the selected LGA.');
                }
            }

            $relationship = (int) $this->input('relationship_to_principal', 1);
            if ($relationship === 1 || !$this->filled('premium_plan_id')) {
                return;
            }

            $plan = PremiumPlan::find($this->input('premium_plan_id'));
            if (!$plan) {
                return;
            }

            if (!$plan->isFamilyPlan()) {
                $validator->errors()->add('relationship_to_principal', 'Dependants are not allowed for the selected premium plan.');
                return;
            }

            if (!$this->filled('principal_enrollee_id')) {
                $validator->errors()->add('principal_enrollee_id', 'Principal enrollee is required for dependant enrollment.');
                return;
            }

            $dependantCount = Enrollee::where('principal_enrollee_id', $this->input('principal_enrollee_id'))->count();
            if ($dependantCount >= $plan->getEffectiveMaximumDependants()) {
                $validator->errors()->add('principal_enrollee_id', 'The selected principal has reached the dependant limit for this plan.');
            }
        });
    }
}
