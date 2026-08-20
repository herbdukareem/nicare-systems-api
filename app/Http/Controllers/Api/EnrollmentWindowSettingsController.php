<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\EnrollmentWindowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EnrollmentWindowSettingsController extends BaseController
{
    public function __construct(private readonly EnrollmentWindowService $service)
    {
    }

    public function show()
    {
        return $this->sendResponse(
            $this->service->currentState(),
            'Enrollment window settings retrieved successfully.'
        );
    }

    public function update(Request $request)
    {
        $validated = $this->validated($request);

        return $this->sendResponse(
            $this->service->save($validated),
            'Enrollment window settings updated successfully.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $rules = [
            'enabled' => ['required', 'boolean'],
            'timezone' => ['nullable', 'string', Rule::in([EnrollmentWindowService::DEFAULT_TIMEZONE])],
            'schedule' => ['required', 'array'],
        ];

        foreach (EnrollmentWindowService::DAYS as $day) {
            $rules["schedule.{$day}"] = ['required', 'array'];
            $rules["schedule.{$day}.enabled"] = ['required', 'boolean'];
            $rules["schedule.{$day}.start_time"] = ['nullable', 'date_format:H:i'];
            $rules["schedule.{$day}.end_time"] = ['nullable', 'date_format:H:i'];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            foreach (EnrollmentWindowService::DAYS as $day) {
                $enabled = (bool) data_get($request->all(), "schedule.{$day}.enabled", false);
                $start = data_get($request->all(), "schedule.{$day}.start_time");
                $end = data_get($request->all(), "schedule.{$day}.end_time");

                if (!$enabled) {
                    continue;
                }

                if (!is_string($start) || trim($start) === '') {
                    $validator->errors()->add("schedule.{$day}.start_time", 'A start time is required when the day is open.');
                }

                if (!is_string($end) || trim($end) === '') {
                    $validator->errors()->add("schedule.{$day}.end_time", 'An end time is required when the day is open.');
                }

                if (is_string($start) && is_string($end) && trim($start) !== '' && trim($end) !== '' && $start >= $end) {
                    $validator->errors()->add("schedule.{$day}.end_time", 'End time must be later than start time.');
                }
            }
        });

        return $validator->validate();
    }
}
