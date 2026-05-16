<template>
  <AdminLayout>
    <div class="tw-space-y-4">

      <!-- ── Header Banner ──────────────────────────────────────────────────── -->
      <section
        class="tw-rounded-2xl tw-overflow-hidden tw-relative"
        style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 55%, #0d6e6e 100%);"
      >
        <!-- Decorative orbs -->
        <div class="tw-absolute tw-inset-0 tw-pointer-events-none tw-overflow-hidden">
          <div class="tw-absolute tw--top-20 tw--right-20 tw-w-80 tw-h-80 tw-rounded-full" style="background: radial-gradient(circle, rgba(96,165,250,0.18) 0%, transparent 70%);" />
          <div class="tw-absolute tw--bottom-16 tw--left-16 tw-w-64 tw-h-64 tw-rounded-full" style="background: radial-gradient(circle, rgba(52,211,153,0.14) 0%, transparent 70%);" />
          <div class="tw-absolute tw-top-0 tw-left-1/2 tw-w-48 tw-h-48 tw-rounded-full tw-opacity-50" style="background: radial-gradient(circle, rgba(167,139,250,0.1) 0%, transparent 70%);" />
        </div>

        <div class="tw-relative tw-px-5 tw-pt-4 tw-pb-4">
          <!-- Top row: title + refresh -->
          <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
            <div>
              <h1 class="tw-text-xl sm:tw-text-2xl tw-font-black tw-text-white tw-tracking-tight tw-leading-none">
                Programme Intelligence
              </h1>
              <p class="tw-text-white/50 tw-text-xs tw-mt-1 tw-flex tw-items-center tw-gap-1 tw-flex-wrap">
                Coverage, equity and provider readiness overview
                <span class="tw-text-white/30 tw-mx-1">·</span>
                <span class="tw-text-white/80 tw-font-semibold">{{ currentMonth }}</span>
              </p>
            </div>
            <v-btn
              color="white"
              variant="tonal"
              :loading="loading"
              size="x-small"
              prepend-icon="mdi-refresh"
              class="tw-shrink-0"
              @click="loadDashboard"
            >
              Refresh
            </v-btn>
          </div>

          <!-- Quick-stat pills -->
          <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-4 tw-gap-2">
            <div
              v-for="pill in headerPills"
              :key="pill.label"
              class="tw-bg-white/10 tw-backdrop-blur-sm tw-border tw-border-white/10 tw-px-3 tw-py-2 tw-flex tw-flex-col tw-gap-0.5"
            >
              <p class="tw-text-[9px] tw-text-white/50 tw-font-semibold tw-uppercase tw-tracking-widest">{{ pill.label }}</p>
              <p class="tw-text-base sm:tw-text-lg tw-font-black tw-text-white tw-leading-none">{{ pill.value }}</p>
              <p v-if="pill.sub" class="tw-text-[9px] tw-text-white/40">{{ pill.sub }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Loading State ───────────────────────────────────────────────────── -->
      <div v-if="loading" class="tw-grid tw-grid-cols-2 xl:tw-grid-cols-6 tw-gap-4">
        <div v-for="i in 6" :key="i" class="tw-h-20 tw-bg-white tw-border tw-border-gray-100 tw-animate-pulse" />
      </div>

      <template v-else>

        <!-- ── Executive KPI Cards ──────────────────────────────────────────── -->
        <section class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-2 xl:tw-grid-cols-3 2xl:tw-grid-cols-6 tw-gap-4">
          <KpiCard
            v-for="card in executiveCards"
            :key="card.label"
            :label="card.label"
            :value="formatValue(card.value)"
            :helper="card.helper"
            :icon="card.icon"
            :tone="card.tone"
          />
        </section>

        <!-- ── Coverage Performance Meters (3 cards in a row) ──────────────── -->
        <section class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-3 tw-gap-4">
          <div
            v-for="meter in performanceMeters"
            :key="meter.label"
            class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-p-5 tw-flex tw-items-center tw-gap-5"
          >
            <!-- Ring gauge -->
            <div class="tw-relative tw-w-20 tw-h-20 tw-shrink-0">
              <svg class="tw-w-full tw-h-full tw--rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3" />
                <circle
                  cx="18" cy="18" r="15.9"
                  fill="none"
                  :stroke="meter.stroke"
                  stroke-width="3"
                  stroke-linecap="round"
                  :stroke-dasharray="`${meter.pct} 100`"
                  style="transition: stroke-dasharray 0.8s ease"
                />
              </svg>
              <span class="tw-absolute tw-inset-0 tw-flex tw-items-center tw-justify-center tw-text-sm tw-font-black tw-text-gray-900">{{ meter.pct }}%</span>
            </div>
            <!-- Text -->
            <div class="tw-flex-1 tw-min-w-0">
              <p class="tw-text-base tw-font-bold tw-text-gray-900 tw-leading-tight">{{ meter.label }}</p>
              <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5 tw-mb-2">{{ meter.sub }}</p>
              <div class="tw-w-full tw-h-1.5 tw-bg-gray-100 tw-rounded-full tw-overflow-hidden">
                <div
                  class="tw-h-full tw-rounded-full"
                  :style="{ width: meter.pct + '%', backgroundColor: meter.stroke, transition: 'width 0.8s ease' }"
                />
              </div>
            </div>
          </div>
        </section>

        <!-- ── Coverage Progress + Approval Pipeline ───────────────────────── -->
        <section class="tw-grid tw-grid-cols-1 xl:tw-grid-cols-3 tw-gap-6">

          <!-- Progress rows -->
          <div class="xl:tw-col-span-2 tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-justify-between">
              <div>
                <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Coverage Breakdown</h2>
                <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Active eligibility and risk exposure across enrolled lives</p>
              </div>
              <div class="tw-flex tw-items-center tw-gap-2">
                <div class="tw-w-2 tw-h-2 tw-rounded-full tw-bg-green-500 tw-animate-pulse" />
                <span class="tw-text-xs tw-font-semibold tw-text-green-600">{{ performance.coverage_rate || 0 }}% active</span>
              </div>
            </div>
            <div class="tw-p-6 tw-space-y-4">
              <ProgressBar label="Active Coverage" :value="coverage.active" :max="totalEnrollees" color="#22c55e" />
              <ProgressBar label="No Expiry (Permanent)" :value="coverage.no_expiry" :max="Math.max(coverage.active, 1)" color="#3b82f6" />
              <ProgressBar label="Expiring within 30 Days" :value="coverage.expiring_30_days" :max="Math.max(coverage.active, 1)" color="#f59e0b" />
              <ProgressBar label="Expired / Inactive" :value="coverage.expired_or_inactive" :max="totalEnrollees" color="#ef4444" />
            </div>
          </div>

          <!-- Approval Pipeline -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100">
              <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Approval Pipeline</h2>
              <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Enrollee status distribution for operational follow-up</p>
            </div>
            <div class="tw-p-6 tw-space-y-3">
              <div
                v-for="item in (overview.status_breakdown || [])"
                :key="item.label"
                class="tw-flex tw-items-center tw-gap-3"
              >
                <div :class="`tw-w-2 tw-h-2 tw-rounded-full tw-shrink-0 ${statusDot(item.label)}`" />
                <div class="tw-flex-1 tw-min-w-0">
                  <div class="tw-flex tw-items-center tw-justify-between tw-mb-1">
                    <span class="tw-text-xs tw-font-semibold tw-text-gray-700 tw-truncate">{{ item.label }}</span>
                    <span class="tw-text-xs tw-font-bold tw-text-gray-900 tw-ml-2 tw-shrink-0">{{ number(item.count) }}</span>
                  </div>
                  <div class="tw-w-full tw-h-1.5 tw-bg-gray-100 tw-rounded-full tw-overflow-hidden">
                    <div
                      :class="`tw-h-full tw-rounded-full ${statusBar(item.label)}`"
                      :style="{ width: Math.min(100, Math.round((item.count / Math.max(totalEnrollees, 1)) * 100)) + '%', transition: 'width 0.8s ease' }"
                    />
                  </div>
                </div>
                <span class="tw-text-xs tw-text-gray-400 tw-shrink-0 tw-w-9 tw-text-right">{{ item.percentage || 0 }}%</span>
              </div>
            </div>
            <div v-if="overview.status_breakdown?.length" class="tw-px-6 tw-pb-5">
              <div class="tw-h-px tw-bg-gray-100 tw-mb-4" />
              <div class="tw-flex tw-justify-around tw-text-center">
                <div>
                  <p class="tw-text-xs tw-text-gray-400">Total</p>
                  <p class="tw-text-lg tw-font-extrabold tw-text-gray-900">{{ number(totalEnrollees) }}</p>
                </div>
                <div>
                  <p class="tw-text-xs tw-text-gray-400">Active</p>
                  <p class="tw-text-lg tw-font-extrabold tw-text-green-600">{{ number(coverage.active) }}</p>
                </div>
                <div>
                  <p class="tw-text-xs tw-text-gray-400">Pending</p>
                  <p class="tw-text-lg tw-font-extrabold tw-text-amber-600">{{ number(coverage.pending) }}</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Enrollment Trend (Interactive: Year → Month) + Programme Mix ── -->
        <section class="tw-grid tw-grid-cols-1 xl:tw-grid-cols-3 tw-gap-6">

          <!-- Enrollment Trend -->
          <div class="xl:tw-col-span-2 tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-justify-between tw-gap-3 tw-flex-wrap">
              <div>
                <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Enrollment Trend</h2>
                <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">
                  <template v-if="trendDrillLevel === 'year'">Yearly totals — click a bar to see monthly breakdown</template>
                  <template v-else>Monthly breakdown for <span class="tw-font-semibold tw-text-gray-700">{{ trendSelectedYear }}</span></template>
                </p>
              </div>
              <div class="tw-flex tw-items-center tw-gap-2">
                <!-- Back button in month view -->
                <v-btn
                  v-if="trendDrillLevel === 'month'"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="mdi-arrow-left"
                  @click="backToYears"
                >
                  All Years
                </v-btn>
                <v-chip v-if="trendDrillLevel === 'year'" size="x-small" color="blue" variant="tonal">Click a bar</v-chip>
                <span v-else class="tw-flex tw-items-center tw-gap-1.5 tw-text-xs tw-text-gray-500">
                  <span class="tw-inline-block tw-w-3 tw-h-0.5 tw-bg-blue-500 tw-rounded" />Enrollments
                </span>
              </div>
            </div>
            <div class="tw-p-5 tw-relative">
              <div v-if="trendLoading" class="tw-h-60 tw-flex tw-items-center tw-justify-center">
                <v-progress-circular indeterminate color="primary" size="32" />
              </div>
              <!-- Year bar chart -->
              <BarChart
                v-else-if="trendDrillLevel === 'year' && trendYearChartData.labels?.length"
                :data="trendYearChartData"
                :options="trendYearOptions"
                :height="240"
              />
              <!-- Month line chart -->
              <LineChart
                v-else-if="trendDrillLevel === 'month' && trendMonthChartData.labels?.length"
                :data="trendMonthChartData"
                :height="240"
              />
              <div v-else class="tw-h-60 tw-flex tw-items-center tw-justify-center tw-text-gray-400 tw-text-sm">
                No enrollment data available
              </div>
            </div>
          </div>

          <!-- Programme Mix -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100">
              <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Programme Mix</h2>
              <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Enrollee distribution across insurance programmes</p>
            </div>
            <div class="tw-p-5">
              <DoughnutChart v-if="programmeMixData.labels?.length" :data="programmeMixData" :height="180" />
              <p v-else class="tw-py-8 tw-text-center tw-text-gray-400 tw-text-sm">No programme data yet</p>
              <div class="tw-mt-4 tw-space-y-2">
                <div
                  v-for="(item, i) in (overview.programme_mix || []).slice(0, 5)"
                  :key="item.label"
                  class="tw-flex tw-items-center tw-gap-2"
                >
                  <div class="tw-w-2 tw-h-2 tw-rounded-full tw-shrink-0" :style="{ backgroundColor: CHART_COLORS[i % CHART_COLORS.length] }" />
                  <span class="tw-text-xs tw-text-gray-600 tw-flex-1 tw-truncate">{{ item.label }}</span>
                  <span class="tw-text-xs tw-font-semibold tw-text-gray-900">{{ number(item.count) }}</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Geographic Reach (Interactive: LGA → Wards) + Provider Load ── -->
        <section class="tw-grid tw-grid-cols-1 xl:tw-grid-cols-2 tw-gap-6">

          <!-- Geographic Reach -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-justify-between tw-gap-3 tw-flex-wrap">
              <div>
                <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Geographic Reach</h2>
                <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">
                  <template v-if="geoView === 'lga'">LGA spread — click a row to see ward breakdown</template>
                  <template v-else>Wards under <span class="tw-font-semibold tw-text-gray-700">{{ selectedLga?.lga_name }}</span></template>
                </p>
              </div>
              <div class="tw-flex tw-items-center tw-gap-2">
                <v-btn
                  v-if="geoView === 'ward'"
                  size="x-small"
                  variant="tonal"
                  color="teal"
                  prepend-icon="mdi-arrow-left"
                  @click="backToLgas"
                >
                  All LGAs
                </v-btn>
                <div v-else class="tw-flex tw-items-center tw-gap-2 tw-bg-teal-50 tw-border tw-border-teal-100 tw-rounded-lg tw-px-3 tw-py-1.5">
                  <v-icon size="14" color="teal">mdi-map-marker-multiple</v-icon>
                  <span class="tw-text-xs tw-font-bold tw-text-teal-700">
                    {{ geography.lga_reach?.covered || 0 }} / {{ geography.lga_reach?.total || 0 }} LGAs
                  </span>
                </div>
              </div>
            </div>

            <div class="tw-p-5 tw-space-y-3 tw-relative">
              <!-- Loading overlay -->
              <div v-if="wardLoading" class="tw-flex tw-items-center tw-justify-center tw-py-10">
                <v-progress-circular indeterminate color="teal" size="28" />
              </div>

              <!-- LGA view -->
              <template v-else-if="geoView === 'lga'">
                <div
                  v-for="(item, i) in (geography.top_lgas || []).slice(0, 8)"
                  :key="item.lga"
                  class="tw-flex tw-items-center tw-gap-3 tw-cursor-pointer tw-p-2 tw--mx-2 hover:tw-bg-teal-50 tw-transition-colors tw-group"
                  @click="drillToLga(item)"
                >
                  <span class="tw-text-xs tw-font-bold tw-text-gray-400 tw-w-4 tw-text-right tw-shrink-0">{{ i + 1 }}</span>
                  <div class="tw-flex-1 tw-min-w-0">
                    <div class="tw-flex tw-justify-between tw-mb-1">
                      <span class="tw-text-sm tw-font-medium tw-text-gray-700 tw-truncate group-hover:tw-text-teal-700">{{ item.lga }}</span>
                      <span class="tw-text-sm tw-font-bold tw-text-teal-700 tw-ml-2 tw-shrink-0">{{ number(item.count) }}</span>
                    </div>
                    <div class="tw-w-full tw-h-2 tw-bg-teal-50 tw-rounded-full tw-overflow-hidden">
                      <div
                        class="tw-h-full tw-rounded-full tw-bg-teal-500"
                        :style="{ width: Math.min(100, Math.round((item.count / Math.max(largest(geography.top_lgas), 1)) * 100)) + '%', transition: 'width 0.8s ease' }"
                      />
                    </div>
                  </div>
                  <v-icon size="14" color="gray" class="tw-opacity-40 group-hover:tw-opacity-80 tw-shrink-0">mdi-chevron-right</v-icon>
                </div>
                <p v-if="!(geography.top_lgas || []).length" class="tw-py-8 tw-text-center tw-text-gray-400 tw-text-sm">No LGA data available</p>
              </template>

              <!-- Ward view -->
              <template v-else>
                <div
                  v-for="(ward, i) in wardData.slice(0, 10)"
                  :key="ward.ward_id || ward.ward"
                  class="tw-flex tw-items-center tw-gap-3"
                >
                  <span class="tw-text-xs tw-font-bold tw-text-gray-400 tw-w-4 tw-text-right tw-shrink-0">{{ i + 1 }}</span>
                  <div class="tw-flex-1 tw-min-w-0">
                    <div class="tw-flex tw-justify-between tw-mb-1">
                      <span class="tw-text-sm tw-font-medium tw-text-gray-700 tw-truncate">{{ ward.ward }}</span>
                      <span class="tw-text-sm tw-font-bold tw-text-indigo-700 tw-ml-2 tw-shrink-0">{{ number(ward.count) }}</span>
                    </div>
                    <div class="tw-w-full tw-h-2 tw-bg-indigo-50 tw-rounded-full tw-overflow-hidden">
                      <div
                        class="tw-h-full tw-rounded-full tw-bg-indigo-500"
                        :style="{ width: Math.min(100, Math.round((ward.count / Math.max(largest(wardData), 1)) * 100)) + '%', transition: 'width 0.8s ease' }"
                      />
                    </div>
                  </div>
                  <span class="tw-text-xs tw-text-gray-400 tw-shrink-0 tw-w-9 tw-text-right">{{ ward.percentage || 0 }}%</span>
                </div>
                <p v-if="!wardData.length" class="tw-py-8 tw-text-center tw-text-gray-400 tw-text-sm">No ward data for this LGA</p>
              </template>
            </div>
          </div>

          <!-- Provider Load -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-justify-between">
              <div>
                <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Provider Load</h2>
                <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Facilities carrying the highest active covered lives</p>
              </div>
              <div class="tw-flex tw-items-center tw-gap-2 tw-bg-indigo-50 tw-border tw-border-indigo-100 tw-rounded-lg tw-px-3 tw-py-1.5">
                <v-icon size="14" color="indigo">mdi-hospital-building</v-icon>
                <span class="tw-text-xs tw-font-bold tw-text-indigo-700">{{ facilities.summary?.active || 0 }} active</span>
              </div>
            </div>
            <div class="tw-overflow-x-auto">
              <table class="tw-w-full">
                <thead>
                  <tr class="tw-bg-gray-50">
                    <th class="tw-py-3 tw-px-4 tw-text-left tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-500">Facility</th>
                    <th class="tw-py-3 tw-px-3 tw-text-left tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-500">LGA</th>
                    <th class="tw-py-3 tw-px-3 tw-text-right tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-500">Lives</th>
                    <th class="tw-py-3 tw-px-4 tw-text-right tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-500">Load</th>
                  </tr>
                </thead>
                <tbody class="tw-divide-y tw-divide-gray-50">
                  <tr
                    v-for="fac in (facilities.top_by_active_lives || []).slice(0, 8)"
                    :key="fac.hcp_code || fac.name"
                    class="hover:tw-bg-gray-50 tw-transition-colors"
                  >
                    <td class="tw-py-3 tw-px-4">
                      <p class="tw-text-sm tw-font-semibold tw-text-gray-900 tw-leading-tight">{{ fac.name }}</p>
                      <p class="tw-text-xs tw-text-gray-400 tw-font-mono">{{ fac.hcp_code || '—' }}</p>
                    </td>
                    <td class="tw-py-3 tw-px-3 tw-text-sm tw-text-gray-600">{{ fac.lga || '—' }}</td>
                    <td class="tw-py-3 tw-px-3 tw-text-right tw-text-sm tw-font-bold tw-text-gray-900">{{ number(fac.active_lives) }}</td>
                    <td class="tw-py-3 tw-px-4 tw-text-right">
                      <span
                        class="tw-inline-flex tw-items-center tw-rounded-full tw-px-2 tw-py-0.5 tw-text-xs tw-font-semibold"
                        :class="fac.utilization > 90 ? 'tw-bg-red-50 tw-text-red-700' : fac.utilization > 70 ? 'tw-bg-amber-50 tw-text-amber-700' : 'tw-bg-green-50 tw-text-green-700'"
                      >
                        {{ fac.utilization || 0 }}%
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!(facilities.top_by_active_lives || []).length">
                    <td colspan="4" class="tw-py-10 tw-text-center tw-text-gray-400 tw-text-sm">No facility load data yet</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ── Funding + Vulnerable Groups + Financials ──────────────────────── -->
        <section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-3 tw-gap-6">

          <!-- Funding Sources -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100">
              <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Funding Sources</h2>
              <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Government and donor accountability structure</p>
            </div>
            <div class="tw-p-5 tw-space-y-3">
              <BreakdownRow
                v-for="(item, i) in (overview.funding_mix || [])"
                :key="item.label"
                :label="item.label"
                :count="item.count"
                :pct="item.percentage || 0"
                :color="CHART_COLORS[i % CHART_COLORS.length]"
              />
              <p v-if="!(overview.funding_mix || []).length" class="tw-py-8 tw-text-center tw-text-gray-400 tw-text-sm">No funding data yet</p>
            </div>
          </div>

          <!-- Vulnerable Groups -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-justify-between">
              <div>
                <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Vulnerable Groups</h2>
                <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Equity lens for enrollee classification</p>
              </div>
              <span class="tw-text-xs tw-font-bold tw-text-purple-600 tw-bg-purple-50 tw-border tw-border-purple-100 tw-rounded-full tw-px-2 tw-py-0.5">
                {{ number(coverage.active ? vulnerableCovered : 0) }} covered
              </span>
            </div>
            <div class="tw-p-5 tw-space-y-3">
              <BreakdownRow
                v-for="(item, i) in (overview.vulnerable_groups || [])"
                :key="item.label"
                :label="item.label"
                :count="item.count"
                :pct="item.percentage || 0"
                :color="PURPLE_COLORS[i % PURPLE_COLORS.length]"
              />
              <p v-if="!(overview.vulnerable_groups || []).length" class="tw-py-8 tw-text-center tw-text-gray-400 tw-text-sm">No vulnerable group data yet</p>
            </div>
          </div>

          <!-- PIN & Invoice Signals -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100">
              <h2 class="tw-text-base tw-font-bold tw-text-gray-900">PIN & Invoice Signals</h2>
              <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Premium voucher movement and payment tracking</p>
            </div>
            <div class="tw-p-5">
              <div class="tw-grid tw-grid-cols-2 tw-gap-3">
                <FinancialTile label="PINs Generated" :value="number(financials.pin_inventory?.total)" icon="mdi-key-chain" color="blue" />
                <FinancialTile label="PINs Used" :value="number(financials.pin_inventory?.used)" icon="mdi-key-check" color="green" />
                <FinancialTile label="Paid Invoices" :value="number(financials.invoices?.paid)" icon="mdi-receipt-text-check-outline" color="teal" />
                <FinancialTile label="Paid Value" :value="money(financials.invoices?.paid_value)" icon="mdi-cash-check" color="indigo" />
              </div>
              <div v-if="financials.pin_inventory?.total > 0" class="tw-mt-4 tw-pt-4 tw-border-t tw-border-gray-100">
                <div class="tw-flex tw-justify-between tw-text-xs tw-text-gray-500 tw-mb-2">
                  <span>PIN Utilisation</span>
                  <span class="tw-font-semibold">{{ pinUtilPct }}%</span>
                </div>
                <div class="tw-h-2 tw-bg-gray-100 tw-rounded-full tw-overflow-hidden">
                  <div
                    class="tw-h-full tw-rounded-full tw-transition-all tw-duration-700"
                    :class="pinUtilPct > 80 ? 'tw-bg-green-500' : pinUtilPct > 50 ? 'tw-bg-blue-500' : 'tw-bg-gray-400'"
                    :style="{ width: pinUtilPct + '%' }"
                  />
                </div>
                <div class="tw-flex tw-justify-between tw-text-xs tw-text-gray-400 tw-mt-1">
                  <span>{{ number(financials.pin_inventory?.used) }} used</span>
                  <span>{{ number(financials.pin_inventory?.total) }} total</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Benefactor Participation + Capitation Overview ──────────────── -->
        <section class="tw-grid tw-grid-cols-1 xl:tw-grid-cols-3 tw-gap-6">

          <!-- Benefactor Participation -->
          <div class="tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100">
              <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Benefactor Participation</h2>
              <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Sponsored vs self-funded distribution</p>
            </div>
            <div class="tw-p-5 tw-space-y-3">
              <BreakdownRow
                v-for="(item, i) in (overview.benefactor_mix || [])"
                :key="item.label"
                :label="item.label"
                :count="item.count"
                :pct="item.percentage || 0"
                :color="TEAL_COLORS[i % TEAL_COLORS.length]"
              />
              <p v-if="!(overview.benefactor_mix || []).length" class="tw-py-8 tw-text-center tw-text-gray-400 tw-text-sm">No benefactor data yet</p>
            </div>
          </div>

          <!-- Capitation Overview -->
          <div class="xl:tw-col-span-2 tw-bg-white tw-border tw-border-gray-100 tw-shadow-sm tw-overflow-hidden">
            <div class="tw-px-6 tw-py-4 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-justify-between">
              <div>
                <h2 class="tw-text-base tw-font-bold tw-text-gray-900">Capitation Overview</h2>
                <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">Period pipeline, payment status and provider amounts</p>
              </div>
              <router-link to="/capitation/generate">
                <v-chip size="x-small" color="cyan" variant="tonal" class="tw-cursor-pointer">View all</v-chip>
              </router-link>
            </div>

            <!-- Summary stat chips -->
            <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-4 tw-gap-px tw-bg-gray-100 tw-border-b tw-border-gray-100">
              <div class="tw-bg-white tw-px-5 tw-py-4">
                <p class="tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-400">Total Periods</p>
                <p class="tw-text-2xl tw-font-extrabold tw-text-gray-900 tw-mt-1">{{ capitationStats.total }}</p>
              </div>
              <div class="tw-bg-white tw-px-5 tw-py-4">
                <p class="tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-400">Finalised</p>
                <p class="tw-text-2xl tw-font-extrabold tw-text-green-600 tw-mt-1">{{ capitationStats.finalised }}</p>
              </div>
              <div class="tw-bg-white tw-px-5 tw-py-4">
                <p class="tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-400">In Progress</p>
                <p class="tw-text-2xl tw-font-extrabold tw-text-amber-600 tw-mt-1">{{ capitationStats.inProgress }}</p>
              </div>
              <div class="tw-bg-white tw-px-5 tw-py-4">
                <p class="tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-400">Draft</p>
                <p class="tw-text-2xl tw-font-extrabold tw-text-gray-400 tw-mt-1">{{ capitationStats.draft }}</p>
              </div>
            </div>

            <!-- Recent periods list -->
            <div v-if="capitationLoading" class="tw-flex tw-items-center tw-justify-center tw-py-10">
              <v-progress-circular indeterminate color="cyan" size="28" />
            </div>
            <div v-else-if="capitationPeriods.length" class="tw-divide-y tw-divide-gray-50">
              <div
                v-for="period in capitationPeriods.slice(0, 5)"
                :key="period.id"
                class="tw-flex tw-items-center tw-gap-4 tw-px-6 tw-py-3.5 hover:tw-bg-slate-50 tw-transition-colors"
              >
                <div
                  class="tw-flex tw-h-9 tw-w-9 tw-flex-shrink-0 tw-items-center tw-justify-center tw-rounded-lg"
                  :class="period.status ? 'tw-bg-green-100' : period.computed_at ? 'tw-bg-blue-100' : 'tw-bg-amber-100'"
                >
                  <v-icon size="18" :color="period.status ? 'green' : period.computed_at ? 'blue' : 'amber-darken-2'">
                    {{ period.status ? 'mdi-check-decagram' : period.computed_at ? 'mdi-progress-check' : 'mdi-clock-outline' }}
                  </v-icon>
                </div>
                <div class="tw-flex-1 tw-min-w-0">
                  <p class="tw-text-sm tw-font-semibold tw-text-gray-900 tw-truncate">{{ period.name }}</p>
                  <p class="tw-text-xs tw-text-gray-400 tw-mt-0.5">
                    {{ formatCapDate(period.period_start) }} – {{ formatCapDate(period.period_end) }}
                    <span class="tw-mx-1 tw-text-gray-300">·</span>
                    {{ period.capitation_details_count || 0 }} facilities
                  </p>
                </div>
                <div class="tw-text-right tw-shrink-0">
                  <span
                    class="tw-inline-block tw-rounded-full tw-px-2 tw-py-0.5 tw-text-xs tw-font-semibold"
                    :class="period.status ? 'tw-bg-green-50 tw-text-green-700' : period.computed_at ? 'tw-bg-blue-50 tw-text-blue-700' : 'tw-bg-amber-50 tw-text-amber-700'"
                  >{{ period.status ? 'Finalised' : period.computed_at ? 'Computed' : 'Draft' }}</span>
                  <p v-if="period.capitation_rate" class="tw-text-xs tw-text-gray-400 tw-mt-1 tw-font-mono">
                    ₦{{ Number(period.capitation_rate).toLocaleString() }}/head
                  </p>
                </div>
              </div>
            </div>
            <div v-else class="tw-py-10 tw-text-center tw-text-gray-400 tw-text-sm">
              No capitation periods found
            </div>
          </div>
        </section>

      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref, resolveComponent } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import LineChart from '../charts/LineChart.vue'
