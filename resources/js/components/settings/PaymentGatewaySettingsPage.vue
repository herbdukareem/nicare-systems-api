<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Payment Gateway Configuration"
        subtitle="Configure the secure online gateway used for self-enrollment and premium purchase checkout."
        kicker="Administration"
        icon="mdi-credit-card-settings-outline"
      >
        <v-btn color="primary" prepend-icon="mdi-content-save-outline" :loading="saving" @click="saveConfig">
          Save Configuration
        </v-btn>
      </AppPageHeader>

      <AppAlert
        title="Gateway-neutral checkout layer"
        tone="info"
        message="Self-enrollment and premium online purchases use this shared checkout configuration instead of collecting manual payment references."
      />

      <AppAlert
        v-if="errorMessage"
        tone="danger"
        title="Configuration unavailable"
        :message="errorMessage"
      />

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[0.8fr_1.2fr]">
        <AppCard title="Gateway Selection" icon="mdi-swap-horizontal" tone="primary">
          <div class="tw-space-y-4">
            <v-select
              v-model="form.active_gateway"
              :items="gatewayOptions"
              item-title="name"
              item-value="code"
              label="Active checkout gateway"
              variant="outlined"
              density="comfortable"
            />

            <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Current mode</p>
              <p class="tw-mt-2 tw-text-lg tw-font-semibold tw-text-slate-900">
                {{ activeGatewayConfig.enabled ? 'Hosted checkout enabled' : 'Hosted checkout disabled' }}
              </p>
              <p class="tw-mt-2 tw-text-sm tw-text-slate-600">
                Plans that require payment and use this gateway will launch the hosted checkout page directly.
              </p>
            </div>
          </div>
        </AppCard>

        <AppCard title="Gateway Credentials & Endpoints" icon="mdi-link-variant" tone="secondary">
          <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
            <v-switch
              v-model="activeGatewayConfig.enabled"
              color="primary"
              label="Enable hosted checkout"
              hide-details
              inset
              class="md:tw-col-span-2"
            />
            <v-text-field v-model="activeGatewayConfig.provider_name" label="Provider name" variant="outlined" density="comfortable" />
            <v-text-field v-model="activeGatewayConfig.currency" label="Currency" variant="outlined" density="comfortable" />
            <v-text-field v-model="activeGatewayConfig.base_url" label="Base URL" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field v-model="activeGatewayConfig.initialize_endpoint" label="Initialize endpoint" variant="outlined" density="comfortable" />
            <v-text-field v-model="activeGatewayConfig.verify_endpoint" label="Verify endpoint" variant="outlined" density="comfortable" />
            <v-text-field v-model="activeGatewayConfig.public_key" label="Public key" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field
              v-model="activeGatewayConfig.secret_key"
              label="Secret key"
              variant="outlined"
              density="comfortable"
              :type="showSecret ? 'text' : 'password'"
              class="md:tw-col-span-2"
            >
              <template #append-inner>
                <v-btn icon variant="text" size="small" @click="showSecret = !showSecret">
                  <v-icon>{{ showSecret ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}</v-icon>
                </v-btn>
              </template>
            </v-text-field>
            <v-text-field v-model="activeGatewayConfig.callback_path" label="Frontend return path" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field v-model.number="activeGatewayConfig.request_amount_multiplier" label="Amount multiplier" type="number" variant="outlined" density="comfortable" />
          </div>
        </AppCard>
      </div>

      <AppCard title="Response Mapping" icon="mdi-source-commit" tone="warning">
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
          <v-text-field v-model="activeGatewayConfig.response_paths.success" label="Response path: success flag" variant="outlined" density="comfortable" />
          <v-text-field v-model="activeGatewayConfig.response_paths.message" label="Response path: message" variant="outlined" density="comfortable" />
          <v-text-field v-model="activeGatewayConfig.response_paths.authorization_url" label="Response path: authorization URL" variant="outlined" density="comfortable" />
          <v-text-field v-model="activeGatewayConfig.response_paths.access_code" label="Response path: access code" variant="outlined" density="comfortable" />
          <v-text-field v-model="activeGatewayConfig.response_paths.reference" label="Response path: reference" variant="outlined" density="comfortable" />
          <v-text-field v-model="activeGatewayConfig.response_paths.paid_status" label="Response path: paid status" variant="outlined" density="comfortable" />
          <v-combobox
            v-model="activeGatewayConfig.successful_payment_values"
            label="Successful verification values"
            chips
            multiple
            variant="outlined"
            density="comfortable"
            class="md:tw-col-span-2"
          />
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
import { paymentGatewaySettingsAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { success, error } = useToast()

const saving = ref(false)
const loading = ref(false)
const showSecret = ref(false)
const errorMessage = ref('')
const gatewayOptions = ref([])

const defaultPaystackConfig = () => ({
  enabled: false,
  provider_name: 'Paystack',
  base_url: 'https://api.paystack.co',
  initialize_endpoint: '/transaction/initialize',
  verify_endpoint: '/transaction/verify/{reference}',
  public_key: '',
  secret_key: '',
  currency: 'NGN',
  callback_path: '/enroll/start?checkout_return=1',
  request_amount_multiplier: 100,
  response_paths: {
    success: 'status',
    message: 'message',
    authorization_url: 'data.authorization_url',
    access_code: 'data.access_code',
    reference: 'data.reference',
    paid_status: 'data.status',
  },
  successful_payment_values: ['success'],
})

const form = reactive({
  active_gateway: 'paystack',
  gateway_configurations: {
    paystack: defaultPaystackConfig(),
  },
})

const activeGatewayConfig = computed(() => {
  const code = form.active_gateway || 'paystack'

  if (!form.gateway_configurations[code]) {
    form.gateway_configurations[code] = defaultPaystackConfig()
  }

  return form.gateway_configurations[code]
})

const applyConfig = (payload = {}) => {
  gatewayOptions.value = payload.supported_gateways || []
  form.active_gateway = payload.active_gateway || 'paystack'
  form.gateway_configurations = {
    paystack: {
      ...defaultPaystackConfig(),
      ...(payload.gateway_configurations?.paystack || {}),
      response_paths: {
        ...defaultPaystackConfig().response_paths,
        ...(payload.gateway_configurations?.paystack?.response_paths || {}),
      },
      successful_payment_values: payload.gateway_configurations?.paystack?.successful_payment_values || ['success'],
    },
  }
}

const loadConfig = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await paymentGatewaySettingsAPI.getConfig()
    applyConfig(response.data?.data || {})
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Unable to load payment gateway configuration.'
    error(errorMessage.value)
  } finally {
    loading.value = false
  }
}

const saveConfig = async () => {
  saving.value = true
  errorMessage.value = ''

  try {
    const response = await paymentGatewaySettingsAPI.updateConfig({
      active_gateway: form.active_gateway,
      gateway_configurations: form.gateway_configurations,
    })
    applyConfig(response.data?.data || {})
    success('Payment gateway configuration saved')
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Unable to save payment gateway configuration.'
    error(errorMessage.value)
  } finally {
    saving.value = false
  }
}

onMounted(loadConfig)
</script>
