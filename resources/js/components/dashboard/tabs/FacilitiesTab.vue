<template>
  <div class="tw-space-y-6">
    <!-- Loading -->
    <div v-if="loading" class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
      <v-skeleton-loader type="heading, list-item, list-item, list-item, list-item, list-item"></v-skeleton-loader>
    </div>

    <!-- Summary Cards -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
      <SummaryCard
        icon="mdi-map-marker-multiple"
        gradient="from-blue-500 to-blue-600"
        label="LGAs Listed"
        :value="totalLgas"
      />
      <SummaryCard
        icon="mdi-map-marker"
        gradient="from-green-500 to-green-600"
        label="Wards (Top list)"
        :value="totalWards"
      />
      <SummaryCard
        icon="mdi-hospital-building"
        gradient="from-purple-500 to-purple-600"
        label="Avg Enrollees / Facility (Top 10)"
        :value="avgPerFacilityTop10"
        :format="v => Number(v).toFixed(1)"
      />
      <SummaryCard
        icon="mdi-target"
        gradient="from-orange-500 to-orange-600"
        label="Avg Utilization (Top 5)"
        :value="avgUtilizationTop5"
        :suffix="'%'"
        :format="v => Number(v).toFixed(1)"
      />
    </div>

    <!-- Charts/Lists Row -->
    <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-6">
      <!-- Enrollees by LGA -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
          <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Enrollees by LGA</h3>
          <v-chip size="small" color="primary" variant="tonal">{{ totalEnrolleesByLga.toLocaleString() }} total</v-chip>
        </div>

        <div v-if="byLgaNorm.length" class="tw-space-y-4 tw-max-h-96 tw-overflow-y-auto">
          <div
            v-for="it in byLgaNorm"
            :key="it.lga"
            class="tw-flex tw-items-start tw-gap-4"
          >
            <div class="tw-w-10 tw-h-10 tw-rounded-full tw-flex tw-items-center tw-justify-center tw-bg-blue-50">
              <span class="tw-text-blue-600 tw-text-sm tw-font-semibold">
                {{ it.rank }}
              </span>
            </div>
            <div class="tw-flex-1">
              <div class="tw-flex tw-items-center tw-justify-between">
                <p class="tw-text-sm tw-font-medium tw-text-gray-900">{{ it.lga }}</p>
                <p class="tw-text-sm tw-font-semibold tw-text-gray-900">
                  {{ it.count.toLocaleString() }}
                  <span class="tw-text-gray-500 tw-font-normal">({{ it.perc.toFixed(1) }}%)</span>
                </p>
              </div>
              <v-progress-linear
                class="tw-mt-2"
                color="primary"
                rounded
                :model-value="it.perc"
                height="8"
              />
            </div>
          </div>
        </div>
        <EmptyState v-else icon="mdi-map-marker-off" text="No LGA data available" />
      </div>

      <!-- Enrollees by Ward (Top 10) -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
          <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Enrollees by Ward (Top 10)</h3>
          <v-chip size="small" color="green" variant="tonal">{{ totalEnrolleesByWard.toLocaleString() }} total</v-chip>
        </div>

        <div v-if="byWardNorm.length" class="tw-space-y-4 tw-max-h-96 tw-overflow-y-auto">
          <div
            v-for="it in byWardNorm"
            :key="it.ward"
            class="tw-flex tw-items-start tw-gap-4"
          >
            <div class="tw-w-10 tw-h-10 tw-rounded-full tw-flex tw-items-center tw-justify-center tw-bg-green-50">
              <span class="tw-text-green-600 tw-text-sm tw-font-semibold">
                {{ it.rank }}
              </span>
            </div>
            <div class="tw-flex-1">
              <div class="tw-flex tw-items-center tw-justify-between">
                <p class="tw-text-sm tw-font-medium tw-text-gray-900">{{ it.ward }}</p>
                <p class="tw-text-sm tw-font-semibold tw-text-gray-900">
                  {{ it.count.toLocaleString() }}
                  <span class="tw-text-gray-500 tw-font-normal">({{ it.perc.toFixed(1) }}%)</span>
                </p>
              </div>
              <v-progress-linear
                class="tw-mt-2"
                color="green"
                rounded
                :model-value="it.perc"
                height="8"
              />
            </div>
          </div>
        </div>
        <EmptyState v-else icon="mdi-map-marker" text="No ward data available" />
      </div>
    </div>

    <!-- Facility Distribution (Top 10) -->
    <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
      <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Enrollees by Facility (Top 10)</h3>
        <v-chip size="small" color="purple" variant="tonal">{{ totalEnrolleesByFacility.toLocaleString() }} total</v-chip>
      </div>

      <div v-if="byFacilityNorm.length" class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-6">
        <div
          v-for="it in byFacilityNorm"
          :key="it.facility"
          class="tw-bg-gray-50 tw-rounded-lg tw-p-4 tw-border tw-border-gray-100"
        >
          <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
            <h4 class="tw-text-sm tw-font-medium tw-text-gray-900 tw-line-clamp-1">
              {{ it.facility }}
            </h4>
            <v-chip size="x-small" color="purple" variant="flat">{{ it.perc.toFixed(1) }}%</v-chip>
          </div>
          <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
            <span class="tw-text-2xl tw-font-bold tw-text-gray-900">
              {{ it.count.toLocaleString() }}
            </span>
            <v-icon size="20" color="purple">mdi-hospital-building</v-icon>
          </div>
          <v-progress-linear
            color="purple"
            rounded
            :model-value="it.perc"
            height="8"
          />
        </div>
      </div>
      <EmptyState v-else icon="mdi-hospital-off" text="No facility data available" />
    </div>

    <!-- Top Performing Facilities -->
    <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
      <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Top Performing Facilities</h3>
        <div class="tw-flex tw-items-center tw-gap-2">
          <v-chip v-if="topFacilities.length" size="small" color="primary" variant="tonal">
            Avg Utilization: {{ avgUtilizationTop5.toFixed(1) }}%
          </v-chip>
          <v-chip v-if="topFacilities.length" size="small" color="secondary" variant="tonal">
            Avg Enrollees: {{ avgEnrolleesTop5.toLocaleString() }}
          </v-chip>
        </div>
      </div>

      <v-data-table
        :headers="topHeaders"
        :items="topFacilities"
        class="tw-elevation-0"
        :items-per-page="5"
        :loading="loading"
      >
        <template #item.enrollees="{ item }">
          <span class="tw-font-semibold">{{ Number(item.enrollees ?? 0).toLocaleString() }}</span>
        </template>

        <template #item.capacity="{ item }">
          <span class="tw-text-gray-700">{{ Number(item.capacity ?? 0).toLocaleString() }}</span>
        </template>

        <template #item.utilization="{ item }">
          <div class="tw-flex tw-items-center tw-gap-2 tw-w-48">
            <v-progress-linear
              :model-value="Number(item.utilization ?? 0)"
              height="10"
              rounded
              :color="utilColor(Number(item.utilization ?? 0))"
            />
            <span class="tw-text-sm tw-font-semibold tw-w-12">{{ Number(item.utilization ?? 0).toFixed(1) }}%</span>
          </div>
        </template>

        <template #no-data>
          <EmptyState icon="mdi-database-off" text="No top facility records available" />
        </template>
      </v-data-table>
    </div>
  </div>