import BarChart from '../charts/BarChart.vue'
import DoughnutChart from '../charts/DoughnutChart.vue'
import { capitationAPI, dashboardAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error, success } = useToast()

const loading    = ref(false)
const overview   = ref({})

// ── Computed accessors ──────────────────────────────────────────────────────
const currentMonth    = computed(() => new Date().toLocaleDateString(undefined, { month: 'long', year: 'numeric' }))
const executiveCards  = computed(() => overview.value.executive_summary || [])
const performance     = computed(() => overview.value.performance || {})
const coverage        = computed(() => overview.value.coverage || {})
const geography       = computed(() => overview.value.geography || {})
const facilities      = computed(() => overview.value.facilities || {})
const financials      = computed(() => overview.value.financials || {})
const pipeline        = computed(() => overview.value.pipeline || {})
const totalEnrollees  = computed(() => Number(executiveCards.value[0]?.value || 0))
const vulnerableCovered = computed(() => Number(executiveCards.value[3]?.value || 0))

// ── Header pills (performance rates — not duplicating the count KPI cards) ──
const headerPills = computed(() => [
  { label: 'Coverage Rate',      value: (performance.value.coverage_rate          || 0) + '%', sub: 'of enrolled lives active' },
  { label: 'Approval Rate',      value: (performance.value.approval_rate          || 0) + '%', sub: 'processed for coverage' },
  { label: 'Geographic Reach',   value: (performance.value.geographic_reach_rate  || 0) + '%', sub: 'of LGAs have enrollees' },
  { label: 'Facility Active',    value: (performance.value.active_facility_rate   || 0) + '%', sub: 'of providers accredited' },
])

