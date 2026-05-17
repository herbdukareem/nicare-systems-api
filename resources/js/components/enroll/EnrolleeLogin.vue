<template>
  <div class="enroll-login">
    <!-- Left panel -->
    <div class="enroll-login__left">
      <div class="enroll-login__left-inner">
        <div class="enroll-login__brand">
          <img :src="'/logo.png'" alt="NiCare" class="enroll-login__logo" @error="logoErr = true" v-if="!logoErr" />
          <div v-else class="enroll-login__logo-fb"><v-icon color="white" size="32">mdi-hospital-box</v-icon></div>
          <div>
            <div class="enroll-login__brand-name">NiCare</div>
            <div class="enroll-login__brand-sub">Health Insurance System</div>
          </div>
        </div>

        <div class="enroll-login__left-content">
          <div class="enroll-login__shield">
            <v-icon size="80" color="rgba(255,255,255,0.9)">mdi-shield-account</v-icon>
          </div>
          <h2 class="enroll-login__left-title">Enrollee Portal</h2>
          <p class="enroll-login__left-sub">
            Access your health insurance account, view your coverage status, manage your profile, and renew your premium plan.
          </p>
          <div class="enroll-login__benefits">
            <div v-for="b in benefits" :key="b" class="enroll-login__benefit">
              <v-icon size="16" color="rgba(255,255,255,0.9)">mdi-check-circle</v-icon>
              {{ b }}
            </div>
          </div>
        </div>

        <div class="enroll-login__back-link" @click="$router.push('/')">
          <v-icon size="16">mdi-arrow-left</v-icon> Back to Home
        </div>
      </div>
    </div>

    <!-- Right panel -->
    <div class="enroll-login__right">
      <div class="enroll-login__form-wrap">
        <!-- Mobile header -->
        <div class="enroll-login__mobile-header">
          <img :src="'/logo.png'" alt="NiCare" class="tw-h-10 tw-w-10 tw-object-contain tw-rounded-lg" v-if="!logoErr" />
          <div>
            <div class="tw-font-bold tw-text-slate-800">NiCare</div>
            <div class="tw-text-xs tw-text-slate-500">Enrollee Portal</div>
          </div>
        </div>

        <div class="enroll-login__heading">
          <h1>Welcome Back</h1>
          <p>Sign in with your Enrollee ID and NIN (or password if you've set one)</p>
        </div>

        <v-alert v-if="errorMsg" type="error" variant="tonal" class="tw-mb-5" closable @click:close="errorMsg = ''">
          {{ errorMsg }}
        </v-alert>

        <form @submit.prevent="handleLogin" class="enroll-login__form">
          <div>
            <label class="enroll-login__label">Enrollee ID</label>
            <v-text-field
              v-model="form.enrollee_id"
              placeholder="e.g. NGSCHA000001234"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-card-account-details"
              color="primary"
              :error-messages="fieldErrors.enrollee_id"
            />
          </div>

          <div>
            <label class="enroll-login__label">NIN / Password</label>
            <v-text-field
              v-model="form.password"
              :type="showPass ? 'text' : 'password'"
              placeholder="Enter your NIN or custom password"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-lock"
              :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showPass = !showPass"
              color="primary"
              :error-messages="fieldErrors.password"
              @keyup.enter="handleLogin"
            />
            <p class="enroll-login__hint">
              Default password is your NIN. Use a custom password if you've changed it.
            </p>
          </div>

          <v-btn
            type="submit"
            color="primary"
            size="large"
            block
            rounded
            :loading="loading"
            class="tw-mt-2"
          >
            <v-icon start>mdi-login</v-icon>
            Sign In to Portal
          </v-btn>
        </form>

        <div class="enroll-login__divider">
          <span>Not an enrollee?</span>
        </div>
        <v-btn
          variant="outlined"
          color="primary"
          block
          rounded
          @click="$router.push('/')"
          class="tw-mb-4"
        >
          <v-icon start>mdi-home</v-icon>
          Back to Landing Page
        </v-btn>

        <p class="enroll-login__staff-link">
          Are you staff or admin?
          <a @click.prevent="$router.push('/login')" href="#" class="tw-font-medium tw-text-primary">Staff Login →</a>
        </p>

        <p class="enroll-login__copyright">
          © {{ new Date().getFullYear() }} Niger State Contributory Health Agency
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
  'View your coverage & enrollment status',
  'Access your NiCare ID card',
  'Renew your premium plan',
  'Update your profile details',
  'Track your facility visits',
];

