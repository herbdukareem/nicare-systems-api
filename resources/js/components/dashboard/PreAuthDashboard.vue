<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-animate-fade-in-up">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
          <div>
            <h1 class="tw-text-3xl tw-font-bold tw-bg-gradient-to-r tw-from-purple-600 tw-to-pink-600 tw-bg-clip-text tw-text-transparent">
              Preauthorization Dashboard
            </h1>
            <p class="tw-text-gray-600 tw-mt-1">Monitor preauthorization requests and approvals</p>
          </div>
          <div class="tw-flex tw-space-x-3">
            <v-btn 
              color="primary" 
              variant="outlined" 
              prepend-icon="mdi-download"
              class="tw-transition-all tw-duration-300 hover:tw-scale-105"
            >
              Export Report
            </v-btn>
            <v-btn 
              color="primary" 
              prepend-icon="mdi-plus"
              class="tw-transition-all tw-duration-300 hover:tw-scale-105 tw-shadow-lg"
            >
              New Request
            </v-btn>
          </div>
        </div>
      </div>

      <!-- Coming Soon Message -->
      <div class="tw-bg-white tw-rounded-2xl tw-shadow-lg tw-p-8 tw-text-center">
        <div class="tw-mb-6">
          <v-icon size="64" color="primary" class="tw-mb-4">mdi-shield-check</v-icon>
          <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-mb-2">Preauthorization Dashboard</h2>
          <p class="tw-text-gray-600">This module is under development and will be available soon.</p>
        </div>
        
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4 tw-mt-8">
          <div class="tw-p-4 tw-bg-purple-50 tw-rounded-lg">
            <v-icon size="32" color="purple" class="tw-mb-2">mdi-clipboard-check</v-icon>
            <h3 class="tw-font-semibold tw-text-purple-800">Request Management</h3>
            <p class="tw-text-sm tw-text-purple-600">Manage preauth requests</p>
          </div>
          <div class="tw-p-4 tw-bg-blue-50 tw-rounded-lg">
            <v-icon size="32" color="blue" class="tw-mb-2">mdi-clock-check</v-icon>
            <h3 class="tw-font-semibold tw-text-blue-800">Approval Workflow</h3>
            <p class="tw-text-sm tw-text-blue-600">Track approval process</p>
          </div>
          <div class="tw-p-4 tw-bg-green-50 tw-rounded-lg">
            <v-icon size="32" color="green" class="tw-mb-2">mdi-chart-timeline</v-icon>
            <h3 class="tw-font-semibold tw-text-green-800">Analytics</h3>
            <p class="tw-text-sm tw-text-green-600">Preauth analytics & reports</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '../layout/AdminLayout.vue';
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
