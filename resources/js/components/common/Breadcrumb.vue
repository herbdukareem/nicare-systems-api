<template>
  <nav class="tw-flex tw-items-center tw-space-x-2 tw-text-sm tw-text-gray-600 tw-mb-4" aria-label="Breadcrumb">
    <router-link
      to="/dashboard"
      class="tw-flex tw-items-center tw-text-gray-500 hover:tw-text-gray-700 tw-transition-colors tw-duration-200"
    >
      <v-icon size="16" class="tw-mr-1">mdi-home</v-icon>
      Dashboard
    </router-link>
    
    <template v-for="(item, index) in breadcrumbItems" :key="index">
      <v-icon size="12" class="tw-text-gray-400">mdi-chevron-right</v-icon>
      
      <router-link
        v-if="item.path && index < breadcrumbItems.length - 1"
        :to="item.path"
        class="tw-text-gray-500 hover:tw-text-gray-700 tw-transition-colors tw-duration-200"
      >
        {{ item.name }}
      </router-link>
      
      <span
        v-else
        class="tw-text-gray-900 tw-font-medium"
      >
        {{ item.name }}
      </span>
    </template>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

// Define breadcrumb mappings
const breadcrumbMappings = {
  '/dashboard': [{ name: 'Overview' }],
  '/dashboard/premium': [{ name: 'Premium Dashboard' }],
  '/dashboard/preauth': [{ name: 'Preauthorization Dashboard' }],
  '/enrollees': [{ name: 'Enrollment', path: '/enrollees' }, { name: 'Enrollees List' }],
  '/enrollment/change-facility': [{ name: 'Enrollment' }, { name: 'Change of Facility' }],
  '/enrollment/id-cards': [{ name: 'Enrollment' }, { name: 'ID Card Printing' }],
  '/enrollment/phases': [{ name: 'Enrollment' }, { name: 'Enrollment Phases' }],
  '/facilities': [{ name: 'Settings' }, { name: 'Facilities' }],
  '/settings/users': [{ name: 'Settings' }, { name: 'User Management' }, { name: 'Users' }],
  '/settings/roles': [{ name: 'Settings' }, { name: 'User Management' }, { name: 'Roles & Permissions' }],
  '/settings/benefactors': [{ name: 'Settings' }, { name: 'Manage Benefactors' }],
  '/settings/departments': [{ name: 'Settings' }, { name: 'Manage Department' }],
  '/settings/designations': [{ name: 'Settings' }, { name: 'Manage Designation' }],
  '/devices/manage': [{ name: 'Device Management' }, { name: 'Manage Device' }],
  '/devices/config': [{ name: 'Device Management' }, { name: 'Enrollment Configuration' }],
  '/capitation/generate': [{ name: 'Capitation Module' }, { name: 'Generate Capitation' }],
  '/capitation/review': [{ name: 'Capitation Module' }, { name: 'Review Capitation' }],
  '/capitation/approval': [{ name: 'Capitation Module' }, { name: 'Capitation Approval' }],
  '/capitation/payments': [{ name: 'Capitation Module' }, { name: 'Capitation Payment/Invoices' }],
  '/pas/generate': [{ name: 'Pre-authorization System' }, { name: 'Generate Referral/PA-Code' }],
  '/pas/programmes': [{ name: 'Pre-authorization System' }, { name: 'Manage Programmes/Services' }],
  '/pas/drugs': [{ name: 'Pre-authorization System' }, { name: 'Manage Drugs' }],
  '/pas/labs': [{ name: 'Pre-authorization System' }, { name: 'Manage Labs' }],
  '/pas/clinical': [{ name: 'Pre-authorization System' }, { name: 'Manage Clinical Services' }],
  '/case-categories': [{ name: 'Pre-authorization System' }, { name: 'Case Categories' }],
  '/service-categories': [{ name: 'Pre-authorization System' }, { name: 'Service Categories' }],
  '/do-facilities': [{ name: 'Pre-authorization System' }, { name: 'DO Facility Assignments' }],
  '/claims/referrals': [{ name: 'Claims Management' }, { name: 'Manage Referrals' }],
  '/claims/submissions': [{ name: 'Claims Management' }, { name: 'Claim Submissions' }],
  '/claims/history': [{ name: 'Claims Management' }, { name: 'Claims History' }],
};

const breadcrumbItems = computed(() => {
  return breadcrumbMappings[route.path] || [{ name: 'Page' }];
});
</script>

<style scoped>
/* Additional breadcrumb styles if needed */
</style>
