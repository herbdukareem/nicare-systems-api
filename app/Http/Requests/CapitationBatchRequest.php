<?php

namespace App\Http\Requests;

use App\Models\Capitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CapitationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'funding_type_id' => ['nullable', 'exists:funding_types,id'],
            'capitation_month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_day' => ['required', 'integer', 'between:1,31'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $month = (int) $this->input('capitation_month');
            $year = (int) $this->input('year');

            if (!$month || !$year) {
                return;
            }

            $exists = Capitation::where('capitation_month', $month)
                ->where('year', $year)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'capitation_month',
                    'A capitation period already exists for the selected month and year.'
                );
            }
        });
    }
}
