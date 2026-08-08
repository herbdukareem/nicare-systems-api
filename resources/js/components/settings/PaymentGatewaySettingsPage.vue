<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Payment Gateway Configuration"
        subtitle="Configure hosted online gateways, reusable subaccounts, and split profiles for premium checkout."
        kicker="Administration"
        icon="mdi-credit-card-settings-outline"
      >
        <v-btn color="primary" prepend-icon="mdi-content-save-outline" :loading="saving" @click="saveConfig">
          Save Configuration
        </v-btn>
      </AppPageHeader>

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
                {{ activeGatewayModeLabel }}
              </p>
              <p class="tw-mt-2 tw-text-sm tw-text-slate-600">
                {{ activeGatewayModeDescription }}
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
            <v-select
              v-model="activeGatewayConfig.mode"
              :items="gatewayModeOptions"
              label="Active mode"
              variant="outlined"
              density="comfortable"
            />

            <div class="md:tw-col-span-2 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
              <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
                <div>
                  <p class="tw-text-sm tw-font-semibold tw-text-slate-900">Credential sets</p>
                  <p class="tw-text-xs tw-text-slate-500">Save separate test and live credentials, endpoints, and response mapping.</p>
                </div>
                <v-btn-toggle
                  v-model="environmentEditorMode"
                  mandatory
                  color="primary"
                  density="comfortable"
                  variant="outlined"
                >
                  <v-btn value="test">Test setup</v-btn>
                  <v-btn value="live">Live setup</v-btn>
                </v-btn-toggle>
              </div>
            </div>

            <v-text-field v-model="selectedGatewayEnvironmentConfig.currency" label="Currency" variant="outlined" density="comfortable" />
            <v-text-field
              :model-value="environmentEditorMode === 'live' ? 'Live configuration' : 'Test configuration'"
              label="Editing"
              variant="outlined"
              density="comfortable"
              readonly
            />
            <v-text-field v-model="selectedGatewayEnvironmentConfig.base_url" label="Base URL" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field
              v-if="form.active_gateway === 'monnify'"
              v-model="selectedGatewayEnvironmentConfig.login_endpoint"
              label="Login endpoint"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field v-model="selectedGatewayEnvironmentConfig.initialize_endpoint" label="Initialize endpoint" variant="outlined" density="comfortable" />
            <v-text-field v-model="selectedGatewayEnvironmentConfig.verify_endpoint" label="Verify endpoint" variant="outlined" density="comfortable" />
            <v-text-field
              v-if="form.active_gateway === 'paystack'"
              v-model="selectedGatewayEnvironmentConfig.public_key"
              label="Public key"
              variant="outlined"
              density="comfortable"
              class="md:tw-col-span-2"
            />
            <v-text-field
              v-if="form.active_gateway === 'monnify'"
              v-model="selectedGatewayEnvironmentConfig.api_key"
              label="API key"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field
              v-if="form.active_gateway === 'monnify'"
              v-model="selectedGatewayEnvironmentConfig.contract_code"
              label="Contract code"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field
              v-if="form.active_gateway === 'quickteller'"
              v-model="selectedGatewayEnvironmentConfig.merchant_code"
              label="Merchant code"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field
              v-if="form.active_gateway === 'quickteller'"
              v-model="selectedGatewayEnvironmentConfig.pay_item_id"
              label="Pay item ID"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field
              v-if="form.active_gateway !== 'quickteller'"
              v-model="selectedGatewayEnvironmentConfig.secret_key"
              :label="form.active_gateway === 'paystack' ? 'Secret key' : form.active_gateway === 'remita' ? 'Secret key header value' : 'Secret key'"
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
            <v-text-field v-model="selectedGatewayEnvironmentConfig.callback_path" label="Frontend return path" variant="outlined" density="comfortable" class="md:tw-col-span-2" />
            <v-text-field v-model.number="selectedGatewayEnvironmentConfig.request_amount_multiplier" label="Amount multiplier" type="number" variant="outlined" density="comfortable" />
            <v-combobox
              v-if="form.active_gateway === 'monnify'"
              v-model="selectedGatewayEnvironmentConfig.payment_methods"
              :items="monnifyPaymentMethodOptions"
              label="Allowed Monnify payment methods"
              chips
              multiple
              variant="outlined"
              density="comfortable"
              class="md:tw-col-span-2"
            />

            <div class="md:tw-col-span-2 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
              <div class="tw-flex tw-flex-wrap tw-items-start tw-justify-between tw-gap-3">
                <div>
                  <p class="tw-text-sm tw-font-semibold tw-text-slate-900">Checkout processing fee</p>
                  <p class="tw-mt-1 tw-text-xs tw-text-slate-600">Use the provider's actual commercial terms. When enabled, the payer sees the fee separately and the gateway receives the total payable amount.</p>
                </div>
                <v-switch v-model="selectedGatewayEnvironmentConfig.checkout_fee_policy.customer_bears_processing_fee" color="primary" label="Customer pays fee" hide-details inset />
              </div>
              <div v-if="selectedGatewayEnvironmentConfig.checkout_fee_policy.customer_bears_processing_fee" class="tw-mt-4 tw-grid tw-gap-4 md:tw-grid-cols-3">
                <v-text-field v-model.number="selectedGatewayEnvironmentConfig.checkout_fee_policy.percentage" label="Percentage fee" type="number" min="0" step="0.01" suffix="%" variant="outlined" density="comfortable" />
                <v-text-field v-model.number="selectedGatewayEnvironmentConfig.checkout_fee_policy.flat_amount" label="Flat fee" type="number" min="0" step="0.01" prefix="₦" variant="outlined" density="comfortable" />
                <v-text-field v-model.number="selectedGatewayEnvironmentConfig.checkout_fee_policy.maximum_amount" label="Fee cap (optional)" type="number" min="0" step="0.01" prefix="₦" variant="outlined" density="comfortable" hint="Use 0 for no cap" persistent-hint />
              </div>
            </div>
          </div>
        </AppCard>
      </div>

      <AppCard title="Response Mapping" icon="mdi-source-commit" tone="warning">
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
          <v-text-field v-model="selectedGatewayEnvironmentConfig.response_paths.success" label="Response path: success flag" variant="outlined" density="comfortable" />
          <v-text-field v-model="selectedGatewayEnvironmentConfig.response_paths.message" label="Response path: message" variant="outlined" density="comfortable" />
          <v-text-field v-model="selectedGatewayEnvironmentConfig.response_paths.authorization_url" label="Response path: authorization URL" variant="outlined" density="comfortable" />
          <v-text-field v-model="selectedGatewayEnvironmentConfig.response_paths.access_code" label="Response path: access code" variant="outlined" density="comfortable" />
          <v-text-field v-model="selectedGatewayEnvironmentConfig.response_paths.reference" label="Response path: reference" variant="outlined" density="comfortable" />
          <v-text-field v-model="selectedGatewayEnvironmentConfig.response_paths.paid_status" label="Response path: paid status" variant="outlined" density="comfortable" />
          <v-combobox
            v-model="selectedGatewayEnvironmentConfig.successful_payment_values"
            label="Successful verification values"
            chips
            multiple
            variant="outlined"
            density="comfortable"
            class="md:tw-col-span-2"
          />
        </div>
      </AppCard>

      <AppCard title="Gateway Subaccounts" icon="mdi-bank-outline" tone="primary">
        <div class="tw-space-y-4">
          <p class="tw-text-sm tw-text-slate-600">
            Register only reusable settlement targets for gateways that support split settlement. Each subaccount stores the provider-issued external code used during hosted checkout.
          </p>

          <div
            v-for="(subaccount, index) in form.subaccounts"
            :key="`subaccount-${index}`"
            class="tw-rounded-xl tw-border tw-border-slate-200 tw-p-4 tw-space-y-4"
          >
            <div class="tw-flex tw-items-center tw-justify-between">
              <div class="tw-font-semibold tw-text-slate-900">{{ subaccount.name || `Subaccount ${index + 1}` }}</div>
              <v-btn color="error" variant="text" size="small" prepend-icon="mdi-delete-outline" @click="removeSubaccount(index)">
                Remove
              </v-btn>
            </div>

            <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
              <v-text-field v-model="subaccount.code" label="Internal code" variant="outlined" density="comfortable" />
              <v-select v-model="subaccount.gateway_code" :items="splitGatewayOptions" item-title="name" item-value="code" label="Gateway" variant="outlined" density="comfortable" />
              <v-text-field v-model="subaccount.name" label="Display name" variant="outlined" density="comfortable" />
              <v-text-field v-model="subaccount.external_code" :label="subaccountExternalLabel(subaccount.gateway_code)" variant="outlined" density="comfortable" />
              <v-text-field v-if="showSubaccountCurrency(subaccount.gateway_code)" v-model="subaccount.currency" label="Currency" variant="outlined" density="comfortable" />
              <v-text-field v-if="showSubaccountAccountName(subaccount.gateway_code)" v-model="subaccount.account_name" label="Account name" variant="outlined" density="comfortable" />
              <v-text-field v-if="showSubaccountBankCode(subaccount.gateway_code)" v-model="subaccount.bank_code" label="Bank code" variant="outlined" density="comfortable" />
              <v-text-field v-if="showSubaccountAccountNumber(subaccount.gateway_code)" v-model="subaccount.account_number" label="Account number" variant="outlined" density="comfortable" />
              <v-text-field v-if="showSubaccountEmail(subaccount.gateway_code)" v-model="subaccount.email" label="Contact email" variant="outlined" density="comfortable" />
              <v-switch v-model="subaccount.active" color="primary" label="Active" hide-details inset />
            </div>
          </div>

          <v-btn color="primary" variant="tonal" prepend-icon="mdi-plus" @click="addSubaccount">
            Add Subaccount
          </v-btn>
        </div>
      </AppCard>

      <AppCard title="Split Profiles" icon="mdi-source-branch" tone="secondary">
        <div class="tw-space-y-4">
          <p class="tw-text-sm tw-text-slate-600">
            Split profiles are reusable settlement instructions attached to premium plans. A plan chooses one profile, and the selected gateway adapter translates it into the provider-specific split payload during checkout.
          </p>

          <div
            v-for="(profile, profileIndex) in form.split_profiles"
            :key="`split-profile-${profileIndex}`"
            class="tw-rounded-xl tw-border tw-border-slate-200 tw-p-4 tw-space-y-4"
          >
            <div class="tw-flex tw-items-center tw-justify-between">
              <div class="tw-font-semibold tw-text-slate-900">{{ profile.name || `Split Profile ${profileIndex + 1}` }}</div>
              <v-btn color="error" variant="text" size="small" prepend-icon="mdi-delete-outline" @click="removeSplitProfile(profileIndex)">
                Remove
              </v-btn>
            </div>

            <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
              <v-text-field v-model="profile.code" label="Profile code" variant="outlined" density="comfortable" />
              <v-text-field v-model="profile.name" label="Profile name" variant="outlined" density="comfortable" />
              <v-select
                v-model="profile.gateway_code"
                :items="splitGatewayOptions"
                item-title="name"
                item-value="code"
                label="Gateway"
                variant="outlined"
                density="comfortable"
                @update:model-value="normalizeSplitProfileGateway(profile)"
              />
              <v-switch v-model="profile.active" color="primary" label="Active" hide-details inset />
              <v-select
                v-if="profile.gateway_code === 'paystack'"
                v-model="profile.settings.paystack.bearer_type"
                :items="paystackBearerTypes"
                label="Paystack fee bearer"
                variant="outlined"
                density="comfortable"
              />
              <v-select
                v-if="profile.gateway_code === 'paystack' && profile.settings.paystack.bearer_type === 'subaccount'"
                v-model="profile.settings.paystack.bearer_subaccount_code"
                :items="subaccountsForGateway(profile.gateway_code)"
                item-title="name"
                item-value="code"
                label="Paystack bearer subaccount"
                variant="outlined"
                density="comfortable"
              />
            </div>

            <div class="tw-space-y-3">
              <div class="tw-flex tw-items-center tw-justify-between">
                <div class="tw-text-sm tw-font-semibold tw-text-slate-800">Profile entries</div>
                <v-btn color="primary" variant="text" size="small" prepend-icon="mdi-plus" @click="addSplitEntry(profile)">
                  Add Entry
                </v-btn>
              </div>

              <div
                v-for="(entry, entryIndex) in profile.entries"
                :key="`split-entry-${profileIndex}-${entryIndex}`"
                class="tw-rounded-lg tw-border tw-border-slate-200 tw-p-3 tw-grid tw-gap-4 md:tw-grid-cols-2"
              >
                <v-select
                  v-model="entry.subaccount_code"
                  :items="subaccountsForGateway(profile.gateway_code)"
                  item-title="name"
                  item-value="code"
                  label="Subaccount"
                  variant="outlined"
                  density="comfortable"
                />
                <v-select
                  v-model="entry.share_type"
                  :items="shareTypeOptionsForGateway(profile.gateway_code)"
                  label="Share type"
                  variant="outlined"
                  density="comfortable"
                  :disabled="profile.gateway_code === 'remita'"
                />
                <v-text-field
                  v-model.number="entry.share_value"
                  :label="entry.share_type === 'flat' ? 'Flat share amount' : 'Share percentage'"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                />
                <v-switch
                  v-if="showSplitEntryFeeBearer(profile.gateway_code)"
                  v-model="entry.fee_bearer"
                  color="primary"
                  :label="profile.gateway_code === 'monnify' ? 'Subaccount bears Monnify fee' : 'Fee bearer'"
                  hide-details
                  inset
                />
                <v-text-field
                  v-if="profile.gateway_code === 'monnify'"
                  v-model.number="entry.fee_percentage"
                  label="Monnify fee percentage"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                />
                <div class="tw-flex tw-items-end">
                  <v-btn color="error" variant="text" size="small" prepend-icon="mdi-delete-outline" @click="removeSplitEntry(profile, entryIndex)">
                    Remove Entry
                  </v-btn>
                </div>
              </div>
            </div>
          </div>

          <v-btn color="primary" variant="tonal" prepend-icon="mdi-plus" @click="addSplitProfile">
            Add Split Profile
          </v-btn>
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
const environmentEditorMode = ref('test')

