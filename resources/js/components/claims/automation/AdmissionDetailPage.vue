<template>
  <div class="admission-detail-page">
    <v-container>
      <v-row>
        <v-col cols="12">
          <v-btn @click="$router.back()" class="mb-4">
            <v-icon left>mdi-arrow-left</v-icon>
            Back
          </v-btn>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" md="8">
          <!-- Admission Details -->
          <v-card class="mb-4">
            <v-card-title class="bg-primary text-white">
              <v-icon left>mdi-hospital-box</v-icon>
              Admission Details
            </v-card-title>
            <v-card-text>
              <v-simple-table>
                <template v-slot:default>
                  <tbody>
                    <tr>
                      <td><strong>Admission Number:</strong></td>
                      <td>{{ admission?.admission_number }}</td>
                    </tr>
                    <tr>
                      <td><strong>Patient Name:</strong></td>
                      <td>{{ admission?.patient_name }}</td>
                    </tr>
                    <tr>
                      <td><strong>Admission Date:</strong></td>
                      <td>{{ admission?.admission_date }}</td>
                    </tr>
                    <tr>
                      <td><strong>Status:</strong></td>
                      <td>
                        <v-chip
                          :color="getStatusColor(admission?.status)"
                          text-color="white"
                        >
                          {{ admission?.status }}
                        </v-chip>
                      </td>
                    </tr>
                    <tr v-if="admission?.discharge_date">
                      <td><strong>Discharge Date:</strong></td>
                      <td>{{ admission?.discharge_date }}</td>
                    </tr>
                  </tbody>
                </template>
              </v-simple-table>
            </v-card-text>
          </v-card>

          <!-- Linked Claims -->
          <v-card class="mb-4">
            <v-card-title>Linked Claims</v-card-title>
            <v-card-text>
              <v-data-table
                :headers="claimHeaders"
                :items="admission?.claims || []"
                :loading="loading"
              >
                <template v-slot:item.status="{ item }">
                  <v-chip
                    :color="getClaimStatusColor(item.status)"
                    text-color="white"
                    small
                  >
                    {{ item.status }}
                  </v-chip>
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon
                    small
                    color="primary"
                    @click="viewClaim(item)"
                  >
                    <v-icon>mdi-eye</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="4">
          <!-- Summary Card -->
          <v-card class="mb-4">
            <v-card-title>Summary</v-card-title>
            <v-card-text>
              <v-list>
                <v-list-item>
                  <v-list-item-title>Total Claims</v-list-item-title>
                  <v-list-item-subtitle>{{ admission?.claims?.length || 0 }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Approved Claims</v-list-item-title>
                  <v-list-item-subtitle>{{ approvedClaimsCount }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Total Amount Claimed</v-list-item-title>
                  <v-list-item-subtitle>{{ totalAmountClaimed }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Total Amount Approved</v-list-item-title>
                  <v-list-item-subtitle>{{ totalAmountApproved }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>

          <!-- Actions -->
          <v-card>
            <v-card-title>Actions</v-card-title>
            <v-card-text>
              <v-btn
                block
                color="primary"
                class="mb-2"
                @click="createClaim"
              >
                <v-icon left>mdi-plus</v-icon>
                Create Claim
              </v-btn>
              <v-btn
                block
                color="warning"
                v-if="admission?.status === 'ACTIVE'"
                @click="dischargeAdmission"
              >
                <v-icon left>mdi-logout</v-icon>
                Discharge Patient
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from '@/js/composables/useToast';
import { useClaimsAPI } from '@/js/composables/useClaimsAPI';
import api from '@/js/utils/api';

const route = useRoute();
const router = useRouter();
const { success: showSuccess, error: showError } = useToast();
const { loading } = useClaimsAPI();

const admission = ref(null);

const claimHeaders = [
  { title: 'Claim Number', value: 'claim_number' },
  { title: 'Amount', value: 'total_amount_claimed' },
  { title: 'Status', value: 'status' },
  { title: 'Actions', value: 'actions' },
];

const approvedClaimsCount = computed(() => {
  return admission.value?.claims?.filter(c => c.status === 'APPROVED').length || 0;
});

const totalAmountClaimed = computed(() => {
  return admission.value?.claims?.reduce((sum, c) => sum + (c.total_amount_claimed || 0), 0) || 0;
});

const totalAmountApproved = computed(() => {
  return admission.value?.claims?.reduce((sum, c) => sum + (c.total_amount_approved || 0), 0) || 0;
});

onMounted(async () => {
  try {
    const response = await api.get(`/api/admissions/${route.params.id}`);
    admission.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load admission details');
  }
});

const getStatusColor = (status) => {
  const colors = {
    'ACTIVE': 'green',
    'DISCHARGED': 'gray',
    'PENDING': 'orange',
  };
  return colors[status] || 'blue';
};

const getClaimStatusColor = (status) => {
  const colors = {
    'DRAFT': 'blue',
    'SUBMITTED': 'orange',
    'APPROVED': 'green',
    'REJECTED': 'red',
  };
  return colors[status] || 'gray';
};

const viewClaim = (claim) => {
  router.push(`/claims/review?claim_id=${claim.id}`);
};

const createClaim = () => {
  router.push(`/claims/submissions?admission_id=${admission.value.id}`);
};

const dischargeAdmission = async () => {
  if (!confirm('Are you sure you want to discharge this patient?')) return;

  try {
    await api.put(`/api/admissions/${admission.value.id}`, {
      status: 'DISCHARGED',
      discharge_date: new Date().toISOString().split('T')[0],
    });
    showSuccess('Patient discharged successfully');
    admission.value.status = 'DISCHARGED';
  } catch (err) {
    showError('Failed to discharge patient');
  }
};
</script>

<style scoped>
.admission-detail-page {
  padding: 20px 0;
}
</style>

