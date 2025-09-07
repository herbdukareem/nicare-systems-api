<template>
  <div class="tw-space-y-6">
    <!-- Charts Row -->
    <div class="tw-grid tw-grid-cols-1 tw-lg:grid-cols-2 tw-gap-6">
      <!-- Gender Distribution -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
          Enrollees by Gender
        </h3>
        <div v-if="stats.byGender && stats.byGender.length > 0" class="tw-space-y-4">
          <div
            v-for="item in stats.byGender"
            :key="item.gender"
            class="tw-flex tw-items-center tw-justify-between"
          >
            <div class="tw-flex tw-items-center tw-space-x-3">
              <div
                :class="[
                  'tw-w-4 tw-h-4 tw-rounded-full',
                  item.gender === 'Male' ? 'tw-bg-blue-500' : 'tw-bg-pink-500'
                ]"
              />
              <span class="tw-text-sm tw-font-medium tw-text-gray-700">
                {{ item.gender }}
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
        <div v-else class="tw-text-center tw-py-8">
          <v-icon size="48" color="gray">mdi-gender-male-female</v-icon>
          <p class="tw-text-gray-500 tw-mt-2">No gender data available</p>
        </div>
      </div>

      <!-- Enrollee Types -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
          Enrollees by Type
        </h3>
        <div class="tw-space-y-4">
          <div
            v-for="item in stats.byType"
            :key="item.type"
            class="tw-flex tw-items-center tw-justify-between"
          >
            <div class="tw-flex tw-items-center tw-space-x-3">
              <div class="tw-w-4 tw-h-4 tw-rounded-full tw-bg-green-500" />
              <span class="tw-text-sm tw-font-medium tw-text-gray-700">
                {{ item.type }}
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

    <!-- Additional Charts Row -->
    <div class="tw-grid tw-grid-cols-1 tw-lg:grid-cols-2 tw-gap-6">
      <!-- Benefactor Distribution -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
          Enrollees by Benefactor
        </h3>
        <div class="tw-space-y-4">
          <div
            v-for="item in stats.byBenefactor"
            :key="item.benefactor"
            class="tw-flex tw-items-center tw-justify-between"
          >
            <div class="tw-flex tw-items-center tw-space-x-3">
              <div class="tw-w-4 tw-h-4 tw-rounded-full tw-bg-purple-500" />
              <span class="tw-text-sm tw-font-medium tw-text-gray-700">
                {{ item.benefactor }}
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

      <!-- Monthly Trend -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">
          Monthly Enrollment Trend
        </h3>
        <div class="tw-space-y-3">
          <div
            v-for="item in stats.monthlyTrend"
            :key="item.month"
            class="tw-flex tw-items-center tw-justify-between"
          >
            <span class="tw-text-sm tw-font-medium tw-text-gray-700">
              {{ item.month }}
            </span>
            <div class="tw-flex tw-items-center tw-space-x-2">
              <div class="tw-flex-1 tw-bg-gray-200 tw-rounded-full tw-h-2 tw-min-w-20">
                <div
                  class="tw-bg-blue-500 tw-h-2 tw-rounded-full"
                  :style="{ width: (item.count / 1654) * 100 + '%' }"
                />
              </div>
              <span class="tw-text-sm tw-font-semibold tw-text-gray-900 tw-min-w-12 tw-text-right">
                {{ item.count }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-3 tw-gap-6">
      <div class="tw-bg-gradient-to-r tw-from-blue-500 tw-to-blue-600 tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-blue-100 tw-text-sm tw-font-medium">Average Age</p>
            <p class="tw-text-2xl tw-font-bold">34.5 years</p>
          </div>
          <v-icon size="48" class="tw-opacity-80">mdi-account-clock</v-icon>
        </div>
      </div>

      <div class="tw-bg-gradient-to-r tw-from-green-500 tw-to-green-600 tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-green-100 tw-text-sm tw-font-medium">Growth Rate</p>
            <p class="tw-text-2xl tw-font-bold">+12.5%</p>
          </div>
          <v-icon size="48" class="tw-opacity-80">mdi-trending-up</v-icon>
        </div>
      </div>

      <div class="tw-bg-gradient-to-r tw-from-purple-500 tw-to-purple-600 tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-purple-100 tw-text-sm tw-font-medium">Coverage Rate</p>
            <p class="tw-text-2xl tw-font-bold">87.3%</p>
          </div>
          <v-icon size="48" class="tw-opacity-80">mdi-target</v-icon>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
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
</script>

<style scoped>
/* Additional custom styles */
</style>