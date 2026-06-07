<template>
  <div class="enroll-login">
    <!-- Left panel -->
    <div class="enroll-login__left">
      <div class="enroll-login__left-inner">
        <div class="enroll-login__brand">
          <img :src="'/logo.png'" alt="NiCare" class="enroll-login__logo" @error="logoErr = true" v-if="!logoErr" />
          <div v-else class="enroll-login__logo-fb"><v-icon color="white" size="22">mdi-hospital-box</v-icon></div>
          <div>
            <div class="enroll-login__brand-name">NiCare</div>
            <div class="enroll-login__brand-sub">Health Insurance System</div>
          </div>
        </div>

        <div class="enroll-login__left-content">
          <h2 class="enroll-login__left-title">Enrollee Portal</h2>
          <p class="enroll-login__left-sub">
            Sign in to view your coverage, manage your profile, and access your NiCare ID card.
          </p>
          <ul class="enroll-login__benefits">
            <li v-for="b in benefits" :key="b">
              <v-icon size="15" color="rgba(255,255,255,0.85)">mdi-check</v-icon>
              {{ b }}
            </li>
          </ul>
        </div>

        <div class="enroll-login__back-link" @click="router.push('/')">
          <v-icon size="16">mdi-arrow-left</v-icon> Back to Home
        </div>
      </div>
    </div>

    <!-- Right panel -->
    <div class="enroll-login__right">
      <div class="enroll-login__form-wrap">
        <!-- Mobile header -->
        <div class="enroll-login__mobile-header">
          <img :src="'/logo.png'" alt="NiCare" class="enroll-login__logo-sm" v-if="!logoErr" />
          <div>
            <div class="enroll-login__brand-name-sm">NiCare</div>
            <div class="enroll-login__brand-sub-sm">Enrollee Portal</div>
          </div>
        </div>

        <div class="enroll-login__heading">
          <h1>Enrollee Sign In</h1>
          <p>Sign in with your Enrollee ID and portal password.</p>
        </div>

        <AppAlert v-if="errorMsg" tone="danger" :message="errorMsg" class="tw-mb-4" />

        <form @submit.prevent="handleLogin" class="enroll-login__form">
          <div>
            <label class="enroll-login__label">Enrollee ID</label>
            <v-text-field
              v-model="form.enrollee_id"
              placeholder="e.g. NGSCHA000001234"
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-card-account-details-outline"
              :error-messages="fieldErrors.enrollee_id"
            />
          </div>

          <div>
            <label class="enroll-login__label">Password</label>
            <v-text-field
              v-model="form.password"
              :type="showPass ? 'text' : 'password'"
              placeholder="Enter your portal password"
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-lock-outline"
              :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showPass = !showPass"
              :error-messages="fieldErrors.password"
              @keyup.enter="handleLogin"
            />
          </div>

          <v-btn
            type="submit"
            color="primary"
            size="large"
            block
            variant="flat"
            :loading="loading"
          >
            <v-icon start size="18">mdi-login</v-icon>
            Sign In to Portal
          </v-btn>
        </form>

        <div class="enroll-login__divider"><span>Not an enrollee?</span></div>

        <v-btn variant="outlined" color="primary" block @click="router.push('/')">
          <v-icon start size="18">mdi-home-outline</v-icon>
          Back to Landing Page
        </v-btn>

        <p class="enroll-login__staff-link">
          Are you staff or admin?
          <a @click.prevent="router.push('/login')" href="#">Staff Sign In →</a>
        </p>

        <p class="enroll-login__copyright">
          &copy; {{ new Date().getFullYear() }} Niger State Contributory Health Agency
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import { useToast } from '../../composables/useToast';
import AppAlert from '../common/AppAlert.vue';

const router = useRouter();
const enrolleeAuth = useEnrolleeAuthStore();
const { success, error } = useToast();

const logoErr = ref(false);
const loading = ref(false);
const showPass = ref(false);
const errorMsg = ref('');

const form = reactive({ enrollee_id: '', password: '' });
const fieldErrors = reactive({ enrollee_id: [], password: [] });

const benefits = [
  'View coverage & enrollment status',
  'Access your NiCare ID card',
  'Renew your premium plan',
  'Update your profile details',
];

