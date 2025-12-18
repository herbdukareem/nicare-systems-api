<template>
  <AdminLayout>
    <v-container fluid>
      <v-row>
        <v-col cols="12" md="8">
          <v-card>
            <v-card-title>
              <v-icon class="mr-2">mdi-message-plus</v-icon>
              Create Referral Feedback
            </v-card-title>

            <v-card-text>
              <v-form ref="formRef" @submit.prevent="submitFeedback">
                <!-- Referral Selection -->
                <v-autocomplete
                  v-model="form.referral_id"
                  :items="referrals"
                  :loading="loadingReferrals"
                  item-title="display_text"
                  item-value="id"
                  label="Select Approved Referral"
                  :rules="[v => !!v || 'Referral is required']"
                  @update:model-value="onReferralSelected"
                  variant="outlined"
                >
                  <template #item="{ props, item }">
                    <v-list-item v-bind="props">
                      <v-list-item-subtitle>
                        UTN: {{ item.raw.utn }} | {{ item.raw.enrollee?.full_name }}
                      </v-list-item-subtitle>
                    </v-list-item>
                  </template>
                </v-autocomplete>

                <!-- Selected Referral Details -->
                <v-card v-if="selectedReferral" class="mb-4" variant="outlined">
                  <v-card-text>
                    <v-row>
                      <v-col cols="6" md="3">
                        <strong>UTN:</strong><br>{{ selectedReferral.utn }}
                      </v-col>
                      <v-col cols="6" md="3">
                        <strong>Enrollee:</strong><br>{{ selectedReferral.enrollee?.full_name }}
                      </v-col>
                      <v-col cols="6" md="3">
                        <strong>Current Status:</strong><br>
                        <v-chip :color="getStatusColor(selectedReferral.status)" size="small">
                          {{ selectedReferral.status }}
                        </v-chip>
                      </v-col>
                      <v-col cols="6" md="3">
                        <strong>Facility:</strong><br>{{ selectedReferral.receiving_facility?.name }}
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>

                <!-- Feedback Type -->
                <v-select
                  v-model="form.feedback_type"
                  :items="feedbackTypes"
                  label="Feedback Type"
                  :rules="[v => !!v || 'Feedback type is required']"
                     variant="outlined"
                />

                <!-- Priority -->
                <v-select
                  v-model="form.priority"
                  :items="priorityOptions"
                  label="Priority"
                     variant="outlined"
                />

                <!-- Feedback Comments -->
                <v-textarea
                  v-model="form.feedback_comments"
                  label="Feedback Comments"
                     variant="outlined"
                  rows="4"
                  :rules="[v => !!v || 'Comments are required']"
                />

                <!-- Officer Observations -->
                <v-textarea
                  v-model="form.officer_observations"
                  label="Officer Observations (Optional)"
                     variant="outlined"
                  rows="3"
                />

                <!-- Change Referral Status -->
                <v-checkbox
                  v-model="changeStatus"
                  label="Change Referral Status"
                />

                <v-select
                  v-if="changeStatus"
                  v-model="form.new_referral_status"
                  :items="referralStatusOptions"
                  label="New Referral Status"
                     variant="outlined"
                />

                <v-btn
                  type="submit"
                  color="primary"
                  :loading="submitting"
                  class="mt-4"
                >
                  <v-icon left>mdi-check</v-icon>
                  Submit Feedback
                </v-btn>
              </v-form>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Recent Feedbacks -->
        <v-col cols="12" md="4">
          <v-card>
            <v-card-title>Recent Feedbacks</v-card-title>
            <v-card-text>
              <v-list v-if="recentFeedbacks.length > 0" density="compact">
                <v-list-item
                  v-for="fb in recentFeedbacks"
                  :key="fb.id"
                  :subtitle="fb.feedback_comments?.substring(0, 50) + '...'"
                >
                  <template #title>
                    {{ fb.feedback_code }} - {{ fb.feedback_type }}
                  </template>
                  <template #append>
                    <v-chip size="x-small" :color="fb.is_system_generated ? 'grey' : 'primary'">
                      {{ fb.is_system_generated ? 'Auto' : 'Manual' }}
                    </v-chip>
                  </template>
                </v-list-item>
              </v-list>
              <p v-else class="text-grey">No recent feedbacks</p>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import { feedbackAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { success, error } = useToast();
const route = useRoute();

const formRef = ref(null);
const loadingReferrals = ref(false);
const submitting = ref(false);
const referrals = ref([]);
const selectedReferral = ref(null);
const recentFeedbacks = ref([]);
const changeStatus = ref(false);

const form = ref({
  referral_id: null,
  feedback_type: 'referral',
  priority: 'medium',
  feedback_comments: '',
  officer_observations: '',
  new_referral_status: null,
});

const feedbackTypes = [
  { title: 'Referral Follow-up', value: 'referral' },
  { title: 'PA Code Issue', value: 'pa_code' },
  { title: 'General Inquiry', value: 'general' },
  { title: 'Enrollee Verification', value: 'enrollee_verification' },
  { title: 'Service Delivery', value: 'service_delivery' },
  { title: 'Claims Guidance', value: 'claims_guidance' },
  { title: 'Medical History Review', value: 'medical_history' },
  { title: 'Complaint Resolution', value: 'complaint' },
  { title: 'UTN Validation Issue', value: 'utn_validation' },
  { title: 'Facility Coordination', value: 'facility_coordination' },
  { title: 'Document Verification', value: 'document_verification' },
  { title: 'Treatment Progress', value: 'treatment_progress' },
];

const priorityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
  { title: 'Urgent', value: 'urgent' },
];

