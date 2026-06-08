import { ref } from 'vue';
import { organizationSettingsAPI } from '../utils/api';

const DEFAULTS = {
  agency_name: 'Niger State Contributory Health Agency',
  scheme_name: 'NiCare',
  scheme_tagline: 'Health Insurance Scheme',
  hero_title: 'Health Insurance Coverage for the People of Niger State',
  hero_description: "NiCare is the State's contributory health insurance scheme, providing access to quality healthcare across approved facilities in all 25 Local Government Areas.",
  hotline: '08162653801',
  website: 'nicare.nigerstate.gov.ng',
  address: 'Minna, Niger State, Nigeria',
  about_title: 'Niger State Contributory Health Agency',
  about_description: "Statutory body responsible for administering the State's contributory health insurance scheme under the enabling law establishing NGSCHA.",
  logo_path: null,
  logo_url: '/logo.png',
};

// Shared module-level state — fetched once and reused across pages
const settings = ref({ ...DEFAULTS });
const loaded = ref(false);
const loading = ref(false);
let pendingFetch = null;

const fetchSettings = async () => {
  if (loaded.value) {
    return settings.value;
  }

  if (pendingFetch) {
    return pendingFetch;
  }

  loading.value = true;
  pendingFetch = organizationSettingsAPI.getPublic()
    .then((response) => {
      const data = response.data?.data || {};
      settings.value = {
        ...DEFAULTS,
        ...data,
        logo_url: data.logo_url || DEFAULTS.logo_url,
      };
      loaded.value = true;
      return settings.value;
    })
    .catch(() => settings.value)
    .finally(() => {
      loading.value = false;
      pendingFetch = null;
    });

  return pendingFetch;
};

export function useOrganizationSettings() {
  return {
    settings,
    loading,
    loaded,
    fetchSettings,
  };
}