// ── Chart colours ───────────────────────────────────────────────────────────
const CHART_COLORS  = ['#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6', '#f97316', '#6366f1']
const PURPLE_COLORS = ['#8b5cf6', '#a78bfa', '#7c3aed', '#c4b5fd', '#6d28d9']
const TEAL_COLORS   = ['#14b8a6', '#0d9488', '#5eead4', '#0f766e', '#2dd4bf']

// ── Programme Mix ───────────────────────────────────────────────────────────
const programmeMixData = computed(() => {
  const items = overview.value.programme_mix || []
  if (!items.length) return { labels: [], datasets: [] }
  return {
    labels: items.map(i => i.label),
    datasets: [{ data: items.map(i => i.count), backgroundColor: CHART_COLORS, borderWidth: 0 }],
  }
})

// ── Performance meters ──────────────────────────────────────────────────────
const performanceMeters = computed(() => [
  { label: 'Coverage Rate',  pct: performance.value.coverage_rate  || 0, sub: 'of enrolled lives',   stroke: '#22c55e' },
  { label: 'Approval Rate',  pct: performance.value.approval_rate  || 0, sub: 'processed so far',    stroke: '#3b82f6' },
  { label: 'Geo Reach',      pct: performance.value.geographic_reach_rate || 0, sub: 'of LGAs reached', stroke: '#14b8a6' },
])

