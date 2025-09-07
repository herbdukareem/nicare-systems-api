<template>
  <div class="tw-space-y-6">
    <!-- Charts Row -->
    <div class="tw-grid tw-grid-cols-1 tw-lg:grid-cols-2 tw-gap-6">
      <!-- Enrollees by LGA -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
          Enrollees by LGA
        </h3>
        <div class="tw-space-y-4">
          <div
            v-for="item in stats.byLga"
            :key="item.lga"
            class="tw-flex tw-items-center tw-justify-between"
          >
            <div class="tw-flex tw-items-center tw-space-x-3">
              <div class="tw-w-4 tw-h-4 tw-rounded-full tw-bg-blue-500" />
              <span class="tw-text-sm tw-font-medium tw-text-gray-700">
                {{ item.lga }}
              </span>
            </div>
            <div class="tw-text-right">
              <span class="tw-text-sm tw-font-semibold tw-text-gray-900">
                {{ item.count.toLocaleString() }}
              </span>
              <span class="tw-text-xs tw-text-gray-500 tw-ml-2">
                ({{ item.percentage }}%)
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Enrollees by Ward -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
          Enrollees by Ward
        </h3>
        <div class="tw-space-y-4">
          <div
            v-for="item in stats.byWard"
            :key="item.ward"
            class="tw-flex tw-items-center tw-justify-between"
          >
            <div class="tw-flex tw-items-center tw-space-x-3">
              <div class="tw-w-4 tw-h-4 tw-rounded-full tw-bg-green-500" />
              <span class="tw-text-sm tw-font-medium tw-text-gray-700">
                {{ item.ward }}
              </span>
            </div>
            <div class="tw-text-right">
              <span class="tw-text-sm tw-font-semibold tw-text-gray-900">
                {{ item.count.toLocaleString() }}
              </span>
              <span class="tw-text-xs tw-text-gray-500 tw-ml-2">
                ({{ item.percentage }}%)
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Facility Distribution -->
    <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
      <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
        Enrollees by Facility Type
      </h3>
      <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-2 tw-lg:grid-cols-3 tw-gap-6">
        <div
          v-for="item in stats.byFacility"
          :key="item.facility"
          class="tw-bg-gray-50 tw-rounded-lg tw-p-4"
        >
          <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
            <h4 class="tw-text-sm tw-font-medium tw-text-gray-900">
              {{ item.facility }}
            </h4>
            <v-icon size="20" color="blue">mdi-hospital-building</v-icon>
          </div>
          <div class="tw-flex tw-items-center tw-justify-between">
            <span class="tw-text-2xl tw-font-bold tw-text-gray-900">
              {{ item.count.toLocaleString() }}
            </span>
            <span class="tw-text-sm tw-text-gray-500">
              {{ item.percentage }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-4 tw-gap-6">
      <div class="tw-bg-gradient-to-r tw-from-blue-500 tw-to-blue-600 tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-blue-100 tw-text-sm tw-font-medium">Total LGAs</p>
            <p class="tw-text-2xl tw-font-bold">20</p>
          </div>
          <v-icon size="48" class="tw-opacity-80">mdi-map-marker-multiple</v-icon>
        </div>
      </div>

      <div class="tw-bg-gradient-to-r tw-from-green-500 tw-to-green-600 tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-green-100 tw-text-sm tw-font-medium">Total Wards</p>
            <p class="tw-text-2xl tw-font-bold">256</p>
          </div>
          <v-icon size="48" class="tw-opacity-80">mdi-map-marker</v-icon>
        </div>
      </div>

      <div class="tw-bg-gradient-to-r tw-from-purple-500 tw-to-purple-600 tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-purple-100 tw-text-sm tw-font-medium">Avg per Facility</p>
            <p class="tw-text-2xl tw-font-bold">53.6</p>
          </div>
          <v-icon size="48" class="tw-opacity-80">mdi-chart-line</v-icon>
        </div>
      </div>

      <div class="tw-bg-gradient-to-r tw-from-orange-500 tw-to-orange-600 tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-orange-100 tw-text-sm tw-font-medium">Coverage</p>
            <p class="tw-text-2xl tw-font-bold">94.2%</p>
          </div>
          <v-icon size="48" class="tw-opacity-80">mdi-target</v-icon>
        </div>
      </div>
    </div>

    <!-- Top Performing Facilities -->
    <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
      <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
        Top Performing Facilities
      </h3>
      <div class="tw-overflow-x-auto">
        <table class="tw-min-w-full tw-divide-y tw-divide-gray-200">
          <thead class="tw-bg-gray-50">
            <tr>
              <th class="tw-px-6 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-text-gray-500 tw-uppercase tw-tracking-wider">
                Facility Name
              </th>
              <th class="tw-px-6 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-text-gray-500 tw-uppercase tw-tracking-wider">
                LGA
              </th>
              <th class="tw-px-6 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-text-gray-500 tw-uppercase tw-tracking-wider">
                Enrollees
              </th>
              <th class="tw-px-6 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-text-gray-500 tw-uppercase tw-tracking-wider">
                Capacity
              </th>
              <th class="tw-px-6 tw-py-3 tw-text-left tw-text-xs tw-font-medium tw-text-gray-500 tw-uppercase tw-tracking-wider">
                Utilization
              </th>
            </tr>
          </thead>
          <tbody class="tw-bg-white tw-divide-y tw-divide-gray-200">
            <tr v-for="facility in topFacilities" :key="facility.name">
              <td class="tw-px-6 tw-py-4 tw-whitespace-nowrap tw-text-sm tw-font-medium tw-text-gray-900">
                {{ facility.name }}
              </td>
              <td class="tw-px-6 tw-py-4 tw-whitespace-nowrap tw-text-sm tw-text-gray-500">
                {{ facility.lga }}
              </td>
              <td class="tw-px-6 tw-py-4 tw-whitespace-nowrap tw-text-sm tw-text-gray-900">
                {{ facility.enrollees }}
              </td>
              <td class="tw-px-6 tw-py-4 tw-whitespace-nowrap tw-text-sm tw-text-gray-500">
                {{ facility.capacity }}
              </td>
              <td class="tw-px-6 tw-py-4 tw-whitespace-nowrap">
                <div class="tw-flex tw-items-center">
                  <div class="tw-flex-1 tw-bg-gray-200 tw-rounded-full tw-h-2 tw-mr-2">
                    <div
                      class="tw-bg-blue-500 tw-h-2 tw-rounded-full"
                      :style="{ width: facility.utilization + '%' }"
                    />
                  </div>
                  <span class="tw-text-sm tw-text-gray-900 tw-w-12">
                    {{ facility.utilization }}%
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  stats: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

// Mock data for top facilities
const topFacilities = ref([
  {
    name: 'Lagos State University Teaching Hospital',
    lga: 'Ikeja',
    enrollees: 2341,
    capacity: 2500,
    utilization: 94,
  },
  {
    name: 'General Hospital Lagos Island',
    lga: 'Lagos Island',
    enrollees: 1892,
    capacity: 2000,
    utilization: 95,
  },
  {
    name: 'Primary Health Center Surulere',
    lga: 'Surulere',
    enrollees: 1654,
    capacity: 1800,
    utilization: 92,
  },
  {
    name: 'Federal Medical Center Ebute Metta',
    lga: 'Lagos Mainland',
    enrollees: 1523,
    capacity: 1700,
    utilization: 90,
  },
  {
    name: 'Lagos University Teaching Hospital',
    lga: 'Idi-Araba',
    enrollees: 1432,
    capacity: 1600,
    utilization: 90,
  },
]);
</script>

<style scoped>
/* Additional custom styles */
</style>