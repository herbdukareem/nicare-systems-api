<template>
  <v-app>
    <v-main>
      <router-view v-slot="{ Component, route }">
        <transition name="page" mode="out-in" @after-enter="uiStore.setRouteLoading(false)">
          <div :key="route.fullPath" class="route-view-shell">
            <component :is="Component" />
          </div>
        </transition>
      </router-view>
    </v-main>

    <transition name="fade">
      <AppLoadingScreen
        v-if="showGlobalLoader"
        :title="loaderTitle"
        :subtitle="loaderSubtitle"
      />
    </transition>

    <ToastContainer />
  </v-app>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from './stores/auth';
import { useUiStore } from './stores/ui';
import AppLoadingScreen from './components/common/AppLoadingScreen.vue';
import ToastContainer from './components/layout/ToastContainer.vue';

const authStore = useAuthStore();
const uiStore = useUiStore();

const showGlobalLoader = computed(() => authStore._initializing || uiStore.routeLoading || uiStore.requestLoading);
const loaderTitle = computed(() => {
  if (authStore._initializing) return 'Checking access';
  if (uiStore.routeLoading) return 'Loading page';
  return uiStore.requestTitle;
});
const loaderSubtitle = computed(() => {
  if (authStore._initializing) return 'Restoring your secure session';
  if (uiStore.routeLoading) return 'Preparing your workspace';
  return uiStore.requestSubtitle;
});
</script>

<style>
.v-application {
  font-family: 'Inter', sans-serif;
}

.route-view-shell {
  min-height: 100%;
}

::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.page-enter-active,
.page-leave-active,
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.16s ease;
}

.page-enter-from,
.page-leave-to,
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