// ── PIN utilisation ─────────────────────────────────────────────────────────
const pinUtilPct = computed(() => {
  const inv = financials.value.pin_inventory
  if (!inv?.total) return 0
  return Math.round((inv.used / inv.total) * 100)
})

// ── Enrollment Trend: interactive year → month ──────────────────────────────
const trendDrillLevel  = ref('year')
const trendSelectedYear = ref(null)
const trendYearData    = ref([])
const trendMonthlyData = ref([])
const trendLoading     = ref(false)

const trendYearChartData = computed(() => {
  if (!trendYearData.value.length) return { labels: [], datasets: [] }
  return {
    labels: trendYearData.value.map(d => d.label || String(d.year)),
    datasets: [{
      label: 'Enrollments',
      data: trendYearData.value.map(d => d.count || 0),
      backgroundColor: trendYearData.value.map((_, i) =>
        i === trendYearData.value.length - 1 ? '#3b82f6' : 'rgba(59,130,246,0.55)'
      ),
      hoverBackgroundColor: '#1d4ed8',
      borderRadius: 8,
    }],
  }
})

const trendMonthChartData = computed(() => {
  if (!trendMonthlyData.value.length) return { labels: [], datasets: [] }
  return {
    labels: trendMonthlyData.value.map(d => d.label),
    datasets: [{
      label: `Enrollments ${trendSelectedYear.value}`,
      data: trendMonthlyData.value.map(d => d.count || 0),
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59,130,246,0.08)',
      fill: true,
      tension: 0.4,
    }],
  }
})

