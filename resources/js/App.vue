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
        :title="authStore._initializing ? 'Checking access' : 'Loading page'"
        :subtitle="authStore._initializing ? 'Restoring your secure session' : 'Preparing your workspace'"
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

const showGlobalLoader = computed(() => authStore._initializing || uiStore.routeLoading);
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
