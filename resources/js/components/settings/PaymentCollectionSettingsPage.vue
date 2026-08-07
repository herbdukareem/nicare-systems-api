<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader title="Virtual Account Collection" subtitle="Configure Paystack temporary and reusable virtual-account collection." kicker="Administration" icon="mdi-bank-transfer-in">
        <v-btn color="primary" prepend-icon="mdi-content-save-outline" :loading="saving" @click="save">Save settings</v-btn>
      </AppPageHeader>
      <AppAlert v-if="errorMessage" tone="danger" title="Settings unavailable" :message="errorMessage" />
      <AppCard title="Paystack collection policy" icon="mdi-bank-outline" tone="primary">
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
          <v-switch v-model="form.enabled" label="Enable Paystack virtual accounts" color="primary" inset hide-details class="md:tw-col-span-2" />
          <v-select v-model="form.default_mode" :items="modes" label="Default account mode" variant="outlined" density="comfortable" />
          <v-select v-model="form.allow_modes" :items="modes" label="Allowed account modes" multiple chips variant="outlined" density="comfortable" />
          <v-text-field v-model.number="form.per_payment.expiry_minutes" type="number" min="15" max="480" label="Temporary account expiry (minutes)" hint="Paystack permits 15 minutes to 8 hours." persistent-hint variant="outlined" density="comfortable" />
          <v-text-field v-model.number="form.per_payer.intent_expiry_hours" type="number" min="1" max="168" label="Enrollment / renewal intent expiry (hours)" variant="outlined" density="comfortable" />
          <v-text-field v-model.number="form.bulk_pin.intent_expiry_hours" type="number" min="24" max="168" label="Bulk PIN intent expiry (hours)" variant="outlined" density="comfortable" />
          <v-switch v-model="form.exact_amount_only" label="Require exact transfer amount" color="primary" inset hide-details />
          <v-switch v-model="form.refunds_allowed" label="Allow permission-gated refunds" color="primary" inset hide-details />
        </div>
      </AppCard>
      <AppCard title="Operational safeguards" icon="mdi-shield-check-outline" tone="warning">
        <p class="tw-text-sm tw-text-slate-600">Webhook settlement is automatic and idempotent. Manual settlement, refunds, and exception review require the dedicated payment-collection permissions; they are never granted simply by role name.</p>
      </AppCard>
    </div>
  </AdminLayout>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppCard from '../common/AppCard.vue'
import AppAlert from '../common/AppAlert.vue'
import { paymentCollectionSettingsAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
const { success, error } = useToast()
const saving = ref(false); const errorMessage = ref('')
const modes = [{ title: 'Temporary per payment', value: 'per_payment' }, { title: 'Reusable per payer', value: 'per_payer' }]
const form = reactive({ enabled: false, provider: 'paystack', default_mode: 'per_payment', allow_modes: ['per_payment', 'per_payer'], exact_amount_only: true, refunds_allowed: true, per_payment: { expiry_minutes: 480 }, per_payer: { intent_expiry_hours: 24 }, bulk_pin: { intent_expiry_hours: 72 } })
const load = async () => { try { Object.assign(form, (await paymentCollectionSettingsAPI.getConfig()).data.data) } catch (e) { errorMessage.value = e.response?.data?.message || 'Unable to load payment collection settings.' } }
const save = async () => { saving.value = true; try { Object.assign(form, (await paymentCollectionSettingsAPI.updateConfig(form)).data.data); success('Payment collection settings saved') } catch (e) { error(e.response?.data?.message || 'Unable to save payment collection settings.') } finally { saving.value = false } }
onMounted(load)
</script>
