<template>
  <div class="tw-space-y-6">
    <!-- Request Type Selection -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
      <!-- Referral Option -->
      <v-card
        :class="[
          'tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-lg',
          selectedType === 'referral' ? 'tw-ring-2 tw-ring-blue-500 tw-bg-blue-50' : 'hover:tw-bg-gray-50'
        ]"
        @click="selectType('referral')"
        elevation="2"
      >
        <v-card-text class="tw-p-6 tw-text-center">
          <div class="tw-mb-4">
            <v-icon
              :color="selectedType === 'referral' ? 'blue' : 'grey'"
              size="64"
            >
              mdi-account-arrow-right
            </v-icon>
          </div>
          <h3 class="tw-text-xl tw-font-bold tw-mb-2 tw-text-gray-900">
            Referral Request
          </h3>
          <p class="tw-text-gray-600 tw-mb-4">
            Transfer patient to another facility for specialized care or higher level of treatment
          </p>
          <div class="tw-space-y-2 tw-text-sm tw-text-left">
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Provider-to-provider transfers</span>
            </div>
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Requires approval workflow</span>
            </div>
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Clinical justification required</span>
            </div>
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Generates referral code</span>
            </div>
          </div>
          <v-btn
            v-if="selectedType === 'referral'"
            color="blue"
            variant="flat"
            class="tw-mt-4"
            block
          >
            <v-icon class="tw-mr-2">mdi-check</v-icon>
            Selected
          </v-btn>
        </v-card-text>
      </v-card>

      <!-- PA Code Option -->
      <v-card
        :class="[
          'tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-lg',
          selectedType === 'pa_code' ? 'tw-ring-2 tw-ring-green-500 tw-bg-green-50' : 'hover:tw-bg-gray-50'
        ]"
        @click="selectType('pa_code')"
        elevation="2"
      >
        <v-card-text class="tw-p-6 tw-text-center">
          <div class="tw-mb-4">
            <v-icon
              :color="selectedType === 'pa_code' ? 'green' : 'grey'"
              size="64"
            >
              mdi-qrcode
            </v-icon>
          </div>
          <h3 class="tw-text-xl tw-font-bold tw-mb-2 tw-text-gray-900">
            PA Code Request
          </h3>
          <p class="tw-text-gray-600 tw-mb-4">
            Authorization for specialized services, procedures, or medications to be provided to enrollee
          </p>
          <div class="tw-space-y-2 tw-text-sm tw-text-left">
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Service authorization</span>
            </div>
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Drug/consumable authorization</span>
            </div>
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Immediate activation</span>
            </div>
            <div class="tw-flex tw-items-center">
              <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-check</v-icon>
              <span>Generates PA code</span>
            </div>
          </div>
          <v-btn
            v-if="selectedType === 'pa_code'"
            color="green"
            variant="flat"
            class="tw-mt-4"
            block
          >
            <v-icon class="tw-mr-2">mdi-check</v-icon>
            Selected
          </v-btn>
        </v-card-text>
      </v-card>
    </div>

    <!-- Selected Type Details -->
    <v-card v-if="selectedType" class="tw-border-l-4" :class="selectedType === 'referral' ? 'tw-border-blue-500' : 'tw-border-green-500'">
      <v-card-text class="tw-p-6">
        <div class="tw-flex tw-items-center tw-mb-4">
          <v-icon
            :color="selectedType === 'referral' ? 'blue' : 'green'"
            size="32"
            class="tw-mr-3"
          >
            {{ selectedType === 'referral' ? 'mdi-account-arrow-right' : 'mdi-qrcode' }}
          </v-icon>
          <div>
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">
              {{ selectedType === 'referral' ? 'Referral Request' : 'PA Code Request' }} Selected
            </h3>
            <p class="tw-text-gray-600">
              {{ selectedType === 'referral' 
                ? 'You are creating a referral for patient transfer' 
                : 'You are creating a PA code for service authorization' 
              }}
            </p>
          </div>
        </div>

        <!-- Workflow Information -->
        <div class="tw-bg-gray-50 tw-rounded-lg tw-p-4">
          <h4 class="tw-font-semibold tw-mb-3 tw-text-gray-900">
            {{ selectedType === 'referral' ? 'Referral' : 'PA Code' }} Workflow:
          </h4>
          <div class="tw-space-y-2">
            <div v-if="selectedType === 'referral'" class="tw-space-y-2">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-blue-500">mdi-numeric-1-circle</v-icon>
                <span class="tw-text-sm">Select services requiring referral</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-blue-500">mdi-numeric-2-circle</v-icon>
                <span class="tw-text-sm">Provide clinical justification</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-blue-500">mdi-numeric-3-circle</v-icon>
                <span class="tw-text-sm">Submit for approval</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-blue-500">mdi-numeric-4-circle</v-icon>
                <span class="tw-text-sm">Referral code generated upon approval</span>
              </div>
            </div>
            <div v-else class="tw-space-y-2">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-numeric-1-circle</v-icon>
                <span class="tw-text-sm">Select services/drugs requiring authorization</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-numeric-2-circle</v-icon>
                <span class="tw-text-sm">Provide medical justification</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-numeric-3-circle</v-icon>
                <span class="tw-text-sm">PA code generated immediately</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-green-500">mdi-numeric-4-circle</v-icon>
                <span class="tw-text-sm">Code ready for use at facility</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Important Notes -->
        <v-alert
          :type="selectedType === 'referral' ? 'info' : 'success'"
          variant="tonal"
          class="tw-mt-4"
        >
          <div class="tw-space-y-1">
            <p class="tw-font-semibold">Important Notes:</p>
            <ul class="tw-list-disc tw-list-inside tw-space-y-1 tw-text-sm">
              <li v-if="selectedType === 'referral'">
                Referrals require approval and may take time to process
              </li>
              <li v-if="selectedType === 'referral'">
                Patient must present referral code at receiving facility
              </li>
              <li v-if="selectedType === 'pa_code'">
                PA codes are activated immediately upon creation
              </li>
              <li v-if="selectedType === 'pa_code'">
                Ensure all selected services are medically necessary
              </li>
              <li>All requests must include proper clinical justification</li>
              <li>Codes have expiration dates and usage limits</li>
            </ul>
          </div>
        </v-alert>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue']);

// Computed
const selectedType = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

// Methods
const selectType = (type) => {
  selectedType.value = type;
};
</script>
