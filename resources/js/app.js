import './bootstrap';
import { createApp } from 'vue';
import { createVuetify } from 'vuetify';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import UtilsPlugin from './utils/utils';
import { vuetifyThemeColors } from './design-system/tokens';

// PrimeVue
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';

// Vuetify styles (load first)
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

// Import Vuetify components
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

// Tailwind CSS (load after Vuetify)
import '../css/app.css';

// ⬇️ NEW: auth stores for pre-mount rehydration
import { useAuthStore } from './stores/auth';
import { useEnrolleeAuthStore } from './stores/enrolleeAuth';

// Create Vuetify instance
const vuetify = createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'customTheme',
    themes: {
      customTheme: {
        dark: false,
        colors: {
          ...vuetifyThemeColors,
          accent: '#60a5fa',
        },
      },
    },
  },
  defaults: {
    global: { ripple: false },
    VBtn: { style: 'text-transform: none;' },
  },
});

// Create Pinia store
const pinia = createPinia();

// Create app
const app = createApp(App);
app.use(vuetify);
app.use(pinia);
app.use(router);
app.use(UtilsPlugin);
app.use(PrimeVue);
app.use(ToastService);

// 🔐 Restore auth BEFORE mounting to avoid logout-on-refresh flicker
const bootLoader = document.createElement('div');
bootLoader.id = 'app-boot-loader';
bootLoader.innerHTML = `
  <div style="position:fixed;inset:0;z-index:9999;display:grid;place-items:center;background:#f4f7fb;font-family:Inter,Segoe UI,Arial,sans-serif">
    <div style="display:flex;flex-direction:column;align-items:center;gap:14px">
      <div style="height:92px;width:92px;border-radius:999px;border:4px solid #d9eef1;display:grid;place-items:center;position:relative;background:#fff;box-shadow:0 16px 40px -28px rgba(15,23,42,.32)">
        <div style="position:absolute;inset:-4px;border-radius:999px;border:4px solid transparent;border-top-color:#0b6b79;animation:nicare-spin .8s linear infinite"></div>
        <img src="/logo.png" alt="NGSCHA Logo" style="height:60px;width:60px;object-fit:contain" />
      </div>
      <div style="text-align:center">
        <div style="font-size:14px;font-weight:700;color:#0f172a">Loading NiCare</div>
        <div style="font-size:12px;color:#64748b">Preparing your workspace</div>
      </div>
    </div>
  </div>
`;
const bootStyle = document.createElement('style');
bootStyle.textContent = '@keyframes nicare-spin{to{transform:rotate(360deg)}}';
document.head.appendChild(bootStyle);
document.body.appendChild(bootLoader);

const authStore = useAuthStore();
const enrolleeAuthStore = useEnrolleeAuthStore();

Promise.all([
  authStore.initializeAuth(),
  enrolleeAuthStore.initializeAuth(),
]).finally(() => {
  bootLoader.remove();
  app.mount('#app');
});

// Central listener for unauthorized events fired by api.js (admin/system users)
window.addEventListener('auth:unauthorized', () => {
  if (authStore._initializing) return;
  authStore.clearSession();
  if (router.currentRoute.value.name !== 'login') {
    router.replace('/login');
  }
});

// Central listener for unauthorized enrollee events
window.addEventListener('enrollee:unauthorized', () => {
  if (enrolleeAuthStore._initializing) return;
  enrolleeAuthStore._clearSession();
  const route = router.currentRoute.value;
  if (route.meta?.requiresEnrolleeAuth) {
    router.replace('/enroll/login');
  }
});
