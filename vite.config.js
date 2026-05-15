import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
    vue(),
  ],
  resolve: {
    alias: {
      vue: "vue/dist/vue.esm-bundler.js",
      '@': path.resolve(__dirname, "resources"),
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          vue: ['vue', 'vue-router', 'pinia'],
          vuetify: ['vuetify'],
          charts: ['chart.js', 'vue-chartjs'],
          primevue: ['primevue/config', 'primevue/toastservice'],
          lodash: ['lodash', 'lodash-es'],
        },
      },
    },
    minify: 'terser',
  },
  optimizeDeps: {
    esbuildOptions: {
      define: {
        global: 'globalThis',
      },
      supported: {
        bigint: false,
      },
    },
  },
})
