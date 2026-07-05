<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\NinProviderConfigService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NinProviderConfigurationController extends BaseController
{
    public function __construct(private readonly NinProviderConfigService $configService)
    {
    }

    public function show()
    {
        return $this->sendResponse($this->configService->getConfig(), 'NIN provider configuration retrieved successfully.');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'provider_name' => ['required', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'base_url' => ['required', 'url', 'max:255'],
            'verify_endpoint' => ['required', 'string', 'max:255'],
            'auth_type' => ['required', Rule::in(['bearer'])],
            'api_key' => ['nullable', 'string'],
            'request_method' => ['required', Rule::in(['GET', 'POST'])],
            'request_nin_field' => ['required', 'string', 'max:100'],
            'request_consent_field' => ['required', 'string', 'max:100'],
            'request_consent_value' => ['required'],
            'success_path' => ['required', 'string', 'max:255'],
            'data_path' => ['required', 'string', 'max:255'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:60'],
            'field_map' => ['required', 'array'],
            'field_map.nin' => ['required', 'string'],
            'field_map.first_name' => ['required', 'string'],
            'field_map.middle_name' => ['nullable', 'string'],
            'field_map.last_name' => ['required', 'string'],
            'field_map.date_of_birth' => ['required', 'string'],
            'field_map.gender' => ['required', 'string'],
            'field_map.phone' => ['nullable', 'string'],
            'field_map.photo' => ['nullable', 'string'],
            'field_map.address' => ['nullable', 'string'],
        ]);

        return $this->sendResponse(
            $this->configService->save($validated),
            'NIN provider configuration updated successfully.'
        );
    }
}
