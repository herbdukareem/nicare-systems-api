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
  '/pas': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'PAS Dashboard' }],
  '/pas/generate': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Generate Referral/PA-Code' }],
  '/pas/programmes': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Manage Cases' }],
  '/pas/drugs': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Manage Drugs' }],
  '/pas/labs': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Manage Labs' }],
  '/pas/clinical': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Manage Clinical Services' }],
  '/pas/fu-pa-request': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Request FU-PA Code' }],
  '/pas/fu-pa-approval': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'FU-PA Code Approval' }],
  '/pas/referral-management': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Referral Management' }],
  '/case-categories': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Case Categories' }],
  '/service-categories': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Service Categories' }],
  '/do-facilities': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'DO Facility Assignments' }],
  '/document-requirements': [{ name: 'Pre-authorization System', path: '/pas' }, { name: 'Document Requirements' }],
  '/claims': [{ name: 'Claims Management', path: '/claims' }, { name: 'Claims Dashboard' }],
  '/claims/referrals': [{ name: 'Claims Management', path: '/claims' }, { name: 'Submit Referral to PAS' }],
  '/claims/referral-request': [{ name: 'Claims Management', path: '/claims' }, { name: 'Referral Request to PAS' }],
  '/claims/submission': [{ name: 'Claims Management', path: '/claims' }, { name: 'Claim Submission' }],
  '/claims/review': [{ name: 'Claims Management', path: '/claims' }, { name: 'Review Claims' }],
  '/claims/submissions': [{ name: 'Claims Management', path: '/claims' }, { name: 'Claim Submissions' }],
  '/claims/history': [{ name: 'Claims Management', path: '/claims' }, { name: 'Claims History' }],
  '/claims/automation/admissions': [{ name: 'Claims Management', path: '/claims' }, { name: 'Admission Management' }],
  '/claims/automation/process': [{ name: 'Claims Management', path: '/claims' }, { name: 'Claims Processing' }],
  '/claims/automation/bundles': [{ name: 'Claims Management', path: '/claims' }, { name: 'Bundle Management' }],
  '/management': [{ name: 'Management', path: '/management' }, { name: 'Management Dashboard' }],
  '/management/drugs': [{ name: 'Management', path: '/management' }, { name: 'Drugs Management' }],
  '/management/laboratories': [{ name: 'Management', path: '/management' }, { name: 'Laboratories Management' }],
};

const breadcrumbItems = computed(() => {
  return breadcrumbMappings[route.path] || [{ name: 'Page' }];
});
</script>

<style scoped>
/* Additional breadcrumb styles if needed */
</style>
