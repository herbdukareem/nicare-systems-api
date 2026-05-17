<template>
  <EnrolleeLayout>
    <div class="tw-max-w-xl tw-mx-auto">
      <div class="tw-mb-6">
        <h1 class="tw-text-2xl tw-font-bold tw-text-slate-900">Change Password</h1>
        <p class="tw-text-sm tw-text-slate-500">
          Set a secure custom password for your enrollee account
        </p>
      </div>

      <v-alert type="info" variant="tonal" class="tw-mb-5" rounded="lg" v-if="!enrolleeAuth.hasCustomPassword">
        <strong>Default password active.</strong> Your current password is your NIN. We recommend setting a custom password for better security.
      </v-alert>

      <v-alert type="success" variant="tonal" class="tw-mb-5" v-if="successMsg" rounded="lg">
        {{ successMsg }}
      </v-alert>
      <v-alert type="error" variant="tonal" class="tw-mb-5" v-if="errorMsg" rounded="lg">
        {{ errorMsg }}
      </v-alert>

      <div class="ep-change-pwd-card">
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
      </div>

      <!-- Tips -->
      <div class="ep-tips tw-mt-6">
        <div class="ep-tips__title">
          <v-icon size="16" color="primary">mdi-lightbulb</v-icon>
          Password Tips
        </div>
        <ul class="ep-tips__list">
          <li>Use at least 6 characters</li>
          <li>Mix letters, numbers, and symbols</li>
          <li>Avoid using your NIN or enrollee ID as your new password</li>
          <li>Do not share your password with anyone</li>
        </ul>
      </div>
    </div>
  </EnrolleeLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import { enrolleePortalAPI } from '../../utils/enrolleeApi';
import { useToast } from '../../composables/useToast';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';

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
.ep-change-pwd-card {
  background: white;
  border-radius: 20px;
  padding: 32px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.ep-strength-bar {
  height: 4px;
  flex: 1;
  border-radius: 999px;
  transition: background 0.3s;
}
.ep-tips {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 16px 20px;
}
.ep-tips__title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: #1d4ed8;
  margin-bottom: 8px;
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
