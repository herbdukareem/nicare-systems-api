<template>
  <v-app>
    <v-main>
      <router-view v-slot="{ Component, route }">
        <transition
          name="page"
          mode="out-in"
          @enter="onEnter"
          @leave="onLeave"
        >
          <component :is="Component" :key="route.path" />
        </transition>
      </router-view>
    </v-main>
    <!-- Global Toast Notifications -->
    <ToastContainer />
  </v-app>
</template>

<script setup>
import ToastContainer from './components/layout/ToastContainer.vue';

// Route transition handlers
const onEnter = (el) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';

  setTimeout(() => {
    el.style.transition = 'all 0.3s ease-out';
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';
  }, 10);
};

const onLeave = (el) => {
  el.style.transition = 'all 0.2s ease-in';
  el.style.opacity = '0';
  el.style.transform = 'translateY(-10px)';
};

// Note: Auth initialization is handled in app.js before mounting
// to prevent logout-on-refresh flicker
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

/* Page transition styles */
.page-enter-active,
.page-leave-active {
  transition: all 0.3s ease;
}

.page-enter-from {
  opacity: 0;
  transform: translateY(20px);
}

.page-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.page-enter-to,
.page-leave-from {
  opacity: 1;
  transform: translateY(0);
}
</style>