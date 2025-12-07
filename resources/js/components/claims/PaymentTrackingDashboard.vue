<template>
  <div class="payment-tracking-dashboard">
    <v-container>
      <!-- Summary Cards -->
      <v-row class="mb-4">
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6">Total Claims</div>
              <div class="text-h4 text-primary">{{ totalClaims }}</div>
              <div class="text-caption text-grey">All submitted claims</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6">Approved Amount</div>
              <div class="text-h4 text-success">₦{{ formatCurrency(totalApproved) }}</div>
              <div class="text-caption text-grey">Total approved</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6">Paid Amount</div>
              <div class="text-h4 text-info">₦{{ formatCurrency(totalPaid) }}</div>
              <div class="text-caption text-grey">Total paid</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6">Pending Payment</div>
              <div class="text-h4 text-warning">₦{{ formatCurrency(totalPending) }}</div>
              <div class="text-caption text-grey">Awaiting payment</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Payment Status Chart -->
      <v-row class="mb-4">
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Payment Status Distribution</v-card-title>
            <v-card-text>
              <canvas ref="statusChart"></canvas>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Monthly Payment Trend</v-card-title>
            <v-card-text>
              <canvas ref="trendChart"></canvas>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Facility Payment Summary -->
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title>Facility Payment Summary</v-card-title>
            <v-card-text>
              <v-data-table
                :headers="facilityHeaders"
                :items="facilityPayments"
                :loading="loading"
                class="elevation-1"
              >
                <template v-slot:item.payment_status="{ item }">
                  <v-chip
                    :color="getPaymentStatusColor(item.payment_status)"
                    text-color="white"
                    small
                  >
                    {{ item.payment_status }}
                  </v-chip>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useClaimsAPI } from '../../composables/useClaimsAPI';
import { useClaimsStore } from '../../stores/claimsStore';
import api from '../../utils/api';

const { loading } = useClaimsAPI();
const claimsStore = useClaimsStore();

const statusChart = ref(null);
const trendChart = ref(null);
const facilityPayments = ref([]);

const facilityHeaders = [
  { title: 'Facility Name', value: 'facility_name' },
  { title: 'Total Claims', value: 'total_claims' },
  { title: 'Total Amount', value: 'total_amount' },
  { title: 'Paid Amount', value: 'paid_amount' },
  { title: 'Pending Amount', value: 'pending_amount' },
  { title: 'Payment Status', value: 'payment_status' },
];

const totalClaims = computed(() => claimsStore.claims.length);

const totalApproved = computed(() => {
  return claimsStore.approvedClaims.reduce((sum, c) => sum + (c.total_amount_approved || 0), 0);
});

const totalPaid = computed(() => {
  return claimsStore.claims
    .filter(c => c.payment_status === 'PAID')
    .reduce((sum, c) => sum + (c.total_amount_approved || 0), 0);
});

const totalPending = computed(() => {
  return claimsStore.claims
    .filter(c => c.payment_status === 'PENDING')
    .reduce((sum, c) => sum + (c.total_amount_approved || 0), 0);
});

onMounted(async () => {
  try {
    const response = await api.get('/api/payments/facility-summary');
    facilityPayments.value = response.data.data || response.data;
    initializeCharts();
  } catch (err) {
    console.error('Failed to load payment data:', err);
  }
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-NG', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const getPaymentStatusColor = (status) => {
  const colors = {
    'PAID': 'green',
    'PENDING': 'orange',
    'FAILED': 'red',
    'PROCESSING': 'blue',
  };
  return colors[status] || 'gray';
};

const initializeCharts = () => {
  // Status chart would be initialized here with Chart.js
  // This is a placeholder for the actual implementation
};
</script>

<style scoped>
.payment-tracking-dashboard {
  padding: 20px 0;
}
</style>