// Stable click handler — reads reactive state via .value, no stale closure.
function handleTrendYearClick(event, elements) {
  if (!elements.length || trendDrillLevel.value !== 'year') return
  const idx  = elements[0].index
  const year = trendYearData.value[idx]?.year
  if (year) drillToYear(year)
}

const trendYearOptions = {
  onClick: handleTrendYearClick,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: ctx => ` ${ctx.parsed.y.toLocaleString()} enrollments`,
        footer: () => 'Click to see monthly breakdown',
      },
    },
  },
  scales: {
    x: { grid: { display: false } },
    y: { grid: { color: 'rgba(107,114,128,0.08)' } },
  },
  elements: { bar: { borderRadius: 8, borderSkipped: false } },
}

async function loadTrendYears() {
  try {
    const resp = await dashboardAPI.getEnrollmentTrend()
    trendYearData.value = resp.data?.data || []
  } catch (_) {
    // non-critical
  }
}

async function drillToYear(year) {
  trendLoading.value   = true
  trendSelectedYear.value = year
  trendDrillLevel.value   = 'month'
  try {
    const resp = await dashboardAPI.getEnrollmentTrend({ year })
    trendMonthlyData.value = resp.data?.data || []
  } catch (_) {
    trendMonthlyData.value = []
  } finally {
    trendLoading.value = false
  }
}

