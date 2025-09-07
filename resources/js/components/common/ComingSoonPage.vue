<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-animate-fade-in-up">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
          <div>
            <h1 class="tw-text-3xl tw-font-bold tw-bg-gradient-to-r tw-from-blue-600 tw-to-purple-600 tw-bg-clip-text tw-text-transparent">
              {{ title }}
            </h1>
            <p class="tw-text-gray-600 tw-mt-1">{{ subtitle }}</p>
          </div>
        </div>
      </div>

      <!-- Coming Soon Message -->
      <div class="tw-bg-white tw-rounded-2xl tw-shadow-lg tw-p-8 tw-text-center">
        <div class="tw-mb-6">
          <v-icon size="64" color="primary" class="tw-mb-4">{{ icon }}</v-icon>
          <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-mb-2">{{ title }}</h2>
          <p class="tw-text-gray-600">This module is under development and will be available soon.</p>
        </div>
        
        <div class="tw-bg-blue-50 tw-rounded-lg tw-p-6 tw-mt-8">
          <v-icon size="48" color="blue" class="tw-mb-4">mdi-tools</v-icon>
          <h3 class="tw-text-lg tw-font-semibold tw-text-blue-800 tw-mb-2">Under Development</h3>
          <p class="tw-text-blue-600">
            Our development team is working hard to bring you this feature. 
            Stay tuned for updates!
          </p>
        </div>

        <div class="tw-mt-8">
          <v-btn 
            color="primary" 
            variant="outlined"
            prepend-icon="mdi-arrow-left"
            @click="goBack"
            class="tw-mr-4"
          >
            Go Back
          </v-btn>
          <v-btn 
            color="primary"
            prepend-icon="mdi-home"
            @click="goHome"
          >
            Dashboard
          </v-btn>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';

const props = defineProps({
  title: {
    type: String,
    default: 'Coming Soon'
  },
  subtitle: {
    type: String,
    default: 'This feature is under development'
  },
  icon: {
    type: String,
    default: 'mdi-wrench'
  }
});

const router = useRouter();

const goBack = () => {
  router.go(-1);
};

const goHome = () => {
  router.push('/dashboard');
};
</script>

<style scoped>
/* Custom animations */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.tw-animate-fade-in-up {
  animation: fadeInUp 0.6s ease-out;
}
</style>
