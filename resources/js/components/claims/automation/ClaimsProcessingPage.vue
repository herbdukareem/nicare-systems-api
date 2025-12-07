<template>
  <div class="claims-processing-page">
    <v-container>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="bg-primary text-white">
              <v-icon left>mdi-file-document-edit</v-icon>
              Claims Processing - Bundle & FFS Classification
            </v-card-title>

            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4">
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search by claim number"
                    outlined
                    dense
                    prepend-icon="mdi-magnify"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-select
                    v-model="statusFilter"
                    label="Filter by Status"
                    :items="statusOptions"
                    outlined
                    dense
                    clearable
                  />
                </v-col>
              </v-row>

              <!-- Claims Table -->
              <v-data-table
                :headers="headers"
                :items="filteredClaims"
                :loading="loading"
                class="elevation-1"
              >
                <template v-slot:item.status="{ item }">
                  <v-chip
                    :color="getStatusColor(item.status)"
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
                    @click="processClaim(item)"
                  >
                    <v-icon>mdi-play</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Processing Dialog -->
      <v-dialog v-model="showProcessingDialog" max-width="800px">
        <v-card>
          <v-card-title>
            Process Claim: {{ currentClaim?.claim_number }}
          </v-card-title>
          <v-card-text>
            <v-stepper v-model="processingStep" alt-labels>
              <v-stepper-header>
                <v-stepper-item step="1">Bundle Classification</v-stepper-item>
                <v-divider></v-divider>
                <v-stepper-item step="2">FFS Top-ups</v-stepper-item>
                <v-divider></v-divider>
                <v-stepper-item step="3">Validation</v-stepper-item>
              </v-stepper-header>

              <v-stepper-items>
                <!-- Step 1: Bundle Classification -->
                <v-stepper-content step="1">
                  <v-select
                    v-model="processingData.bundle_id"
                    label="Select Bundle"
                    :items="bundles"
                    item-title="name"
                    item-value="id"
                    outlined
                  />
                  <v-text-field
                    v-model.number="processingData.bundle_amount"
                    label="Bundle Amount"
                    type="number"
                    outlined
                  />
                  <v-btn color="primary" @click="processingStep = 2">
                    Next
                    <v-icon right>mdi-arrow-right</v-icon>
                  </v-btn>
                </v-stepper-content>

                <!-- Step 2: FFS Top-ups -->
                <v-stepper-content step="2">
                  <v-card class="mb-4">
                    <v-card-title>FFS Top-up Services</v-card-title>
                    <v-card-text>
                      <v-data-table
                        :headers="ffsHeaders"
                        :items="processingData.ffs_items"
                        class="mb-4"
                      >
                        <template v-slot:item.actions="{ item, index }">
                          <v-btn
                            icon
                            small
                            color="error"
                            @click="removeFfsItem(index)"
                          >
                            <v-icon>mdi-delete</v-icon>
                          </v-btn>
                        </template>
                      </v-data-table>
                      <v-btn color="success" @click="addFfsItem">
                        <v-icon left>mdi-plus</v-icon>
                        Add FFS Item
                      </v-btn>
                    </v-card-text>
                  </v-card>

                  <v-row>
                    <v-col cols="12" class="d-flex gap-2">
                      <v-btn color="secondary" @click="processingStep = 1">
                        <v-icon left>mdi-arrow-left</v-icon>
                        Back
                      </v-btn>
                      <v-btn color="primary" @click="processingStep = 3">
                        Next
                        <v-icon right>mdi-arrow-right</v-icon>
                      </v-btn>
                    </v-col>
                  </v-row>
                </v-stepper-content>

                <!-- Step 3: Validation -->
                <v-stepper-content step="3">
                  <v-card class="mb-4">
                    <v-card-title>Claim Summary</v-card-title>
                    <v-card-text>
                      <v-simple-table>
                        <template v-slot:default>
                          <tbody>
                            <tr>
                              <td><strong>Bundle Amount:</strong></td>
                              <td>{{ processingData.bundle_amount }}</td>
                            </tr>
                            <tr>
                              <td><strong>FFS Amount:</strong></td>
                              <td>{{ calculateFfsTotal() }}</td>
                            </tr>
                            <tr>
                              <td><strong>Total:</strong></td>
                              <td>{{ processingData.bundle_amount + calculateFfsTotal() }}</td>
                            </tr>
                          </tbody>
                        </template>
                      </v-simple-table>
                    </v-card-text>
                  </v-card>

                  <v-row>
                    <v-col cols="12" class="d-flex gap-2">
                      <v-btn color="secondary" @click="processingStep = 2">
                        <v-icon left>mdi-arrow-left</v-icon>
                        Back
                      </v-btn>
                      <v-btn
                        color="primary"
                        :loading="loading"
                        @click="submitProcessing"
                      >
                        <v-icon left>mdi-check</v-icon>
                        Process Claim
                      </v-btn>
                    </v-col>
                  </v-row>
                </v-stepper-content>
              </v-stepper-items>
            </v-stepper>
          </v-card-text>
        </v-card>
      </v-dialog>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '@/js/composables/useToast';
