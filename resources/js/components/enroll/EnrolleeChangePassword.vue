<template>
  <EnrolleeLayout>
    <div class="tw-max-w-xl tw-mx-auto">
      <AppPageHeader class="tw-mb-6" title="Change Password" subtitle="Set a secure custom password for your enrollee account" kicker="Enrollee portal" icon="mdi-lock-outline" />

      <AppAlert v-if="!enrolleeAuth.hasCustomPassword" class="tw-mb-5" tone="info" title="Default password active" message="Set a custom password for better account security." />

      <AppAlert v-if="successMsg" class="tw-mb-5" tone="success" title="Password updated" :message="successMsg" />
      <AppAlert v-if="errorMsg" class="tw-mb-5" tone="danger" title="Password could not be updated" :message="errorMsg" />

      <AppCard title="Update portal password" subtitle="Confirm your current password before choosing a new one" icon="mdi-shield-lock-outline">
        <form @submit.prevent="handleSubmit">
          <div class="tw-space-y-5">
            <div>
              <label class="ep-label">Current Password (or NIN)</label>
              <v-text-field
                v-model="form.current_password"
                :type="showCurrent ? 'text' : 'password'"
                placeholder="Enter your current password or NIN"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-lock"
                :append-inner-icon="showCurrent ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showCurrent = !showCurrent"
                color="primary"
                :error-messages="errors.current_password"
              />
            </div>

            <v-divider />

            <div>
              <label class="ep-label">New Password</label>
              <v-text-field
                v-model="form.new_password"
                :type="showNew ? 'text' : 'password'"
                placeholder="At least 6 characters"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-lock-plus"
                :append-inner-icon="showNew ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showNew = !showNew"
                color="primary"
                :error-messages="errors.new_password"
              />

              <!-- Strength indicator -->
              <div class="tw-flex tw-gap-1 tw-mt-2" v-if="form.new_password">
                <div
                  v-for="i in 4"
                  :key="i"
                  class="ep-strength-bar"
                  :class="passwordStrength >= i ? strengthClass : 'tw-bg-slate-200'"
                />
              </div>
              <p class="tw-text-xs tw-text-slate-500 tw-mt-1" v-if="form.new_password">
                Strength: {{ strengthLabel }}
              </p>
            </div>

            <div>
              <label class="ep-label">Confirm New Password</label>
              <v-text-field
                v-model="form.new_password_confirmation"
                :type="showConfirm ? 'text' : 'password'"
                placeholder="Re-enter new password"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-lock-check"
                :append-inner-icon="showConfirm ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showConfirm = !showConfirm"
                color="primary"
                :error-messages="errors.new_password_confirmation"
              />
            </div>
          </div>

          <div class="tw-flex tw-gap-3 tw-mt-6">
            <v-btn
              type="submit"
              color="primary"
              size="large"
              rounded
              :loading="loading"
              class="tw-flex-1"
            >
              <v-icon start>mdi-shield-lock</v-icon>
              Update Password
            </v-btn>
            <v-btn
              variant="outlined"
              color="primary"
              size="large"
              rounded
              @click="reset"
              :disabled="loading"
            >
              Reset
            </v-btn>
          </div>
        </form>
      </AppCard>

      <!-- Tips -->
      <AppCard class="tw-mt-6" title="Password Tips" icon="mdi-lightbulb-outline" tone="info" muted>
        <ul class="ep-tips__list">
          <li>Use at least 6 characters</li>
          <li>Mix letters, numbers, and symbols</li>
          <li>Avoid using your NIN or enrollee ID as your new password</li>
          <li>Do not share your password with anyone</li>
        </ul>
      </AppCard>
    </div>
  </EnrolleeLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import { enrolleePortalAPI } from '../../utils/enrolleeApi';
import { useToast } from '../../composables/useToast';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';
import AppAlert from '../common/AppAlert.vue';
import AppCard from '../common/AppCard.vue';
import AppPageHeader from '../common/AppPageHeader.vue';

const enrolleeAuth = useEnrolleeAuthStore();
const { success } = useToast();

const loading = ref(false);
const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);
const successMsg = ref('');
const errorMsg = ref('');

const form = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const errors = reactive({
  current_password: [],
  new_password: [],
  new_password_confirmation: [],
});

const passwordStrength = computed(() => {
  const p = form.new_password;
  if (!p) return 0;
  let score = 0;
  if (p.length >= 6) score++;
  if (p.length >= 10) score++;
  if (/[0-9]/.test(p) && /[a-zA-Z]/.test(p)) score++;
  if (/[^a-zA-Z0-9]/.test(p)) score++;
  return score;
});
const strengthClass = computed(() => {
  return ['tw-bg-red-400', 'tw-bg-orange-400', 'tw-bg-yellow-400', 'tw-bg-green-500'][passwordStrength.value - 1] || 'tw-bg-red-400';
});
const strengthLabel = computed(() => {
  return ['', 'Weak', 'Fair', 'Good', 'Strong'][passwordStrength.value] || '';
});

const reset = () => {
  form.current_password = '';
  form.new_password = '';
  form.new_password_confirmation = '';
  errors.current_password = [];
  errors.new_password = [];
  errors.new_password_confirmation = [];
  successMsg.value = '';
  errorMsg.value = '';
};

const handleSubmit = async () => {
  errors.current_password = [];
  errors.new_password = [];
  errors.new_password_confirmation = [];
  successMsg.value = '';
  errorMsg.value = '';

  if (!form.current_password) { errors.current_password = ['Current password is required']; return; }
  if (!form.new_password)     { errors.new_password = ['New password is required']; return; }
  if (form.new_password.length < 6) { errors.new_password = ['Password must be at least 6 characters']; return; }
  if (form.new_password !== form.new_password_confirmation) {
    errors.new_password_confirmation = ['Passwords do not match'];
    return;
  }

  loading.value = true;
  try {
    await enrolleePortalAPI.changePassword({
      current_password: form.current_password,
      new_password: form.new_password,
      new_password_confirmation: form.new_password_confirmation,
    });
    enrolleeAuth.hasCustomPassword = true;
    successMsg.value = 'Password changed successfully! Future logins will use your new password.';
    success('Password updated successfully.');
    reset();
    form.current_password = '';
    form.new_password = '';
    form.new_password_confirmation = '';
  } catch (err) {
    const msg = err.response?.data?.message;
    const errs = err.response?.data?.errors || {};
    if (errs.current_password) errors.current_password = errs.current_password;
    else if (errs.new_password) errors.new_password = errs.new_password;
    else errorMsg.value = msg || 'Failed to change password. Please try again.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.ep-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}
.ep-strength-bar {
  height: 4px;
  flex: 1;
  border-radius: 999px;
  transition: background 0.3s;
}
.ep-tips__list {
  font-size: 13px;
  color: #374151;
  padding-left: 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
</style>