function backToYears() {
  trendDrillLevel.value   = 'year'
  trendSelectedYear.value = null
  trendMonthlyData.value  = []
}

// ── Capitation stats ─────────────────────────────────────────────────────────
const capitationPeriods = ref([])
const capitationLoading = ref(false)

const capitationStats = computed(() => {
  const periods = capitationPeriods.value
  return {
    total:      periods.length,
    finalised:  periods.filter(p => p.status).length,
    inProgress: periods.filter(p => !p.status && p.computed_at).length,
    draft:      periods.filter(p => !p.status && !p.computed_at).length,
  }
})

const formatCapDate = (value) => value ? new Date(value).toLocaleDateString(undefined, { month: 'short', year: 'numeric' }) : '—'

async function loadCapitationStats() {
  capitationLoading.value = true
  try {
    const response = await capitationAPI.periods({ per_page: 20, sort: 'desc' })
    const payload = response.data?.data
    capitationPeriods.value = (payload?.data || payload || []).sort((a, b) => b.id - a.id)
  } catch (_) {
    // non-critical
  } finally {
    capitationLoading.value = false
  }
}

// ── Geographic Reach: interactive LGA → wards ────────────────────────────────
const geoView     = ref('lga')
const selectedLga = ref(null)
const wardData    = ref([])
const wardLoading = ref(false)

