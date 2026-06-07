<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Organization Settings"
        subtitle="Manage the agency name, scheme branding, contact details, and logo shown across the public site and admin system."
        kicker="Administration"
        icon="mdi-domain"
      >
        <v-btn color="primary" prepend-icon="mdi-content-save-outline" :loading="saving" @click="saveSettings">
          Save Settings
        </v-btn>
      </AppPageHeader>

      <AppAlert
        v-if="errorMessage"
        tone="danger"
        title="Settings unavailable"
        :message="errorMessage"
      />

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[0.8fr_1.2fr]">
        <AppCard title="Logo" icon="mdi-image-outline" tone="primary">
          <div class="tw-flex tw-flex-col tw-items-center tw-gap-4">
            <div class="org-settings__logo-preview">
              <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="Organization logo" />
              <v-icon v-else size="48" color="grey">mdi-image-off-outline</v-icon>
            </div>
            <v-file-input
              v-model="logoFile"
              label="Choose logo image"
              accept="image/png,image/jpeg,image/svg+xml,image/webp"
              variant="outlined"
              density="compact"
              prepend-icon="mdi-camera-outline"
              hide-details
            />
            <div class="tw-flex tw-gap-2 tw-w-full">
              <v-btn color="primary" variant="flat" block :loading="uploadingLogo" :disabled="!logoFile" @click="uploadLogo">
                Upload Logo
              </v-btn>
              <v-btn variant="outlined" color="error" :disabled="!settings.logo_url" :loading="removingLogo" @click="removeLogo">
                Remove
              </v-btn>
            </div>
            <p class="tw-text-xs tw-text-center" style="color: var(--qds-color-text-muted)">
              PNG, JPG, SVG, or WEBP — up to 2MB. Used on the landing page, login pages, and enrollment forms.
            </p>
          </div>
        </AppCard>

        <AppCard title="Identity & Branding" icon="mdi-bank-outline" tone="secondary">
          <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
            <v-text-field v-model="form.agency_name" label="Agency name" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field v-model="form.scheme_name" label="Scheme name" variant="outlined" density="comfortable" />
            <v-text-field v-model="form.scheme_tagline" label="Scheme tagline" variant="outlined" density="comfortable" />
          </div>
        </AppCard>
      </div>

      <AppCard title="Landing Page Content" icon="mdi-text-box-outline" tone="info">
        <div class="tw-grid tw-gap-4">
          <v-text-field v-model="form.hero_title" label="Hero title" variant="outlined" density="comfortable" />
          <v-textarea v-model="form.hero_description" label="Hero description" variant="outlined" density="comfortable" rows="3" />
          <v-text-field v-model="form.about_title" label="About section title" variant="outlined" density="comfortable" />
          <v-textarea v-model="form.about_description" label="About section description" variant="outlined" density="comfortable" rows="3" />
        </div>
      </AppCard>

      <AppCard title="Contact Information" icon="mdi-card-account-phone-outline" tone="warning">
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-3">
          <v-text-field v-model="form.hotline" label="Support hotline" variant="outlined" density="comfortable" prepend-inner-icon="mdi-phone-outline" />
          <v-text-field v-model="form.website" label="Website address" variant="outlined" density="comfortable" prepend-inner-icon="mdi-web" />
          <v-text-field v-model="form.address" label="Physical address" variant="outlined" density="comfortable" prepend-inner-icon="mdi-map-marker-outline" />
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppCard from '../common/AppCard.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import { organizationSettingsAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { useOrganizationSettings } from '../../composables/useOrganizationSettings'

const { success, error } = useToast()
const { settings, fetchSettings } = useOrganizationSettings()

const loading = ref(false)
const saving = ref(false)
const uploadingLogo = ref(false)
const removingLogo = ref(false)
const errorMessage = ref('')
const logoFile = ref(null)

const form = reactive({
  agency_name: '',
  scheme_name: '',
  scheme_tagline: '',
  hero_title: '',
  hero_description: '',
  hotline: '',
  website: '',
  address: '',
  about_title: '',
  about_description: '',
})

const logoPreviewUrl = computed(() => {
  if (logoFile.value && logoFile.value[0]) {
    return URL.createObjectURL(logoFile.value[0])
  }
  return settings.value.logo_url
})

const applySettings = (data = {}) => {
  Object.assign(form, {
    agency_name: data.agency_name ?? '',
    scheme_name: data.scheme_name ?? '',
    scheme_tagline: data.scheme_tagline ?? '',
    hero_title: data.hero_title ?? '',
    hero_description: data.hero_description ?? '',
    hotline: data.hotline ?? '',
    website: data.website ?? '',
    address: data.address ?? '',
    about_title: data.about_title ?? '',
    about_description: data.about_description ?? '',
  })
}

const loadSettings = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await organizationSettingsAPI.getConfig()
    const data = response.data?.data || {}
    settings.value = { ...settings.value, ...data }
    applySettings(data)
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Unable to load organization settings.'
    error(errorMessage.value)
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  errorMessage.value = ''

  try {
    const response = await organizationSettingsAPI.updateConfig({ ...form })
    const data = response.data?.data || {}
    settings.value = { ...settings.value, ...data }
    applySettings(data)
    success('Organization settings updated successfully')
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Unable to save organization settings.'
    error(errorMessage.value)
  } finally {
    saving.value = false
  }
}

const uploadLogo = async () => {
  if (!logoFile.value || !logoFile.value[0]) {
    return
  }

  uploadingLogo.value = true
  try {
    const formData = new FormData()
    formData.append('logo', logoFile.value[0])

    const response = await organizationSettingsAPI.uploadLogo(formData)
    const data = response.data?.data || {}
    settings.value = { ...settings.value, ...data }
    logoFile.value = null
    success('Organization logo updated successfully')
  } catch (err) {
    error(err.response?.data?.message || 'Unable to upload the logo.')
  } finally {
    uploadingLogo.value = false
  }
}

const removeLogo = async () => {
  removingLogo.value = true
  try {
    const response = await organizationSettingsAPI.removeLogo()
    const data = response.data?.data || {}
    settings.value = { ...settings.value, ...data }
    success('Organization logo removed')
  } catch (err) {
    error(err.response?.data?.message || 'Unable to remove the logo.')
  } finally {
    removingLogo.value = false
  }
}

onMounted(async () => {
  await fetchSettings()
  await loadSettings()
})
</script>

<style scoped>
.org-settings__logo-preview {
  width: 140px;
  height: 140px;
  display: grid;
  place-items: center;
  background: var(--qds-color-surface-muted);
  border: 1px solid var(--qds-color-border);
  overflow: hidden;
}
.org-settings__logo-preview img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
</style>
