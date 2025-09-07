<template>
  <v-app>
    <v-main>
      <router-view />
    </v-main>
    <!-- Global Toast Notifications -->
    <ToastContainer />
  </v-app>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './stores/auth';
import ToastContainer from './components/layout/ToastContainer.vue';

const authStore = useAuthStore();

onMounted(() => {
  // Initialize authentication state on app load
  authStore.initializeAuth();
});
</script>

<style>
/* Global styles */
.v-application {
  font-family: 'Inter', sans-serif;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>