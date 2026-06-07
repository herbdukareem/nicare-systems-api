<template>
  <div class="staff-login">
    <!-- Left panel -->
    <div class="staff-login__left">
      <div class="staff-login__left-inner">
        <div class="staff-login__brand">
          <div class="staff-login__brand-mark"><v-icon color="white" size="22">mdi-hospital-box</v-icon></div>
          <div>
            <div class="staff-login__brand-name">NGSCHA</div>
            <div class="staff-login__brand-sub">Niger State Contributory Health Agency</div>
          </div>
        </div>

        <div class="staff-login__figure">
          <img
            v-if="!showFallbackImage"
            :src="ceremonyImg"
            alt="NGSCHA"
            @error="showFallbackImage = true"
          />
          <div v-else class="staff-login__figure-fb">
            <v-icon size="56" color="white">mdi-hospital-building</v-icon>
          </div>
        </div>

        <div class="staff-login__copy">
          <h1>Staff &amp; Administration Portal</h1>
          <p>Sign in with your official credentials to manage enrollment, claims, facilities, and scheme operations.</p>
        </div>

        <div class="staff-login__back-link" @click="router.push('/')">
          <v-icon size="16">mdi-arrow-left</v-icon> Back to public website
        </div>
      </div>
    </div>

    <!-- Right panel -->
    <div class="staff-login__right">
      <div class="staff-login__form-wrap">
        <div class="staff-login__mobile-header">
          <div class="staff-login__brand-mark"><v-icon color="white" size="20">mdi-hospital-box</v-icon></div>
          <div>
            <div class="staff-login__brand-name-sm">NGSCHA Admin</div>
          </div>
        </div>

        <div class="staff-login__heading">
          <h2>Staff Sign In</h2>
          <p>Enter your username and password to access the dashboard.</p>
        </div>

        <form @submit.prevent="handleLogin" class="staff-login__form">
          <div>
            <label for="username" class="staff-login__label">Username</label>
            <v-text-field
              id="username"
              v-model="form.username"
              type="text"
              placeholder="Enter your username"
              required
              variant="outlined"
              :error-messages="errors.username"
              density="compact"
              prepend-inner-icon="mdi-account-outline"
            />
          </div>

          <div>
            <label for="password" class="staff-login__label">Password</label>
            <v-text-field
              id="password"
              v-model="form.password"
              type="password"
              placeholder="Enter your password"
              required
              variant="outlined"
              :error-messages="errors.password"
              density="compact"
              prepend-inner-icon="mdi-lock-outline"
              @keyup.enter="handleLogin"
            />
          </div>

          <div class="staff-login__row">
            <v-checkbox
              v-model="form.remember"
              label="Remember me"
              density="compact"
              hide-details
            />
            <button type="button" class="staff-login__link" @click="showForgotPassword = true">
              Forgot password?
            </button>
          </div>

          <v-btn
            type="submit"
            :loading="loading"
            :disabled="loading"
            color="primary"
            size="large"
            block
            variant="flat"
          >
            <template v-if="loading">
              <v-progress-circular indeterminate size="18" width="2" class="tw-mr-2" color="white" />
              Signing in&hellip;
            </template>
            <template v-else>
              <v-icon start size="18">mdi-login</v-icon>
              Sign In
            </template>
          </v-btn>
        </form>

        <p class="staff-login__copyright">
          &copy; {{ new Date().getFullYear() }} Niger State Contributory Health Agency. All rights reserved.
        </p>
      </div>
    </div>

    <!-- Forgot Password Dialog -->
    <AppModal v-model="showForgotPassword" title="Reset Password" icon="mdi-lock-reset" size="sm">
      <div>
        <p class="tw-mb-4 tw-text-gray-600">
          Enter your username. An administrator will be notified to assist with your password reset.
        </p>
        <v-text-field
          v-model="forgotUsername"
          type="text"
          label="Username"
          variant="outlined"
          density="compact"
          prepend-inner-icon="mdi-account-outline"
          :error-messages="forgotErrors.username"
        />
      </div>
      <template #actions>
        <v-btn variant="outlined" @click="showForgotPassword = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="forgotLoading" @click="handleForgotPassword">Send Reset Link</v-btn>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToast } from '../../composables/useToast';
import AppModal from '../common/AppModal.vue';
import ceremonyImg from '/resources/assets/ngscha-ceremony.png';

const router = useRouter();
const authStore = useAuthStore();
const { success, error } = useToast();

// Form data
const form = reactive({
  username: '',
  password: '',
  remember: false,
});

const errors = reactive({
  username: [],
  password: [],
});

const loading = ref(false);
const showForgotPassword = ref(false);
const showFallbackImage = ref(false);
const forgotUsername = ref('');
const forgotLoading = ref(false);
const forgotErrors = reactive({
  username: [],
});

