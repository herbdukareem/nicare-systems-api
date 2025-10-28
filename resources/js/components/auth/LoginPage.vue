<template>
  <div class="tw-min-h-screen tw-bg-white tw-grid tw-grid-cols-1 lg:tw-grid-cols-2">
    <!-- Left Side - Image/Branding -->
    <div class="tw-hidden lg:tw-flex tw-flex-col tw-justify-center tw-items-center tw-bg-gradient-to-br tw-from-blue-50 tw-to-blue-100 tw-p-12">
      <div class="tw-max-w-lg tw-text-center">
        <!-- Logo -->
        <div class="tw-mb-8">
          <Logo
            size="2xl"
            variant="circle"
            icon-color="white"
            class="bg-primary tw-mx-auto tw-shadow-xl"
          />
        </div>

        <!-- Hero Image -->
        <div class="tw-mb-8 tw-rounded-2xl tw-overflow-hidden tw-shadow-2xl">
          <img
            :src="ceremonyImg"
            alt="NGSCHA Ceremony"
            class="tw-w-full tw-h-80 tw-object-cover"
            @error="showFallbackImage = true"
            v-if="!showFallbackImage"
          />
          <div
            v-else
            class="tw-w-full tw-h-80 bg-primary-200 tw-flex tw-items-center tw-justify-center tw-text-blue-600"
          >
            <div class="tw-text-center">
              <v-icon size="64" class="tw-mb-4">mdi-hospital-building</v-icon>
              <p class="tw-text-lg tw-font-semibold">NGSCHA Healthcare</p>
            </div>
          </div>
        </div>

        <!-- Branding Text -->
        <h1 class="tw-text-4xl tw-font-bold tw-text-gray-800 tw-mb-4">
          Niger State Contributory Health Agency
        </h1>
        <p class="tw-text-xl tw-text-gray-600 tw-mb-6">
          Comprehensive Healthcare Management System
        </p>
        <p class="tw-text-gray-500">
          A reliable scheme that ensures access to affordable quality
healthcare to all the people of Niger state
        </p>
      </div>
    </div>

    <!-- Right Side - Login Form -->
    <div class="tw-flex tw-flex-col tw-justify-center tw-px-8 sm:tw-px-12 lg:tw-px-16 tw-py-12">
      <div class="tw-max-w-md tw-mx-auto tw-w-full">
        <!-- Mobile Logo (visible only on small screens) -->
        <div class="lg:tw-hidden tw-text-center tw-mb-8">
          <Logo
            size="xl"
            variant="circle"
            icon-color="white"
            class="bg-primary tw-mx-auto tw-shadow-lg tw-mb-4"
          />
          <h2 class="tw-text-2xl tw-font-bold tw-text-gray-800">NGSCHA Admin</h2>
        </div>

        <!-- Login Header -->
        <div class="tw-mb-8">
          <h2 class="tw-text-3xl tw-font-bold tw-text-gray-900 tw-mb-2">
            Welcome Back
          </h2>
          <p class="tw-text-gray-600">
            Sign in to access the admin dashboard
          </p>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" class="tw-space-y-6">
          <!-- Username Field -->
          <div>
            <label for="username" class="tw-block tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-2">
              Username
            </label>
            <v-text-field
              id="username"
              v-model="form.username"
              type="text"
              placeholder="Enter your username"
              required
              variant="outlined"
              :error-messages="errors.username"
              density="comfortable"
              prepend-inner-icon="mdi-account"
              color="blue"
              class="tw-w-full"
            />
          </div>

          <!-- Password Field -->
          <div>
            <label for="password" class="tw-block tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-2">
              Password
            </label>
            <v-text-field
              id="password"
              v-model="form.password"
              type="password"
              placeholder="Enter your password"
              required
              variant="outlined"
              :error-messages="errors.password"
              density="comfortable"
              prepend-inner-icon="mdi-lock"
              color="blue"
              class="tw-w-full"
              @keyup.enter="handleLogin"
            />
          </div>

          <!-- Remember me and Forgot Password -->
          <div class="tw-flex tw-items-center tw-justify-between tw-mt-6">
            <div class="tw-flex tw-items-center">
              <v-checkbox
                v-model="form.remember"
                label="Remember me"
                density="compact"
                color="blue"
                class="tw-text-gray-600"
              />
            </div>
            <div class="tw-text-sm">
              <button
                type="button"
                @click="showForgotPassword = true"
                class="tw-font-medium tw-text-blue-600 hover:tw-text-blue-700 tw-transition-colors"
              >
                Forgot password?
              </button>
            </div>
          </div>

          <!-- Login Button -->
          <div class="tw-mt-8">
            <v-btn
              type="submit"
              :loading="loading"
              :disabled="loading"
              color="blue"
              size="large"
              block
              class="tw-py-4 tw-text-lg tw-font-semibold tw-rounded-xl tw-shadow-lg"
              elevation="0"
            >
              <template v-if="loading">
                <v-progress-circular
                  indeterminate
                  size="20"
                  class="tw-mr-2"
                  color="white"
                />
                Signing in...
              </template>
              <template v-else>
                <v-icon class="tw-mr-2">mdi-login</v-icon>
                Sign In
              </template>
            </v-btn>
          </div>

          <!-- Additional Info -->
          <div class="tw-mt-8 tw-text-center tw-text-sm tw-text-gray-500">
            <p>© 2025 Niger State Contributory Health Agency</p>
            <p class="tw-mt-1">A reliable scheme that ensures access to affordable quality