const monnifyPaymentMethodOptions = ['CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER']
const gatewayModeOptions = ['TEST', 'LIVE']
const paystackBearerTypes = [
  { title: 'Account', value: 'account' },
  { title: 'Subaccount', value: 'subaccount' },
  { title: 'All', value: 'all' },
  { title: 'All Proportional', value: 'all-proportional' },
]
const shareTypeOptions = [
  { title: 'Percentage', value: 'percentage' },
  { title: 'Flat Amount', value: 'flat' },
]

const defaultPaystackEnvironment = () => ({
  base_url: 'https://api.paystack.co',
  initialize_endpoint: '/transaction/initialize',
  verify_endpoint: '/transaction/verify/{reference}',
  public_key: '',
  secret_key: '',
  currency: 'NGN',
  callback_path: '/enroll/start?checkout_return=1',
  request_amount_multiplier: 100,
  checkout_fee_policy: { customer_bears_processing_fee: true, percentage: 1.5, flat_amount: 100, maximum_amount: 0 },
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

const defaultMonnifyEnvironment = (environment = 'test') => ({
  base_url: environment === 'live' ? 'https://api.monnify.com' : 'https://sandbox.monnify.com',
  login_endpoint: '/api/v1/auth/login',
  initialize_endpoint: '/api/v1/merchant/transactions/init-transaction',
  verify_endpoint: '/api/v2/merchant/transactions/query?paymentReference={reference}',
  api_key: '',
  secret_key: '',
  contract_code: '',
  currency: 'NGN',
  callback_path: '/enroll/start?checkout_return=1',
  payment_methods: ['CARD', 'ACCOUNT_TRANSFER', 'USSD'],
  request_amount_multiplier: 1,
  checkout_fee_policy: { customer_bears_processing_fee: false, percentage: 0, flat_amount: 0, maximum_amount: 0 },
  response_paths: {
    success: 'requestSuccessful',
    message: 'responseMessage',
    authorization_url: 'responseBody.checkoutUrl',
    access_code: 'responseBody.transactionReference',
    reference: 'responseBody.paymentReference',
    paid_status: 'responseBody.paymentStatus',
  },
  successful_payment_values: ['PAID'],
})

const defaultRemitaEnvironment = (environment = 'test') => ({
  base_url: environment === 'live' ? 'https://api.remita.net' : 'https://api-demo.systemspecsng.com',
  initialize_endpoint: '/services/connect-gateway/api/v1/payment/charge',
  verify_endpoint: '/services/connect-gateway/api/v1/payment-engine/payment/merchant/verify/{reference}',
  secret_key: '',
  currency: 'NGN',
  callback_path: '/enroll/start?checkout_return=1',
  request_amount_multiplier: 1,
  checkout_fee_policy: { customer_bears_processing_fee: false, percentage: 0, flat_amount: 0, maximum_amount: 0 },
  response_paths: {
    success: 'status',
    message: 'message',
    authorization_url: 'data.paymentLink',
    access_code: 'data.paymentIdentifier',
    reference: 'data.paymentIdentifier',
    paid_status: 'data.status',
  },
  successful_payment_values: ['SUCCESS', 'APPROVED', '00'],
})

const defaultQuicktellerEnvironment = (environment = 'test') => ({
  base_url: environment === 'live' ? 'https://webpay.interswitchng.com' : 'https://sandbox.interswitchng.com',
  initialize_endpoint: environment === 'live'
    ? 'https://webpay.interswitchng.com/collections/w/pay'
    : 'https://newwebpay-sandbox.interswitchng.com/collections/w/pay',
  verify_endpoint: '/collections/api/v1/gettransaction.json?merchantcode={merchant_code}&transactionreference={reference}&amount={amount}',
  merchant_code: '',
  pay_item_id: '',
  currency: '566',
  callback_path: '/enroll/start?checkout_return=1',
  request_amount_multiplier: 100,
  checkout_fee_policy: { customer_bears_processing_fee: false, percentage: 0, flat_amount: 0, maximum_amount: 0 },
  response_paths: {
    success: '',
    message: 'ResponseDescription',
    authorization_url: '',
    access_code: 'PaymentReference',
    reference: 'MerchantReference',
    paid_status: 'ResponseCode',
  },
  successful_payment_values: ['00'],
})

const defaultPaystackConfig = () => ({
  enabled: false,
  provider_name: 'Paystack',
  mode: 'TEST',
  environments: {
    test: defaultPaystackEnvironment(),
    live: defaultPaystackEnvironment(),
  },
})

const defaultMonnifyConfig = () => ({
  enabled: false,
  provider_name: 'Monnify',
  mode: 'TEST',
  environments: {
    test: defaultMonnifyEnvironment('test'),
    live: defaultMonnifyEnvironment('live'),
  },
})

const defaultRemitaConfig = () => ({
  enabled: false,
  provider_name: 'Remita',
  mode: 'TEST',
  environments: {
    test: defaultRemitaEnvironment('test'),
    live: defaultRemitaEnvironment('live'),
  },
})

const defaultQuicktellerConfig = () => ({
  enabled: false,
  provider_name: 'Quickteller',
  mode: 'TEST',
  environments: {
    test: defaultQuicktellerEnvironment('test'),
    live: defaultQuicktellerEnvironment('live'),
  },
})

const blankSubaccount = () => ({
  code: '',
  gateway_code: 'paystack',
  name: '',
  external_code: '',
  currency: 'NGN',
  account_name: '',
  bank_code: '',
  account_number: '',
  email: '',
  active: true,
})

const blankSplitEntry = () => ({
  subaccount_code: '',
  share_type: 'percentage',
  share_value: 0,
  fee_bearer: false,
  fee_percentage: 0,
})

const blankSplitProfile = () => ({
  code: '',
  name: '',
  gateway_code: 'paystack',
  active: true,
  settings: {
    paystack: {
      bearer_type: 'account',
      bearer_subaccount_code: '',
    },
  },
  entries: [blankSplitEntry()],
})

const form = reactive({
  active_gateway: 'paystack',
  gateway_configurations: {
    paystack: defaultPaystackConfig(),
    monnify: defaultMonnifyConfig(),
    remita: defaultRemitaConfig(),
    quickteller: defaultQuicktellerConfig(),
  },
  subaccounts: [],
  split_profiles: [],
})

const defaultGatewayConfig = (code) => {
  if (code === 'monnify') return defaultMonnifyConfig()
  if (code === 'remita') return defaultRemitaConfig()
  if (code === 'quickteller') return defaultQuicktellerConfig()
  return defaultPaystackConfig()
}

const activeGatewayConfig = computed(() => {
  const code = form.active_gateway || 'paystack'

  if (!form.gateway_configurations[code]) {
    form.gateway_configurations[code] = defaultGatewayConfig(code)
  }

  return form.gateway_configurations[code]
})

const selectedGatewayEnvironmentConfig = computed(() => {
  const code = form.active_gateway || 'paystack'
  const activeConfig = activeGatewayConfig.value
  const environmentKey = environmentEditorMode.value === 'live' ? 'live' : 'test'
  const defaults = defaultGatewayConfig(code)

  if (!activeConfig.environments) {
    activeConfig.environments = defaults.environments
  }

  if (!activeConfig.environments[environmentKey]) {
    activeConfig.environments[environmentKey] = defaults.environments[environmentKey]
  }

  if (!activeConfig.environments[environmentKey].response_paths) {
    activeConfig.environments[environmentKey].response_paths = {
      ...defaults.environments[environmentKey].response_paths,
    }
  }

  if (!Array.isArray(activeConfig.environments[environmentKey].successful_payment_values)) {
    activeConfig.environments[environmentKey].successful_payment_values = [...(defaults.environments[environmentKey].successful_payment_values || [])]
  }

  if (activeConfig.environments[environmentKey].payment_methods === undefined && defaults.environments[environmentKey].payment_methods) {
    activeConfig.environments[environmentKey].payment_methods = [...defaults.environments[environmentKey].payment_methods]
  }

  return activeConfig.environments[environmentKey]
})

const activeGatewayName = computed(() => {
  return gatewayOptions.value.find((item) => item.code === form.active_gateway)?.name || 'Configured gateway'
})

const activeGatewayMode = computed(() => {
  return (activeGatewayConfig.value.mode || 'TEST').toUpperCase()
})

const activeGatewayModeLabel = computed(() => {
  const prefix = activeGatewayMode.value === 'LIVE' ? 'Live mode' : 'Test mode'

  return activeGatewayConfig.value.enabled ? `${prefix} enabled` : `${prefix} selected`
})

const activeGatewayModeDescription = computed(() => {
  const modeText = activeGatewayMode.value === 'LIVE' ? 'live transactions' : 'test transactions'
  const checkoutText = activeGatewayConfig.value.enabled ? 'Hosted checkout is enabled.' : 'Hosted checkout is currently disabled.'

  return `${activeGatewayName.value} is configured for ${modeText}. ${checkoutText}`
})

const splitGatewayOptions = computed(() => {
  return (gatewayOptions.value || []).filter((item) => item.supports_split_profiles)
})

const normalizeEnvironmentConfig = (baseEnvironment, environmentConfig = {}) => ({
  ...baseEnvironment,
  ...(environmentConfig || {}),
  response_paths: {
    ...(baseEnvironment.response_paths || {}),
    ...(environmentConfig?.response_paths || {}),
  },
  checkout_fee_policy: {
    ...(baseEnvironment.checkout_fee_policy || {}),
    ...(environmentConfig?.checkout_fee_policy || {}),
  },
  successful_payment_values: Array.isArray(environmentConfig?.successful_payment_values)
    ? environmentConfig.successful_payment_values
    : [...(baseEnvironment.successful_payment_values || [])],
  payment_methods: Array.isArray(environmentConfig?.payment_methods)
    ? environmentConfig.payment_methods
    : (baseEnvironment.payment_methods ? [...baseEnvironment.payment_methods] : undefined),
})

const normalizeGatewayConfig = (code, config) => {
  const base = defaultGatewayConfig(code)
  const environments = config?.environments || {}
  const legacyEnvironment = config?.environments
    ? {}
    : Object.fromEntries(
        Object.keys(base.environments.test || {})
          .filter((key) => Object.prototype.hasOwnProperty.call(config || {}, key))
          .map((key) => [key, config[key]])
      )

  return {
    ...base,
    ...(config || {}),
    mode: (config?.mode || base.mode || 'TEST').toUpperCase(),
    environments: {
      test: normalizeEnvironmentConfig(base.environments.test, { ...legacyEnvironment, ...(environments.test || {}) }),
      live: normalizeEnvironmentConfig(base.environments.live, { ...legacyEnvironment, ...(environments.live || {}) }),
    },
  }
}

const subaccountsForGateway = (gatewayCode) => {
  return (form.subaccounts || []).filter((item) => item.gateway_code === gatewayCode)
}

const subaccountExternalLabel = (gatewayCode) => {
  if (gatewayCode === 'monnify') return 'Monnify subAccountCode'
  if (gatewayCode === 'remita') return 'Remita subAccountId'
  return 'Paystack subaccount code'
}

const showSubaccountCurrency = (gatewayCode) => ['monnify', 'remita'].includes(gatewayCode)
const showSubaccountAccountName = (gatewayCode) => ['monnify', 'remita'].includes(gatewayCode)
const showSubaccountBankCode = (gatewayCode) => ['monnify', 'remita'].includes(gatewayCode)
const showSubaccountAccountNumber = (gatewayCode) => ['monnify', 'remita'].includes(gatewayCode)
const showSubaccountEmail = (gatewayCode) => gatewayCode === 'monnify'

const shareTypeOptionsForGateway = (gatewayCode) => {
  if (gatewayCode === 'remita') {
    return [{ title: 'Flat Amount', value: 'flat' }]
  }

  return shareTypeOptions
}

const showSplitEntryFeeBearer = (gatewayCode) => ['monnify', 'remita'].includes(gatewayCode)

const normalizeSplitProfileGateway = (profile) => {
  if (profile.gateway_code === 'remita') {
    profile.entries = (profile.entries || []).map((entry) => ({
      ...entry,
      share_type: 'flat',
      fee_percentage: 0,
    }))
  }

  if (profile.gateway_code === 'paystack') {
    profile.entries = (profile.entries || []).map((entry) => ({
      ...entry,
      fee_bearer: false,
      fee_percentage: 0,
    }))
  }
}

const normalizeSplitProfile = (profile = {}) => {
  const normalized = {
    ...blankSplitProfile(),
    ...profile,
    settings: {
      paystack: {
        ...blankSplitProfile().settings.paystack,
        ...(profile.settings?.paystack || {}),
      },
    },
    entries: (profile.entries || []).length
      ? profile.entries.map((entry) => ({ ...blankSplitEntry(), ...entry }))
      : [blankSplitEntry()],
  }

  normalizeSplitProfileGateway(normalized)

  return normalized
}

const applyConfig = (payload = {}) => {
  gatewayOptions.value = payload.supported_gateways || []
  form.active_gateway = payload.active_gateway || 'paystack'
  environmentEditorMode.value = 'test'
  form.gateway_configurations = {
    paystack: normalizeGatewayConfig('paystack', payload.gateway_configurations?.paystack),
    monnify: normalizeGatewayConfig('monnify', payload.gateway_configurations?.monnify),
    remita: normalizeGatewayConfig('remita', payload.gateway_configurations?.remita),
    quickteller: normalizeGatewayConfig('quickteller', payload.gateway_configurations?.quickteller),
  }
  form.subaccounts = (payload.subaccounts || []).map((item) => ({ ...blankSubaccount(), ...item }))
  form.split_profiles = (payload.split_profiles || []).map((item) => normalizeSplitProfile(item))
}

const addSubaccount = () => {
  form.subaccounts.push(blankSubaccount())
}

const removeSubaccount = (index) => {
  form.subaccounts.splice(index, 1)
}

const addSplitProfile = () => {
  form.split_profiles.push(blankSplitProfile())
}

const removeSplitProfile = (index) => {
  form.split_profiles.splice(index, 1)
}

const addSplitEntry = (profile) => {
  profile.entries.push(blankSplitEntry())
  normalizeSplitProfileGateway(profile)
}

const removeSplitEntry = (profile, index) => {
  profile.entries.splice(index, 1)
  if (!profile.entries.length) {
    profile.entries.push(blankSplitEntry())
  }
  normalizeSplitProfileGateway(profile)
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
      subaccounts: form.subaccounts,
      split_profiles: form.split_profiles,
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
