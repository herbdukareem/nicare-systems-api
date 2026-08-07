<template>
  <AppCard v-if="collection" title="Your virtual account" icon="mdi-bank-transfer-in" tone="primary">
    <div class="tw-space-y-2 tw-text-sm tw-text-slate-700">
      <p class="tw-font-semibold tw-text-slate-900">Transfer exactly {{ money }} to this {{ collection.mode === 'per_payer' ? 'reusable' : 'temporary' }} account.</p>
      <p><strong>Bank:</strong> {{ collection.bank_name }}</p><p><strong>Account name:</strong> {{ collection.account_name }}</p><p><strong>Account number:</strong> {{ collection.account_number }}</p><p><strong>Reference:</strong> {{ collection.reference || collection.payment_reference }}</p>
      <p v-if="collection.expires_at"><strong>Expires:</strong> {{ new Date(collection.expires_at).toLocaleString() }}</p>
      <AppAlert tone="warning" title="Exact amount required" message="Transfers with a different amount are not settled automatically." />
    </div>
  </AppCard>
</template>
<script setup>
import { computed } from 'vue'
import AppCard from './AppCard.vue'
import AppAlert from './AppAlert.vue'
const props = defineProps({ collection: { type: Object, default: null } })
const money = computed(() => new Intl.NumberFormat('en-NG', { style: 'currency', currency: props.collection?.currency || 'NGN' }).format(Number(props.collection?.amount || 0)))
</script>
