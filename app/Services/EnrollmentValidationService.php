<?php

namespace App\Services;

use App\Models\EnrollmentFormSchema;
use App\Models\Facility;
use App\Models\PremiumPlan;
use App\Models\Ward;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EnrollmentValidationService
{
    /**
     * @return array{core: array<string, mixed>, extra: array<string, mixed>}
     */
    public function validate(EnrollmentFormSchema $schema, array $data, array $extraFields = []): array
    {
        $coreRules = [
            'nin' => ['nullable', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'sex' => ['required', 'integer', 'in:1,2'],
            'marital_status' => ['nullable', 'integer', 'in:1,2,3,4'],
            'address' => ['nullable', 'string'],
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'lga_id' => ['required', 'integer', 'exists:lgas,id'],
            'ward_id' => ['required', 'integer', 'exists:wards,id'],
            'insurance_programme_id' => ['required', 'integer', 'exists:insurance_programmes,id'],
            'enrollee_category_id' => ['nullable', 'integer', 'exists:enrollee_categories,id'],
            'premium_plan_id' => ['required', 'integer', 'exists:premium_plans,id'],
            'benefactor_id' => ['nullable', 'integer', 'exists:benefactors,id'],
            'premium_pin' => ['nullable', 'string', 'max:255'],
            'relationship_to_principal' => ['nullable', 'integer', 'in:1,2,3,4'],
            'principal_enrollee_id' => ['nullable', 'integer', 'exists:enrollees,id'],
        ];

        $dynamicRules = [];
        foreach ($schema->fields ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (!$key || array_key_exists($key, $coreRules)) {
                continue;
            }

            $rules = (array) ($field['rules'] ?? []);
            array_unshift($rules, ($field['required'] ?? false) ? 'required' : 'nullable');
            $dynamicRules["extra_fields.{$key}"] = array_values(array_unique($rules));
        }

        $validator = Validator::make([
            ...$data,
            'extra_fields' => $extraFields,
        ], $coreRules + $dynamicRules);

        $validator->after(function ($validator) use ($data, $schema): void {
            if (!empty($data['lga_id']) && !empty($data['ward_id'])) {
                $wardBelongsToLga = Ward::whereKey($data['ward_id'])
                    ->where('lga_id', $data['lga_id'])
                    ->exists();

                if (!$wardBelongsToLga) {
                    $validator->errors()->add('ward_id', 'The selected ward does not belong to the selected LGA.');
                }
            }

            if (!empty($data['facility_id']) && !empty($data['lga_id'])) {
                $facility = Facility::find($data['facility_id']);
                if ($facility && (int) $facility->lga_id !== (int) $data['lga_id']) {
                    $validator->errors()->add('facility_id', 'The selected facility does not belong to the selected LGA.');
                }

                if ($facility && !empty($data['ward_id']) && (int) $facility->ward_id !== (int) $data['ward_id']) {
                    $validator->errors()->add('facility_id', 'The selected facility does not belong to the selected ward.');
                }
            }

            if (!empty($data['premium_plan_id']) && !empty($data['insurance_programme_id'])) {
                $plan = PremiumPlan::find($data['premium_plan_id']);
                if ($plan && (int) $plan->insurance_programme_id !== (int) $data['insurance_programme_id']) {
                    $validator->errors()->add('premium_plan_id', 'The selected premium plan does not belong to the selected programme.');
                }
            }

            $allowedBenefactors = array_values(array_filter(array_map('intval', $schema->benefactor_ids ?? [])));
            if (!empty($data['benefactor_id']) && $allowedBenefactors !== [] && !in_array((int) $data['benefactor_id'], $allowedBenefactors, true)) {
                $validator->errors()->add('benefactor_id', 'The selected benefactor is not available for this enrollment configuration.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $core = collect($validated)->except('extra_fields')->all();

        return [
            'core' => $core,
            'extra' => $validated['extra_fields'] ?? [],
        ];
    }
}