const handleLogin = async () => {
  fieldErrors.enrollee_id = [];
  fieldErrors.password = [];
  errorMsg.value = '';

  if (!form.enrollee_id) { fieldErrors.enrollee_id = ['Enrollee ID is required']; return; }
  if (!form.password)    { fieldErrors.password = ['NIN / Password is required']; return; }

  loading.value = true;
  try {
    await enrolleeAuth.login({ enrollee_id: form.enrollee_id, password: form.password });
    success('Welcome back! Redirecting to your portal…');
    router.push('/enroll/dashboard');
  } catch (err) {
    const msg = err.response?.data?.message || 'Invalid enrollee ID or NIN.';
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
}
@media (max-width: 900px) {
  .enroll-login { grid-template-columns: 1fr; }
  .enroll-login__left { display: none; }
}

/* LEFT */
.enroll-login__left {
  background: linear-gradient(135deg, #0885ab 0%, #0d3b6e 100%);
  display: flex;
  flex-direction: column;
  padding: 40px;
  position: relative;
  overflow: hidden;
}
.enroll-login__left::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 10% 80%, rgba(255,255,255,0.06) 0%, transparent 50%);
}
.enroll-login__left-inner {
  position: relative;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.enroll-login__brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: auto;
}
.enroll-login__logo {
  height: 44px;
  width: 44px;
  object-fit: contain;
  border-radius: 10px;
  background: rgba(255,255,255,.15);
}
.enroll-login__logo-fb {
  height: 44px; width: 44px;
  border-radius: 10px;
  background: rgba(255,255,255,.15);
  display: grid;
  place-items: center;
}
.enroll-login__brand-name {
  font-size: 20px;
  font-weight: 800;
  color: white;
}
.enroll-login__brand-sub {
  font-size: 11px;
  color: rgba(255,255,255,.7);
}
.enroll-login__left-content {
  margin: auto 0;
  text-align: center;
}
.enroll-login__shield {
  margin-bottom: 20px;
  opacity: 0.9;
}
.enroll-login__left-title {
  font-size: 30px;
  font-weight: 800;
  color: white;
  margin-bottom: 14px;
}
.enroll-login__left-sub {
  font-size: 15px;
  color: rgba(255,255,255,.75);
  line-height: 1.6;
  margin-bottom: 28px;
}
.enroll-login__benefits {
  display: flex;
  flex-direction: column;
  gap: 10px;
  text-align: left;
}
.enroll-login__benefit {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: rgba(255,255,255,.85);
}
.enroll-login__back-link {
  display: flex;
  align-items: center;
  gap: 6px;
  color: rgba(255,255,255,.7);
  font-size: 13px;
  cursor: pointer;
  margin-top: auto;
  transition: color 0.2s;
}
.enroll-login__back-link:hover { color: white; }

/* RIGHT */
.enroll-login__right {
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
}
.enroll-login__form-wrap {
  width: 100%;
  max-width: 420px;
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
.enroll-login__heading {
  margin-bottom: 28px;
}
.enroll-login__heading h1 {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 8px;
}
.enroll-login__heading p {
  font-size: 14px;
  color: #64748b;
  line-height: 1.5;
}
.enroll-login__form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.enroll-login__label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}
.enroll-login__hint {
  font-size: 12px;
  color: #94a3b8;
  margin-top: -4px;
}
.enroll-login__divider {
  text-align: center;
  position: relative;
  margin: 20px 0 16px;
  color: #94a3b8;
  font-size: 13px;
}
.enroll-login__divider::before,
.enroll-login__divider::after {
  content: '';
  position: absolute;
  top: 50%;
  width: 38%;
  height: 1px;
  background: #e2e8f0;
}
.enroll-login__divider::before { left: 0; }
.enroll-login__divider::after  { right: 0; }
.enroll-login__staff-link {
  text-align: center;
  font-size: 13px;
  color: #64748b;
  margin: 12px 0;
}
.enroll-login__copyright {
  text-align: center;
  font-size: 12px;
  color: #94a3b8;
  margin-top: 24px;
}
</style>