const handleLogin = async () => {
  // Clear previous errors
  errors.username = [];
  errors.password = [];

  if (!form.username || !form.password) {
    if (!form.username) errors.username = ['Username is required'];
    if (!form.password) errors.password = ['Password is required'];
    return;
  }

  loading.value = true;

  try {
    const loginResponse = await authStore.login({
      username: form.username,
      password: form.password,
    });

    success('Login successful! Welcome back.');

    // Determine redirect based on user role
    const userRoles = loginResponse.data.roles || [];
    const isDeskOfficer = userRoles.includes('desk_officer');

    // Redirect to appropriate dashboard
    const redirectPath = isDeskOfficer ? '/do-dashboard' : '/dashboard';
    router.push(redirectPath);
  } catch (err) {
    const response = err.response;
    if (response?.status === 422) {
      // Validation errors
      const validationErrors = response.data.errors;
      if (validationErrors.username) errors.username = validationErrors.username;
      if (validationErrors.password) errors.password = validationErrors.password;
    } else if (response?.status === 401) {
      error(response.data.message || 'Invalid credentials');
    } else {
      error('Login failed. Please try again.');
    }
  } finally {
    loading.value = false;
  }
};

const handleForgotPassword = async () => {
  // Clear previous errors
  forgotErrors.username = [];

  if (!forgotUsername.value) {
    forgotErrors.username = ['Username is required'];
    return;
  }

  forgotLoading.value = true;

  try {
    await authStore.forgotPassword(forgotUsername.value);
    success('Password reset instructions sent. Please contact your administrator.');
    showForgotPassword.value = false;
    forgotUsername.value = '';
  } catch (err) {
    const response = err.response;
    if (response?.status === 422) {
      const validationErrors = response.data.errors;
      if (validationErrors.username) forgotErrors.username = validationErrors.username;
    } else {
      error('Failed to process reset request. Please try again.');
    }
  } finally {
    forgotLoading.value = false;
  }
};
</script>

<style scoped>
.staff-login {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: var(--qds-color-bg);
  font-family: var(--qds-font-sans);
}
@media (max-width: 960px) {
  .staff-login { grid-template-columns: 1fr; }
  .staff-login__left { display: none; }
}

/* LEFT */
.staff-login__left {
  background: #0b1f33;
  display: flex;
  flex-direction: column;
  padding: 40px;
}
.staff-login__left-inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  max-width: 460px;
}
.staff-login__brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
}
.staff-login__brand-mark {
  height: 40px;
  width: 40px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  background: var(--qds-color-primary);
}
.staff-login__brand-name {
  font-size: 18px;
  font-weight: 800;
  color: white;
  line-height: 1.2;
}
.staff-login__brand-sub {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.65);
}

.staff-login__figure {
  border: 1px solid rgba(255, 255, 255, 0.18);
  margin-bottom: 24px;
  overflow: hidden;
}
.staff-login__figure img {
  display: block;
  width: 100%;
  height: 220px;
  object-fit: cover;
}
.staff-login__figure-fb {
  height: 220px;
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, 0.06);
}

.staff-login__copy {
  margin-bottom: auto;
}
.staff-login__copy h1 {
  font-size: 24px;
  font-weight: 800;
  color: white;
  margin-bottom: 10px;
  line-height: 1.3;
}
.staff-login__copy p {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.6;
}

.staff-login__back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: rgba(255, 255, 255, 0.7);
  font-size: 13px;
  cursor: pointer;
  margin-top: 24px;
  transition: color 0.15s ease;
}
.staff-login__back-link:hover { color: white; }

/* RIGHT */
.staff-login__right {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
}
.staff-login__form-wrap {
  width: 100%;
  max-width: 400px;
}
.staff-login__mobile-header {
  display: none;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}
@media (max-width: 960px) {
  .staff-login__mobile-header { display: flex; }
}
.staff-login__brand-name-sm {
  font-size: 14px;
  font-weight: 700;
  color: var(--qds-color-text);
}

.staff-login__heading {
  margin-bottom: 24px;
}
.staff-login__heading h2 {
  font-size: 22px;
  font-weight: 800;
  color: var(--qds-color-text);
  margin-bottom: 6px;
}
.staff-login__heading p {
  font-size: 13px;
  color: var(--qds-color-text-secondary);
}

.staff-login__form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.staff-login__label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--qds-color-text-secondary);
  margin-bottom: 6px;
}
.staff-login__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: -6px;
}
.staff-login__link {
  font-size: 13px;
  font-weight: 600;
  color: var(--qds-color-primary);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}
.staff-login__link:hover { text-decoration: underline; }

.staff-login__copyright {
  text-align: center;
  font-size: 11px;
  color: var(--qds-color-text-muted);
  margin-top: 28px;
}
</style>
