<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\Request;

class OrganizationSettingsController extends BaseController
{
    public function __construct(private readonly OrganizationSettingsService $settingsService)
    {
    }

    public function show()
    {
        return $this->sendResponse($this->settingsService->getSettings(), 'Organization settings retrieved successfully.');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'agency_name' => ['required', 'string', 'max:255'],
            'scheme_name' => ['required', 'string', 'max:100'],
            'scheme_tagline' => ['required', 'string', 'max:255'],
            'hero_title' => ['required', 'string', 'max:255'],
            'hero_description' => ['required', 'string', 'max:1000'],
            'hotline' => ['required', 'string', 'max:50'],
            'website' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'about_title' => ['required', 'string', 'max:255'],
            'about_description' => ['required', 'string', 'max:1000'],
        ]);

        return $this->sendResponse(
            $this->settingsService->save($validated),
            'Organization settings updated successfully.'
        );
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $file = $request->file('logo');
        $filename = 'org_logo_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('branding', $filename, 'public');

        return $this->sendResponse(
            $this->settingsService->saveLogo($path),
            'Organization logo updated successfully.'
        );
    }

    public function removeLogo()
    {
        return $this->sendResponse(
            $this->settingsService->removeLogo(),
            'Organization logo removed successfully.'
        );
    }
}