const handleLogin = async () => {
  fieldErrors.enrollee_id = [];
  fieldErrors.password = [];
  errorMsg.value = '';

  if (!form.enrollee_id) { fieldErrors.enrollee_id = ['Enrollee ID is required']; return; }
  if (!form.password)    { fieldErrors.password = ['Password is required']; return; }

  loading.value = true;
  try {
    await enrolleeAuth.login({ enrollee_id: form.enrollee_id, password: form.password });
    success('Welcome back! Redirecting to your portal…');
    router.push('/enroll/dashboard');
  } catch (err) {
      const msg = err.response?.data?.message || 'Invalid enrollee ID or password.';
    if (err.response?.status === 422) {
      const errs = err.response.data.errors || {};
      if (errs.enrollee_id) fieldErrors.enrollee_id = errs.enrollee_id;
      if (errs.password)    fieldErrors.password = errs.password;
    } else {
      errorMsg.value = msg;
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.enroll-login {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  font-family: var(--qds-font-sans);
}
@media (max-width: 900px) {
  .enroll-login { grid-template-columns: 1fr; }
  .enroll-login__left { display: none; }
}

/* LEFT */
.enroll-login__left {
  background: #0b1f33;
  display: flex;
  flex-direction: column;
  padding: 40px;
}
.enroll-login__left-inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  max-width: 460px;
}
.enroll-login__brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: auto;
}
.enroll-login__logo {
  height: 40px;
  width: 40px;
  object-fit: contain;
  background: rgba(255, 255, 255, 0.12);
}
.enroll-login__logo-fb {
  height: 40px;
  width: 40px;
  background: rgba(255, 255, 255, 0.12);
  display: grid;
  place-items: center;
}
.enroll-login__brand-name {
  font-size: 18px;
  font-weight: 800;
  color: white;
  line-height: 1.2;
}
.enroll-login__brand-sub {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.65);
}
.enroll-login__left-content {
  margin: auto 0;
}
.enroll-login__left-title {
  font-size: 26px;
  font-weight: 800;
  color: white;
  margin-bottom: 12px;
}
.enroll-login__left-sub {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.6;
  margin-bottom: 22px;
}
.enroll-login__benefits {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.enroll-login__benefits li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.8);
}
.enroll-login__back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: rgba(255, 255, 255, 0.7);
  font-size: 13px;
  cursor: pointer;
  margin-top: auto;
  transition: color 0.15s ease;
}
.enroll-login__back-link:hover { color: white; }

/* RIGHT */
.enroll-login__right {
  background: var(--qds-color-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
}
.enroll-login__form-wrap {
  width: 100%;
  max-width: 400px;
}
.enroll-login__mobile-header {
  display: none;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}
@media (max-width: 900px) {
  .enroll-login__mobile-header { display: flex; }
}
.enroll-login__logo-sm {
  height: 36px;
  width: 36px;
  object-fit: contain;
  background: var(--qds-color-surface-muted);
  border: 1px solid var(--qds-color-border);
}
.enroll-login__brand-name-sm {
  font-size: 14px;
  font-weight: 700;
  color: var(--qds-color-text);
  line-height: 1.2;
}
.enroll-login__brand-sub-sm {
  font-size: 11px;
  color: var(--qds-color-text-muted);
}

.enroll-login__heading { margin-bottom: 24px; }
.enroll-login__heading h1 {
  font-size: 22px;
  font-weight: 800;
  color: var(--qds-color-text);
  margin-bottom: 6px;
}
.enroll-login__heading p {
  font-size: 13px;
  color: var(--qds-color-text-secondary);
  line-height: 1.5;
}
.enroll-login__form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 20px;
}
.enroll-login__label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--qds-color-text-secondary);
  margin-bottom: 6px;
}
.enroll-login__divider {
  text-align: center;
  position: relative;
  margin: 16px 0;
  color: var(--qds-color-text-muted);
  font-size: 12px;
}
.enroll-login__divider::before,
.enroll-login__divider::after {
  content: '';
  position: absolute;
  top: 50%;
  width: 36%;
  height: 1px;
  background: var(--qds-color-border);
}
.enroll-login__divider::before { left: 0; }
.enroll-login__divider::after  { right: 0; }
.enroll-login__staff-link {
  text-align: center;
  font-size: 13px;
  color: var(--qds-color-text-secondary);
  margin: 16px 0 0;
}
.enroll-login__staff-link a {
  font-weight: 600;
  color: var(--qds-color-primary);
  text-decoration: none;
}
.enroll-login__staff-link a:hover { text-decoration: underline; }
.enroll-login__copyright {
  text-align: center;
  font-size: 11px;
  color: var(--qds-color-text-muted);
  margin-top: 24px;
}
</style>