</template>

<script setup>
import { computed, defineComponent } from 'vue'

const props = defineProps({
  stats: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

/* ------------------------------ Normalization ------------------------------ */
const byLga = computed(() => props.stats?.byLga || [])
const byWard = computed(() => (props.stats?.byWard || []))
const byFacility = computed(() => (props.stats?.byFacility || []).slice(0, 10))
const topFacilities = computed(() => props.stats?.topFacilities || [])

const totalEnrolleesByLga = computed(() =>
  byLga.value.reduce((a, b) => a + Number(b.count || 0), 0)
)
const totalEnrolleesByWard = computed(() =>
  byWard.value.reduce((a, b) => a + Number(b.count || 0), 0)
)
const totalEnrolleesByFacility = computed(() =>
  byFacility.value.reduce((a, b) => a + Number(b.count || 0), 0)
)

function withPerc(list, total, labelKey) {
  const t = total || list.reduce((a, b) => a + Number(b.count || 0), 0) || 1
  return list.map((x, idx) => ({
    rank: idx + 1,
    [labelKey]: x[labelKey],
    count: Number(x.count || 0),
    perc: x.percentage != null ? Number(x.percentage) : (Number(x.count || 0) / t) * 100,
    ...(x.lga ? { lga: x.lga } : {}),
  }))
}

const byLgaNorm = computed(() => withPerc(byLga.value, totalEnrolleesByLga.value, 'lga'))
const byWardNorm = computed(() => withPerc(byWard.value, totalEnrolleesByWard.value, 'ward'))
const byFacilityNorm = computed(() => withPerc(byFacility.value, totalEnrolleesByFacility.value, 'facility'))

/* ------------------------------- Summaries -------------------------------- */
const totalLgas = computed(() => byLga.value.length)
const totalWards = computed(() => byWard.value.length)

const avgPerFacilityTop10 = computed(() => {
  const n = byFacility.value.length || 1
  return byFacility.value.reduce((a, b) => a + Number(b.count || 0), 0) / n
})

const avgUtilizationTop5 = computed(() => {
  if (!topFacilities.value.length) return 0
  const n = topFacilities.value.length
  return topFacilities.value.reduce((a, b) => a + Number(b.utilization || 0), 0) / n
})

const avgEnrolleesTop5 = computed(() =>
  topFacilities.value.reduce((a, b) => a + Number(b.enrollees || 0), 0) / (topFacilities.value.length || 1)
)

/* ------------------------------- Table meta ------------------------------- */
const topHeaders = [
  { title: 'Facility Name', key: 'name', sortable: true },
  { title: 'LGA', key: 'lga', sortable: true },
  { title: 'Enrollees', key: 'enrollees', sortable: true },
  { title: 'Capacity', key: 'capacity', sortable: true },
  { title: 'Utilization', key: 'utilization', sortable: true },
]

/* --------------------------------- Utils ---------------------------------- */
function utilColor(v) {
  if (v >= 100) return 'red'
  if (v >= 80) return 'orange'
  if (v >= 50) return 'green'
  return 'blue'
}

/* ---------------------- Reusable micro components ------------------------- */
const SummaryCard = defineComponent({
  name: 'SummaryCard',
  props: {
    icon: String,
    gradient: { type: String, default: 'from-gray-500 to-gray-600' },
    label: String,
    value: [String, Number],
    suffix: { type: String, default: '' },
    format: { type: Function, default: (v) => v },
  },
  template: `
    <div class="tw-bg-gradient-to-r tw-rounded-lg tw-shadow-sm tw-p-6 tw-text-white"
         :class="'tw-'+gradient">
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <p class="tw-opacity-90 tw-text-sm tw-font-medium">{{ label }}</p>
          <p class="tw-text-2xl tw-font-bold">
            {{ format(value) }}<span v-if="suffix">{{ suffix }}</span>
          </p>
        </div>
        <v-icon size="48" class="tw-opacity-80">{{ icon }}</v-icon>
      </div>
    </div>
  `,
})

const EmptyState = defineComponent({
  name: 'EmptyState',
  props: { icon: String, text: String },
  template: `
    <div class="tw-flex tw-items-center tw-justify-center tw-py-10">
      <div class="tw-text-center">
        <v-icon size="48" color="grey">{{ icon }}</v-icon>
        <p class="tw-text-gray-500 tw-mt-2">{{ text }}</p>
      </div>
    </div>
  `,
})
</script>

<style scoped>
/* Subtle polish */
.tw-line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
