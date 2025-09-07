import './bootstrap';
import { createApp } from 'vue';
import { createVuetify } from 'vuetify';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';

// Vuetify styles (load first)
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

// Import Vuetify components
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

// Tailwind CSS (load after Vuetify)
import '../css/app.css';

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
    global: {
      ripple: false,
    },
    VBtn: {
      style: 'text-transform: none;',
    },
  },
});

// Create Pinia store
const pinia = createPinia();

// Create and mount Vue app
const app = createApp(App);
app.use(vuetify);
app.use(pinia);
app.use(router);
app.mount('#app');