async function drillToLga(item) {
  if (!item.lga_id) return
  wardLoading.value = true
  geoView.value     = 'ward'
  selectedLga.value = { lga_id: item.lga_id, lga_name: item.lga }
  try {
    const resp = await dashboardAPI.getWardsByLga(item.lga_id)
    wardData.value = resp.data?.data?.wards || []
  } catch (_) {
    wardData.value = []
  } finally {
    wardLoading.value = false
  }
}

function backToLgas() {
  geoView.value     = 'lga'
  selectedLga.value = null
  wardData.value    = []
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function number(value) {
  return Number(value || 0).toLocaleString()
}
function money(value) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(value || 0))
}
function formatValue(value) {
  return typeof value === 'string' ? value : number(value)
}
function largest(items = []) {
  return Math.max(...items.map(i => Number(i.count || i.enrollees || 0)), 1)
}
function statusDot(label) {
  const l = label.toLowerCase()
  if (l.includes('active'))  return 'tw-bg-green-500'
  if (l.includes('pending')) return 'tw-bg-amber-400'
  if (l.includes('reject'))  return 'tw-bg-red-500'
  if (l.includes('suspend')) return 'tw-bg-orange-400'
  return 'tw-bg-gray-400'
}
function statusBar(label) {
  const l = label.toLowerCase()
  if (l.includes('active'))  return 'tw-bg-green-500'
  if (l.includes('pending')) return 'tw-bg-amber-400'
  if (l.includes('reject'))  return 'tw-bg-red-500'
  if (l.includes('suspend')) return 'tw-bg-orange-400'
  return 'tw-bg-gray-400'
}

// ── API ─────────────────────────────────────────────────────────────────────
async function loadDashboard() {
  loading.value = true
  try {
    const response = await dashboardAPI.getOverview()
    overview.value = response.data?.data || {}
    success('Dashboard refreshed')
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load dashboard')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadDashboard()
  loadTrendYears()
  loadCapitationStats()
})

// ── Sub-components ──────────────────────────────────────────────────────────

