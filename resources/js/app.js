import './bootstrap';
import { createApp } from 'vue';
import { createVuetify } from 'vuetify';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import UtilsPlugin from './utils/utils';

// Vuetify styles (load first)
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

// Import Vuetify components
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

// Tailwind CSS (load after Vuetify)
import '../css/app.css';

// ⬇️ NEW: auth store for pre-mount rehydration
import { useAuthStore } from './stores/auth';

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
          primary: '#2563eb',
          secondary: '#64748b',
          accent: '#82B1FF',
          error: '#FF5252',
          info: '#2196F3',
          success: '#4CAF50',
          warning: '#FFC107',
          background: '#f8fafc',
          surface: '#ffffff',
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

// 🔐 Restore auth BEFORE mounting to avoid logout-on-refresh flicker
const authStore = useAuthStore();
authStore.initializeAuth().finally(() => {
  app.mount('#app');
});

// (Optional) Central listener for unauthorized events fired by api.js
window.addEventListener('auth:unauthorized', async () => {
  // Prevent double work if we're already initializing
  if (!authStore._initializing) {
    await authStore.logout();
    router.replace('/login');
  }
});