import { useClaimsAPI } from '@/js/composables/useClaimsAPI';
import { useClaimsStore } from '@/js/stores/claimsStore';
import api from '@/js/utils/api';

const { success: showSuccess, error: showError } = useToast();
const { fetchClaims, loading } = useClaimsAPI();
const claimsStore = useClaimsStore();

const searchQuery = ref('');
const statusFilter = ref(null);
const showProcessingDialog = ref(false);
const processingStep = ref(1);
const currentClaim = ref(null);
const bundles = ref([]);

const statusOptions = [
  { title: 'Draft', value: 'DRAFT' },
  { title: 'Submitted', value: 'SUBMITTED' },
  { title: 'Reviewing', value: 'REVIEWING' },
];

const headers = [
  { title: 'Claim Number', value: 'claim_number' },
  { title: 'Amount', value: 'total_amount_claimed' },
  { title: 'Status', value: 'status' },
  { title: 'Actions', value: 'actions' },
];

const ffsHeaders = [
  { title: 'Service Code', value: 'service_code' },
  { title: 'Description', value: 'description' },
  { title: 'Amount', value: 'amount' },
  { title: 'Actions', value: 'actions' },
];

const processingData = ref({
  bundle_id: null,
  bundle_amount: 0,
  ffs_items: [],
});

const filteredClaims = computed(() => {
  return claimsStore.claims.filter(claim => {
    const matchesSearch = !searchQuery.value || 
      claim.claim_number?.toLowerCase().includes(searchQuery.value.toLowerCase());
    
    const matchesStatus = !statusFilter.value || claim.status === statusFilter.value;
    
    return matchesSearch && matchesStatus;
  });
});

onMounted(async () => {
  try {
    await fetchClaims();
    const response = await api.get('/api/bundles');
    bundles.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load data');
  }
});

const getStatusColor = (status) => {
  const colors = {
    'DRAFT': 'blue',
    'SUBMITTED': 'orange',
    'REVIEWING': 'purple',
    'APPROVED': 'green',
    'REJECTED': 'red',
  };
  return colors[status] || 'gray';
};

const processClaim = (claim) => {
  currentClaim.value = claim;
  processingData.value = {
    bundle_id: null,
    bundle_amount: 0,
    ffs_items: [],
  };
  processingStep.value = 1;
  showProcessingDialog.value = true;
};

const addFfsItem = () => {
  processingData.value.ffs_items.push({
    service_code: '',
    description: '',
    amount: 0,
  });
};

const removeFfsItem = (index) => {
  processingData.value.ffs_items.splice(index, 1);
};

const calculateFfsTotal = () => {
  return processingData.value.ffs_items.reduce((sum, item) => sum + (item.amount || 0), 0);
};

const submitProcessing = async () => {
  try {
    await api.post(`/api/claims/${currentClaim.value.id}/process`, processingData.value);
    showSuccess('Claim processed successfully');
    showProcessingDialog.value = false;
    await fetchClaims();
  } catch (err) {
    showError(err.message || 'Failed to process claim');
  }
};
</script>

<style scoped>
.claims-processing-page {
  padding: 20px 0;
}

.gap-2 {
  gap: 8px;
}
</style>

