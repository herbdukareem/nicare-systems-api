<?php

namespace App\Services;

use App\Models\Configuration;
use Illuminate\Support\Facades\Storage;

class OrganizationSettingsService
{
    private const KEY_PREFIX = 'ORG_';

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $logoPath = Configuration::getValue(self::KEY_PREFIX . 'LOGO_PATH', '');

        return [
            'agency_name' => Configuration::getValue(self::KEY_PREFIX . 'AGENCY_NAME', 'Niger State Contributory Health Agency'),
            'scheme_name' => Configuration::getValue(self::KEY_PREFIX . 'SCHEME_NAME', 'NiCare'),
            'scheme_tagline' => Configuration::getValue(self::KEY_PREFIX . 'SCHEME_TAGLINE', 'Health Insurance Scheme'),
            'hero_title' => Configuration::getValue(self::KEY_PREFIX . 'HERO_TITLE', 'Health Insurance Coverage for the People of Niger State'),
            'hero_description' => Configuration::getValue(self::KEY_PREFIX . 'HERO_DESCRIPTION', "NiCare is the State's contributory health insurance scheme, providing access to quality healthcare across approved facilities in all 25 Local Government Areas."),
            'hotline' => Configuration::getValue(self::KEY_PREFIX . 'HOTLINE', '08162653801'),
            'website' => Configuration::getValue(self::KEY_PREFIX . 'WEBSITE', 'nicare.nigerstate.gov.ng'),
            'address' => Configuration::getValue(self::KEY_PREFIX . 'ADDRESS', 'Minna, Niger State, Nigeria'),
            'about_title' => Configuration::getValue(self::KEY_PREFIX . 'ABOUT_TITLE', 'Niger State Contributory Health Agency'),
            'about_description' => Configuration::getValue(self::KEY_PREFIX . 'ABOUT_DESCRIPTION', "Statutory body responsible for administering the State's contributory health insurance scheme under the enabling law establishing NGSCHA."),
            'logo_path' => $logoPath ?: null,
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function save(array $attributes): array
    {
        Configuration::setValue(self::KEY_PREFIX . 'AGENCY_NAME', (string) $attributes['agency_name'], 'Statutory agency name displayed across the system.');
        Configuration::setValue(self::KEY_PREFIX . 'SCHEME_NAME', (string) $attributes['scheme_name'], 'Short name of the health insurance scheme.');
        Configuration::setValue(self::KEY_PREFIX . 'SCHEME_TAGLINE', (string) $attributes['scheme_tagline'], 'Tagline shown alongside the scheme name.');
        Configuration::setValue(self::KEY_PREFIX . 'HERO_TITLE', (string) $attributes['hero_title'], 'Headline shown on the public landing page.');
        Configuration::setValue(self::KEY_PREFIX . 'HERO_DESCRIPTION', (string) $attributes['hero_description'], 'Introductory description shown on the public landing page.');
        Configuration::setValue(self::KEY_PREFIX . 'HOTLINE', (string) $attributes['hotline'], 'Public support hotline number.');
        Configuration::setValue(self::KEY_PREFIX . 'WEBSITE', (string) $attributes['website'], 'Public website address.');
        Configuration::setValue(self::KEY_PREFIX . 'ADDRESS', (string) $attributes['address'], 'Physical address shown in contact information.');
        Configuration::setValue(self::KEY_PREFIX . 'ABOUT_TITLE', (string) $attributes['about_title'], 'Title used in the about/statutory information section.');
        Configuration::setValue(self::KEY_PREFIX . 'ABOUT_DESCRIPTION', (string) $attributes['about_description'], 'Description used in the about/statutory information section.');

        return $this->getSettings();
    }

    /**
     * @return array<string, mixed>
     */
    public function saveLogo(string $path): array
    {
        $current = Configuration::getValue(self::KEY_PREFIX . 'LOGO_PATH', '');

        if ($current && $current !== $path) {
            Storage::disk('public')->delete($current);
        }

        Configuration::setValue(self::KEY_PREFIX . 'LOGO_PATH', $path, 'Storage path of the organization logo.');

        return $this->getSettings();
    }

    /**
     * @return array<string, mixed>
     */
    public function removeLogo(): array
    {
        $current = Configuration::getValue(self::KEY_PREFIX . 'LOGO_PATH', '');

        if ($current) {
            Storage::disk('public')->delete($current);
        }

        Configuration::setValue(self::KEY_PREFIX . 'LOGO_PATH', '', 'Storage path of the organization logo.');

        return $this->getSettings();
    }
}