const referralStatusOptions = [
  { title: 'Pending', value: 'PENDING' },
  { title: 'Approved', value: 'APPROVED' },
  { title: 'In Progress', value: 'IN_PROGRESS' },
  { title: 'Completed', value: 'COMPLETED' },
  { title: 'Cancelled', value: 'CANCELLED' },
];

const loadReferrals = async () => {
  loadingReferrals.value = true;
  try {
    const response = await feedbackAPI.getApprovedReferrals();
    referrals.value = (response.data.data || response.data || []).map(r => ({
      ...r,
      display_text: `${r.utn} - ${r.enrollee?.full_name || 'Unknown'}`,
    }));
  } catch (err) {
    console.error('Failed to load referrals:', err);
  } finally {
    loadingReferrals.value = false;
  }
};

const loadRecentFeedbacks = async () => {
  try {
    const response = await feedbackAPI.getMyFeedbacks({ per_page: 5 });
    recentFeedbacks.value = response.data.data || response.data || [];
  } catch (err) {
    console.error('Failed to load recent feedbacks:', err);
  }
};

const onReferralSelected = (referralId) => {
  selectedReferral.value = referrals.value.find(r => r.id === referralId) || null;
};

const getStatusColor = (status) => {
  const colors = {
    'PENDING': 'orange',
    'APPROVED': 'green',
    'IN_PROGRESS': 'blue',
    'COMPLETED': 'success',
    'CANCELLED': 'red',
    'REJECTED': 'error',
  };
  return colors[status] || 'grey';
};

const submitFeedback = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  if (!selectedReferral.value) {
    error('Please select a referral');
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      enrollee_id: selectedReferral.value.enrollee_id,
      referral_id: form.value.referral_id,
      feedback_type: form.value.feedback_type,
      priority: form.value.priority,
      feedback_comments: form.value.feedback_comments,
      officer_observations: form.value.officer_observations,
    };

    if (changeStatus.value && form.value.new_referral_status) {
      payload.new_referral_status = form.value.new_referral_status;
    }

    const response = await feedbackAPI.create(payload);

    if (response.data.success) {
      success('Feedback submitted successfully');

      // Reset form
      form.value = {
        referral_id: null,
        feedback_type: 'referral',
        priority: 'medium',
        feedback_comments: '',
        officer_observations: '',
        new_referral_status: null,
      };
      selectedReferral.value = null;
      changeStatus.value = false;

      await loadRecentFeedbacks();
    } else {
      error(response.data.message || 'Failed to submit feedback');
    }
  } catch (err) {
    console.error('Failed to submit feedback:', err);
    const errorMessage = err.response?.data?.message || err.response?.data?.error || 'Failed to submit feedback. Please try again.';
    error(errorMessage);
  } finally {
    submitting.value = false;
  }
};

onMounted(async () => {
  await loadReferrals();
  loadRecentFeedbacks();

  // Check if there's a pre-selected referral from query params
  const referralId = route.query.referral_id;
  if (referralId) {
    const referralIdNum = parseInt(referralId);
    form.value.referral_id = referralIdNum;
    onReferralSelected(referralIdNum);
  }
});
</script>

<style scoped>
</style>