const KpiCard = defineComponent({
  name: 'KpiCard',
  props: {
    label:  String,
    value:  [String, Number],
    helper: String,
    icon:   { type: String, default: 'mdi-chart-bar' },
    tone:   { type: String, default: 'primary' },
  },
  setup(props) {
    const VIcon = resolveComponent('v-icon')
    const toneMap = {
      primary: { bg: 'tw-bg-blue-50',   text: 'tw-text-blue-700',   border: 'tw-border-blue-100' },
      success: { bg: 'tw-bg-green-50',  text: 'tw-text-green-700',  border: 'tw-border-green-100' },
      warning: { bg: 'tw-bg-amber-50',  text: 'tw-text-amber-700',  border: 'tw-border-amber-100' },
      info:    { bg: 'tw-bg-cyan-50',   text: 'tw-text-cyan-700',   border: 'tw-border-cyan-100' },
      indigo:  { bg: 'tw-bg-indigo-50', text: 'tw-text-indigo-700', border: 'tw-border-indigo-100' },
      teal:    { bg: 'tw-bg-teal-50',   text: 'tw-text-teal-700',   border: 'tw-border-teal-100' },
    }
    return () => {
      const t = toneMap[props.tone] ?? toneMap.primary
      return h('div', {
        class: `tw-bg-white tw-border ${t.border} tw-p-3.5 tw-shadow-sm hover:tw-shadow-md tw-transition-all tw-duration-200`,
      }, [
        h('div', { class: 'tw-flex tw-items-center tw-justify-between tw-gap-2 tw-mb-2' }, [
          h('p', { class: 'tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-gray-500' }, props.label),
          h('div', { class: `tw-w-6 tw-h-6 tw-rounded-md ${t.bg} tw-flex tw-items-center tw-justify-center` }, [
            h(VIcon, { size: 14, class: t.text }, { default: () => props.icon }),
          ]),
        ]),
        h('p', { class: 'tw-text-xl tw-font-extrabold tw-text-gray-950 tw-leading-tight' }, props.value),
        h('p', { class: 'tw-text-[10px] tw-text-gray-400 tw-mt-1 tw-leading-snug' }, props.helper),
      ])
    }
  },
})

const ProgressBar = defineComponent({
  name: 'ProgressBar',
  props: {
    label: String,
    value: { type: [String, Number], default: 0 },
    max:   { type: [String, Number], default: 1 },
    color: { type: String, default: '#3b82f6' },
  },
  setup(props) {
    return () => {
      const val = Number(props.value || 0)
      const max = Math.max(Number(props.max || 1), 1)
      const pct = Math.min(100, Math.round((val / max) * 1000) / 10)
      return h('div', {}, [
        h('div', { class: 'tw-flex tw-items-center tw-justify-between tw-mb-1.5' }, [
          h('span', { class: 'tw-text-sm tw-font-medium tw-text-gray-700' }, props.label),
          h('div', { class: 'tw-flex tw-items-center tw-gap-2' }, [
            h('span', { class: 'tw-text-sm tw-font-extrabold tw-text-gray-950' }, val.toLocaleString()),
            h('span', { class: 'tw-text-xs tw-text-gray-400' }, `(${pct}%)`),
          ]),
        ]),
        h('div', { class: 'tw-w-full tw-h-2.5 tw-bg-gray-100 tw-rounded-full tw-overflow-hidden' }, [
          h('div', {
            style: { width: `${pct}%`, backgroundColor: props.color, transition: 'width 0.8s ease' },
            class: 'tw-h-full tw-rounded-full',
          }),
        ]),
      ])
    }
  },
})

const BreakdownRow = defineComponent({
  name: 'BreakdownRow',
  props: {
    label: String,
    count: { type: Number, default: 0 },
    pct:   { type: Number, default: 0 },
    color: { type: String, default: '#3b82f6' },
  },
  setup(props) {
    return () => h('div', { class: 'tw-flex tw-items-center tw-gap-3' }, [
      h('div', { class: 'tw-w-2 tw-h-2 tw-rounded-full tw-shrink-0', style: { backgroundColor: props.color } }),
      h('span', { class: 'tw-text-sm tw-font-medium tw-text-gray-700 tw-flex-1 tw-min-w-0 tw-truncate' }, props.label),
      h('span', { class: 'tw-text-sm tw-font-bold tw-text-gray-900' }, Number(props.count).toLocaleString()),
      h('span', { class: 'tw-text-xs tw-text-gray-400 tw-w-9 tw-text-right tw-shrink-0' }, `${props.pct}%`),
    ])
  },
})

const FinancialTile = defineComponent({
  name: 'FinancialTile',
  props: {
    label: String,
    value: [String, Number],
    icon:  { type: String, default: 'mdi-cash' },
    color: { type: String, default: 'blue' },
  },
  setup(props) {
    const VIcon = resolveComponent('v-icon')
    const colorMap = {
      blue:   'tw-bg-blue-50 tw-border-blue-100 tw-text-blue-700',
      green:  'tw-bg-green-50 tw-border-green-100 tw-text-green-700',
      teal:   'tw-bg-teal-50 tw-border-teal-100 tw-text-teal-700',
      indigo: 'tw-bg-indigo-50 tw-border-indigo-100 tw-text-indigo-700',
    }
    return () => h('div', {
      class: `tw-border tw-p-3 ${colorMap[props.color] ?? colorMap.blue}`,
    }, [
      h('div', { class: 'tw-flex tw-items-center tw-gap-2 tw-mb-1' }, [
        h(VIcon, { size: 14, class: 'tw-opacity-70' }, { default: () => props.icon }),
        h('p', { class: 'tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-opacity-70' }, props.label),
      ]),
      h('p', { class: 'tw-text-lg tw-font-extrabold' }, props.value ?? '—'),
    ])
  },
})
</script>
