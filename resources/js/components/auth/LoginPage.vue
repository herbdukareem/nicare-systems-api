<template>
  <div class="tw-min-h-screen tw-flex tw-items-center tw-justify-center tw-bg-gradient-to-br tw-from-blue-50 tw-to-indigo-100">
    <div class="tw-max-w-md tw-w-full tw-space-y-8 tw-p-8">
      <!-- Logo and Header -->
      <div class="tw-text-center">
        <div class="tw-mx-auto tw-h-16 tw-w-16 tw-bg-blue-600 tw-rounded-full tw-flex tw-items-center tw-justify-center tw-overflow-hidden">
          <img
            src="/resources/images/logo.png"
            alt="NGSCHA Logo"
            class="tw-w-12 tw-h-12 tw-object-contain"
            @error="showFallbackIcon = true"
            v-if="!showFallbackIcon"
          />
          <v-icon v-else size="32" color="white">mdi-shield-account</v-icon>
        </div>
        <h2 class="tw-mt-6 tw-text-3xl tw-font-extrabold tw-text-gray-900">
          NGSCHA Admin
        </h2>
        <p class="tw-mt-2 tw-text-sm tw-text-gray-600">
          Sign in to access the admin dashboard
        </p>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleLogin" class="tw-mt-8 tw-space-y-6">
        <div class="tw-rounded-md tw-shadow-sm tw-space-y-4">
          <div>
            <label for="username" class="tw-sr-only">Username</label>
            <v-text-field
              id="username"
              v-model="form.username"
              type="text"
              label="Username"
              required
              variant="outlined"
              :error-messages="errors.username"
              density="comfortable"
              prepend-inner-icon="mdi-account"
            />
          </div>
          <div>
            <label for="password" class="tw-sr-only">Password</label>
            <v-text-field
              id="password"
              v-model="form.password"
              type="password"
              label="Password"
              required
              variant="outlined"
              :error-messages="errors.password"
              density="comfortable"
              prepend-inner-icon="mdi-lock"
              @keyup.enter="handleLogin"
            />
          </div>
        </div>

        <!-- Remember me and Forgot Password -->
        <div class="tw-flex tw-items-center tw-justify-between">
          <div class="tw-flex tw-items-center">
            <v-checkbox
              v-model="form.remember"
              label="Remember me"
              density="compact"
              color="primary"
            />
          </div>
          <div class="tw-text-sm">
            <button
              type="button"
              @click="showForgotPassword = true"
              class="tw-font-medium tw-text-blue-600 hover:tw-text-blue-500"
            >
              Forgot your password?
            </button>
          </div>
        </div>

        <!-- Login Button -->
        <div>
          <v-btn
            type="submit"
            :loading="loading"
            :disabled="loading"
            color="primary"
            size="large"
            block
            class="tw-font-medium"
          >
            <v-icon left>mdi-login</v-icon>
            Sign in
          </v-btn>
        </div>
      </form>

      <!-- Forgot Password Dialog -->
      <v-dialog v-model="showForgotPassword" max-width="500">
        <v-card>
          <v-card-title class="tw-text-xl tw-font-semibold">
            Reset Password
          </v-card-title>
          <v-card-text>
            <p class="tw-mb-4 tw-text-gray-600">
              Enter your email address and we'll send you a link to reset your password.
            </p>
            <v-text-field
              v-model="forgotEmail"
              type="email"
              label="Email address"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-email"
              :error-messages="forgotErrors.email"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn
              variant="text"
              @click="showForgotPassword = false"
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              :loading="forgotLoading"
              @click="handleForgotPassword"
            >
              Send Reset Link
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToast } from '../../composables/useToast';

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
const showFallbackIcon = ref(false);
const forgotEmail = ref('');
const forgotLoading = ref(false);
const forgotErrors = reactive({
  email: [],
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
    await authStore.login({
      username: form.username,
      password: form.password,
    });

    success('Login successful! Welcome back.');
    router.push('/dashboard');
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
  forgotErrors.email = [];

  if (!forgotEmail.value) {
    forgotErrors.email = ['Email is required'];
    return;
  }

  forgotLoading.value = true;

  try {
    await authStore.forgotPassword(forgotEmail.value);
    success('Password reset link sent to your email.');
    showForgotPassword.value = false;
    forgotEmail.value = '';
  } catch (err) {
    const response = err.response;
    if (response?.status === 422) {
      const validationErrors = response.data.errors;
      if (validationErrors.email) forgotErrors.email = validationErrors.email;
    } else {
      error('Failed to send reset link. Please try again.');
    }
  } finally {
    forgotLoading.value = false;
  }
};
</script>

<style scoped>
/* Additional custom styles if needed */
</style>