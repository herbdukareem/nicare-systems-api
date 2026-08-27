<?php

namespace App\Http\Requests;

use App\Models\Capitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CapitationHistoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:all,generated,reviewed,approved,paid'],
            'range_mode' => ['required', 'in:all_time,custom'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'funding_type_id' => ['nullable', 'integer', 'exists:funding_types,id'],
            'from_period_id' => ['nullable', 'integer', 'required_if:range_mode,custom', 'exists:capitations,id'],
            'to_period_id' => ['nullable', 'integer', 'required_if:range_mode,custom', 'exists:capitations,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('range_mode') !== 'custom') {
                return;
            }

            $fromPeriodId = (int) $this->input('from_period_id');
            $toPeriodId = (int) $this->input('to_period_id');

            if ($fromPeriodId <= 0 || $toPeriodId <= 0) {
                return;
            }

            $fromPeriod = Capitation::query()
                ->select(['id', 'year', 'capitation_month', 'capitated_month'])
                ->find($fromPeriodId);
            $toPeriod = Capitation::query()
                ->select(['id', 'year', 'capitation_month', 'capitated_month'])
                ->find($toPeriodId);

            if (!$fromPeriod || !$toPeriod) {
                return;
            }

            $fromKey = $this->periodKey($fromPeriod);
            $toKey = $this->periodKey($toPeriod);

            if ($fromKey > $toKey) {
                $validator->errors()->add(
                    'from_period_id',
                    'The starting capitation period must be earlier than or the same as the ending period.'
                );
            }
        });
    }

    private function periodKey(Capitation $capitation): int
    {
        $month = (int) ($capitation->capitation_month ?: $capitation->capitated_month ?: 0);

        return ((int) $capitation->year * 100) + $month;
    }
}
