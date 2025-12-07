<template>
  <v-navigation-drawer
    v-model="drawer"
    :rail="rail"
    permanent
    @click="rail = false"
  >
    <v-list-item
      prepend-avatar="https://randomuser.me/api/portraits/women/85.jpg"
      title="Claims Module"
      subtitle="Referral & Claim Management"
    ></v-list-item>

    <v-divider></v-divider>

    <v-list density="compact" nav>
      <!-- Referral Section -->
      <v-list-group>
        <template v-slot:activator="{ props }">
          <v-list-item
            v-bind="props"
            prepend-icon="mdi-account-arrow-right"
            title="Referrals"
          ></v-list-item>
        </template>

        <v-list-item
          to="/claims/referrals"
          prepend-icon="mdi-plus-circle"
          title="Submit Referral"
          @click="$emit('navigate')"
        ></v-list-item>

        <v-list-item
          to="/pas"
          prepend-icon="mdi-list"
          title="View Referrals"
          @click="$emit('navigate')"
        ></v-list-item>

        <v-list-item
          to="/pas/validate-utn"
          prepend-icon="mdi-check-circle"
          title="Validate UTN"
          @click="$emit('navigate')"
        ></v-list-item>
      </v-list-group>

      <!-- Claims Section -->
      <v-list-group>
        <template v-slot:activator="{ props }">
          <v-list-item
            v-bind="props"
            prepend-icon="mdi-file-document"
            title="Claims"
          ></v-list-item>
        </template>

        <v-list-item
          to="/claims/submissions"
          prepend-icon="mdi-plus-circle"
          title="Submit Claim"
          @click="$emit('navigate')"
        ></v-list-item>

        <v-list-item
          to="/claims/review"
          prepend-icon="mdi-clipboard-check"
          title="Review Claims"
          @click="$emit('navigate')"
        ></v-list-item>

        <v-list-item
          to="/claims/history"
          prepend-icon="mdi-history"
          title="Claims History"
          @click="$emit('navigate')"
        ></v-list-item>
      </v-list-group>

      <!-- Automation Section -->
      <v-list-group>
        <template v-slot:activator="{ props }">
          <v-list-item
            v-bind="props"
            prepend-icon="mdi-robot"
            title="Automation"
          ></v-list-item>
        </template>

        <v-list-item
          to="/claims/automation/admissions"
          prepend-icon="mdi-hospital-box"
          title="Admissions"
          @click="$emit('navigate')"
        ></v-list-item>

        <v-list-item
          to="/claims/automation/process"
          prepend-icon="mdi-file-document-edit"
          title="Process Claims"
          @click="$emit('navigate')"
        ></v-list-item>

        <v-list-item
          to="/claims/automation/bundles"
          prepend-icon="mdi-package-variant"
          title="Bundle Management"
          @click="$emit('navigate')"
        ></v-list-item>
      </v-list-group>

      <!-- Payment Section -->
      <v-list-group>
        <template v-slot:activator="{ props }">
          <v-list-item
            v-bind="props"
            prepend-icon="mdi-cash-multiple"
            title="Payments"
          ></v-list-item>
        </template>

        <v-list-item
          to="/claims/payments"
          prepend-icon="mdi-credit-card"
          title="Payment Tracking"
          @click="$emit('navigate')"
        ></v-list-item>

        <v-list-item
          to="/claims/reports"
          prepend-icon="mdi-chart-bar"
          title="Payment Reports"
          @click="$emit('navigate')"
        ></v-list-item>
      </v-list-group>
    </v-list>

    <template v-slot:append>
      <div class="pa-4">
        <v-btn
          block
          prepend-icon="mdi-chevron-left"
          @click.stop="rail = !rail"
        >
          {{ rail ? 'Expand' : 'Collapse' }}
        </v-btn>
      </div>
    </template>
  </v-navigation-drawer>
</template>

<script setup>
import { ref } from 'vue';

const drawer = ref(true);
const rail = ref(false);

defineEmits(['navigate']);
</script>

<style scoped>
</style>

