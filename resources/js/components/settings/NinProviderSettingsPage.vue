<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="NIN Provider Configuration"
        subtitle="Manage the provider endpoint, request mapping, and response field mapping used during enrollee approval."
        kicker="Administration"
        icon="mdi-card-account-details-outline"
      >
        <v-btn color="primary" prepend-icon="mdi-content-save-outline" :loading="saving" @click="saveConfig">
          Save Configuration
        </v-btn>
      </AppPageHeader>

      <AppAlert
        title="Provider swap ready"
        tone="info"
        message="This configuration layer keeps the NIN integration provider-agnostic. If the scheme changes provider later, update the endpoint and field mappings here instead of changing approval code."
      />

      <AppAlert
        v-if="errorMessage"
        tone="danger"
        title="Configuration unavailable"
        :message="errorMessage"
      />

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[1.1fr_.9fr]">
        <AppCard title="Connection Settings" icon="mdi-lan-connect" tone="primary">
          <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
            <v-switch
              v-model="form.enabled"
              color="primary"
              label="Enable NIN verification during approval"
              inset
              hide-details
              class="md:tw-col-span-2"
            />
            <v-text-field v-model="form.provider_name" label="Provider name" variant="outlined" density="comfortable" />
            <v-select
              v-model="form.auth_type"
              :items="authTypes"
              label="Auth type"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field v-model="form.base_url" label="Base URL" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field v-model="form.verify_endpoint" label="Verification endpoint" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field
              v-model="form.api_key"
              label="API key"
              variant="outlined"
              density="comfortable"
              :type="showApiKey ? 'text' : 'password'"
              class="md:tw-col-span-2"
            >
              <template #append-inner>
                <v-btn icon variant="text" size="small" @click="showApiKey = !showApiKey">
                  <v-icon>{{ showApiKey ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}</v-icon>
                </v-btn>
              </template>
            </v-text-field>
            <v-select
              v-model="form.request_method"
              :items="requestMethods"
              label="Request method"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field v-model.number="form.timeout_seconds" label="Timeout (seconds)" type="number" variant="outlined" density="comfortable" />
          </div>
        </AppCard>

        <AppCard title="Request & Response Mapping" icon="mdi-source-branch" tone="secondary">
          <div class="tw-space-y-4">
            <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
              <v-text-field v-model="form.request_nin_field" label="Request NIN field" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.request_consent_field" label="Request consent field" variant="outlined" density="comfortable" />
              <v-select
                v-model="form.request_consent_value"
                :items="consentOptions"
                label="Default consent value"
                item-title="label"
                item-value="value"
                variant="outlined"
                density="comfortable"
              />
              <v-text-field v-model="form.success_path" label="Response success path" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.data_path" label="Response data path" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            </div>

            <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
              <v-text-field v-model="form.field_map.nin" label="Map: NIN" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.first_name" label="Map: First name" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.middle_name" label="Map: Middle name" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.last_name" label="Map: Last name" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.date_of_birth" label="Map: Date of birth" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.gender" label="Map: Gender" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.phone" label="Map: Phone" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.photo" label="Map: Photo" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.field_map.address" label="Map: Address" variant="outlined" density="comfortable" />
            </div>
          </div>
        </AppCard>
      </div>

      <AppCard title="Current Approval Behaviour" icon="mdi-shield-check-outline" tone="warning">
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-3">
          <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
            <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Status</p>
            <p class="tw-mt-2 tw-text-lg tw-font-semibold tw-text-slate-900">{{ form.enabled ? 'Verification enforced' : 'Verification disabled' }}</p>
          </div>
          <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
            <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Target</p>
            <p class="tw-mt-2 tw-text-lg tw-font-semibold tw-text-slate-900">{{ form.base_url }}{{ form.verify_endpoint }}</p>
          </div>
          <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
            <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Payload</p>
            <p class="tw-mt-2 tw-text-lg tw-font-semibold tw-text-slate-900">{{ form.request_nin_field }} + {{ form.request_consent_field }}</p>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppCard from '../common/AppCard.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import { ninProviderAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { success, error } = useToast()

const authTypes = ['bearer']
const requestMethods = ['POST', 'GET']
const consentOptions = [
  { label: 'true', value: true },
  { label: 'false', value: false },
]

const saving = ref(false)
const loading = ref(false)
const showApiKey = ref(false)
const errorMessage = ref('')

const defaultFieldMap = () => ({
  nin: 'nin',
  first_name: 'first_name',
  middle_name: 'middle_name',
  last_name: 'last_name',
  date_of_birth: 'date_of_birth',
  gender: 'gender',
  phone: 'phone',
  photo: 'photo',
  address: 'address',
})

const form = reactive({
  provider_name: 'Ashlab Verify',
  enabled: false,
  base_url: 'https://api.verify.ashlabtech.ng',
  verify_endpoint: '/api/v1/verify/nin',
  auth_type: 'bearer',
  api_key: '',
  request_method: 'POST',
  request_nin_field: 'nin',
  request_consent_field: 'consent',
  request_consent_value: true,
  success_path: 'success',
  data_path: 'data',
  timeout_seconds: 15,
  field_map: defaultFieldMap(),
})

const applyConfig = (config = {}) => {
  Object.assign(form, {
    provider_name: config.provider_name ?? 'Ashlab Verify',
    enabled: !!config.enabled,
    base_url: config.base_url ?? 'https://api.verify.ashlabtech.ng',
    verify_endpoint: config.verify_endpoint ?? '/api/v1/verify/nin',
    auth_type: config.auth_type ?? 'bearer',
    api_key: config.api_key ?? '',
    request_method: config.request_method ?? 'POST',
    request_nin_field: config.request_nin_field ?? 'nin',
    request_consent_field: config.request_consent_field ?? 'consent',
    request_consent_value: config.request_consent_value ?? true,
    success_path: config.success_path ?? 'success',
    data_path: config.data_path ?? 'data',
    timeout_seconds: Number(config.timeout_seconds ?? 15),
    field_map: { ...defaultFieldMap(), ...(config.field_map || {}) },
  })
}

const loadConfig = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await ninProviderAPI.getConfig()
    applyConfig(response.data?.data || {})
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Unable to load NIN provider configuration.'
    error(errorMessage.value)
  } finally {
    loading.value = false
  }
}

const saveConfig = async () => {
  saving.value = true
  errorMessage.value = ''

  try {
    const response = await ninProviderAPI.updateConfig({
      ...form,
      timeout_seconds: Number(form.timeout_seconds || 15),
    })
    applyConfig(response.data?.data || {})
    success('NIN provider configuration saved')
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Unable to save NIN provider configuration.'
    error(errorMessage.value)
  } finally {
    saving.value = false
  }
}

onMounted(loadConfig)
</script>