healthcare to all the people of Niger state</p>
          </div>
        </form>
      </div>
    </div>

    <!-- Forgot Password Dialog -->
    <v-dialog v-model="showForgotPassword" max-width="500">
      <v-card class="tw-rounded-xl">
        <v-card-title class="tw-text-xl tw-font-semibold bg-primary-50 tw-text-blue-800">
          <v-icon class="tw-mr-2">mdi-lock-reset</v-icon>
          Reset Password
        </v-card-title>
        <v-card-text class="tw-pt-6">
          <p class="tw-mb-4 tw-text-gray-600">
            Enter your username and we'll help you reset your password.
          </p>
          <v-text-field
            v-model="forgotUsername"
            type="text"
            label="Username"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-account"
            :error-messages="forgotErrors.username"
            color="blue"
          />
        </v-card-text>
        <v-card-actions class="tw-px-6 tw-pb-6">
          <v-spacer />
          <v-btn
            variant="text"
            @click="showForgotPassword = false"
            class="tw-text-gray-600"
          >
            Cancel
          </v-btn>
          <v-btn
            color="blue"
            :loading="forgotLoading"
            @click="handleForgotPassword"
            class="tw-px-6"
          >
            Send Reset Link
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToast } from '../../composables/useToast';
import Logo from '../common/Logo.vue';
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
/* Grid layout styles */
.tw-grid {
  min-height: 100vh;
}

/* Custom blue theme styles */
:deep(.v-field--variant-outlined .v-field__outline) {
  --v-field-border-color: #3b82f6;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-color: #2563eb;
  --v-field-border-width: 2px;
}

:deep(.v-input--error .v-field__outline) {
  --v-field-border-color: #ef4444;
}

:deep(.v-btn--variant-elevated) {
  box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1), 0 2px 4px -1px rgba(59, 130, 246, 0.06);
}

:deep(.v-btn--variant-elevated:hover) {
  box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.1), 0 4px 6px -2px rgba(59, 130, 246, 0.05);
  transform: translateY(-1px);
}

/* Form animations */
form {
  animation: slideInRight 0.8s ease-out;
}

.tw-from-blue-50 {
  animation: slideInLeft 0.8s ease-out;
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* Image hover effect */
img {
  transition: transform 0.3s ease;
}

img:hover {
  transform: scale(1.02);
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  .tw-grid {
    grid-template-columns: 1fr;
  }
}
</style>