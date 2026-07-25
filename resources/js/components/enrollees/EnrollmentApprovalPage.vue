<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Enrollment Approval"
        subtitle="Work through pending enrollment approvals in a focused queue, then open any record in a review modal to verify NIN and complete an auditable approval."
        kicker="Enrollment"
        icon="mdi-account-check-outline"
      >
        <v-select
          v-model="limit"
          :items="[25, 50, 75, 100]"
          label="Batch size"
          density="compact"
          variant="outlined"
          hide-details
          class="tw-w-32"
        />
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadBatch">
          Refresh Queue
        </v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-4 md:tw-grid-cols-2 xl:tw-grid-cols-4">
        <AppMetricCard
          title="Loaded queue"
          icon="mdi-format-list-bulleted-square"
          tone="neutral"
          :value="rows.length"
          helper="Pending records loaded into the current approval batch"
        />
        <AppMetricCard
          title="Ready to approve"
          icon="mdi-check-decagram-outline"
          tone="success"
          :value="readyCount"
          helper="Records with no NIN-verification or duplicate blockers"
        />
        <AppMetricCard
          title="Approved this batch"
          icon="mdi-badge-account-horizontal-outline"
          tone="info"
          :value="approvedCount"
          helper="Approvals completed during the current working session"
        />
        <AppMetricCard
          title="Needs attention"
          icon="mdi-alert-decagram-outline"
          tone="warning"
          :value="attentionCount"
          helper="Records that still need verification, payment review, or duplicate resolution"
        />
      </div>

      <AppCard title="Approval Filters" icon="mdi-filter-outline" tone="primary">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-5">
          <v-select v-model="filters.programme_id" :items="metadata.insurance_programmes" item-title="name" item-value="id" label="Programme" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.facility_id" :items="metadata.facilities" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Phase" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding" density="compact" variant="outlined" clearable />
        </div>
      </AppCard>

      <AppCard title="Pending Approval Queue" icon="mdi-table-account" tone="primary">
        <AppDataTable
          v-model:search="search"
          :headers="headers"
          :items="filteredRows"
          :items-length="filteredRows.length"
          :loading="loading"
          searchable
          search-placeholder="Search by enrollee, NIN, phone, facility, programme, or benefactor"
          :items-per-page="10"
          :per-page-options="[10, 25, 50, 100]"
          item-value="id"
          class="tw-mt-4"
        >
          <template #toolbar>
            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-text-xs tw-text-slate-500">
              <span class="tw-rounded-full tw-bg-slate-200 tw-px-2.5 tw-py-1 tw-font-semibold tw-text-slate-700">
                {{ filteredRows.length }} matching record{{ filteredRows.length === 1 ? '' : 's' }}
              </span>
              <span>Open any row to review full enrollment details, verify NIN, and approve.</span>
            </div>
          </template>

          <template #item.enrollee="{ item }">
            <div class="tw-min-w-0">
              <p class="tw-font-semibold tw-text-slate-900">{{ item.full_name || item.name || `Enrollee #${item.id}` }}</p>
              <p class="tw-text-xs tw-text-slate-500">{{ item.enrollee_id || 'Pending ID assignment' }}</p>
              <p class="tw-mt-1 tw-text-xs tw-text-slate-500">{{ item.phone || 'No phone provided' }}</p>
            </div>
          </template>

          <template #item.coverage="{ item }">
            <div class="tw-min-w-0">
              <p class="tw-font-medium tw-text-slate-900">{{ item.insurance_programme?.name || 'No programme' }}</p>
              <p class="tw-text-xs tw-text-slate-500">{{ item.premium_plan?.name || 'No premium plan' }}</p>
              <p class="tw-mt-1 tw-text-xs tw-text-slate-500">{{ item.benefit_package?.name || 'No benefit package' }}</p>
            </div>
          </template>

          <template #item.provider="{ item }">
            <div class="tw-min-w-0">
              <p class="tw-font-medium tw-text-slate-900">{{ item.facility?.name || 'No facility' }}</p>
              <p class="tw-text-xs tw-text-slate-500">{{ item.funding_type?.name || 'No funding type' }}</p>
              <p class="tw-mt-1 tw-text-xs tw-text-slate-500">{{ item.benefactor?.name || 'No benefactor' }}</p>
            </div>
          </template>

          <template #item.verification="{ item }">
            <div class="tw-flex tw-flex-col tw-gap-1.5">
              <AppStatusBadge
                :status="item.local_status === 'approved' ? 'approved' : item.status_label || 'pending'"
                :label="item.local_status === 'approved' ? 'Approved' : item.status_label || 'Pending'"
                size="sm"
              />
              <AppStatusBadge :status="item.nin_verification_status" :label="ninStatusLabel(item.nin_verification_status)" size="sm" />
            </div>
          </template>

          <template #item.flags="{ item }">
            <div class="tw-flex tw-flex-col tw-gap-1.5">
              <span v-if="item.is_possible_duplicate" class="tw-text-xs tw-font-medium tw-text-amber-700">Duplicate review required</span>
              <span v-else class="tw-text-xs tw-text-slate-500">Duplicate clear</span>
              <span v-if="requiresVerification(item)" class="tw-text-xs tw-font-medium tw-text-rose-700">NIN verification pending</span>
              <span v-else-if="item.nin" class="tw-text-xs tw-text-emerald-700">NIN ready</span>
              <span v-else class="tw-text-xs tw-text-slate-500">No NIN provided</span>
              <span v-if="item.premium_plan?.payment_required" class="tw-text-xs tw-text-slate-500">Payment required plan</span>
            </div>
          </template>

          <template #item.created_at="{ item }">
            <div class="tw-text-sm tw-text-slate-600">
              <DateDisplay :value="item.enrollment_date || item.created_at" format="medium" />
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="tw-flex tw-flex-wrap tw-justify-end tw-gap-2">
              <v-btn
                variant="text"
                color="primary"
                size="small"
                prepend-icon="mdi-eye-outline"
                @click="openDetails(item)"
              >
                View
              </v-btn>
              <v-btn
                v-if="item.nin"
                variant="text"
                color="primary"
                size="small"
                :loading="verifyingId === item.id"
                prepend-icon="mdi-card-account-details-outline"
                @click="verifyNin(item, true)"
              >
                Verify NIN
              </v-btn>
              <v-btn
                color="primary"
                size="small"
                prepend-icon="mdi-check-decagram-outline"
                :loading="approvingId === item.id"
                :disabled="cannotApprove(item)"
                @click="openApproveDialog(item)"
              >
                Approve
              </v-btn>
            </div>
          </template>

          <template #no-data>
            <AppEmptyState
              icon="mdi-account-clock-outline"
              title="No pending enrollees"
              description="No pending approval records matched the current batch filters."
            >
              <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadBatch">
                Reload queue
              </v-btn>
            </AppEmptyState>
          </template>
        </AppDataTable>
      </AppCard>

      <AppModal
        :model-value="detailModalOpen"
        title="Enrollment Review"
        subtitle="Review the enrollee profile, compare verified NIN data, and complete the approval decision from one place."
        icon="mdi-account-details-outline"
        size="2xl"
        color="primary"
        @update:modelValue="handleDetailModal"
      >
        <template v-if="selectedRow">
          <div class="tw-space-y-4">
            <div class="tw-flex tw-flex-col tw-gap-2 lg:tw-flex-row lg:tw-items-start lg:tw-justify-between">
              <div>
                <h3 class="tw-text-xl tw-font-semibold tw-text-slate-950">{{ selectedRow.full_name || selectedRow.name || `Enrollee #${selectedRow.id}` }}</h3>
                <p class="tw-mt-0.5 tw-text-sm tw-text-slate-500">{{ selectedRow.enrollee_id || 'Pending ID assignment' }}</p>
                <div class="tw-mt-2 tw-flex tw-flex-wrap tw-gap-1.5">
                  <AppStatusBadge
                    :status="selectedRow.local_status === 'approved' ? 'approved' : selectedRow.status_label || 'pending'"
                    :label="selectedRow.local_status === 'approved' ? 'Approved' : selectedRow.status_label || 'Pending'"
                    size="sm"
                  />
                  <AppStatusBadge :status="selectedRow.nin_verification_status" :label="ninStatusLabel(selectedRow.nin_verification_status)" size="sm" />
                  <AppBadge
                    :tone="cannotApprove(selectedRow) ? 'warning' : 'success'"
                    :icon="cannotApprove(selectedRow) ? 'mdi-alert-outline' : 'mdi-check-decagram-outline'"
                    :label="cannotApprove(selectedRow) ? 'Action needed before approval' : 'Ready to approve'"
                    size="sm"
                  />
                </div>
              </div>

              <div class="tw-flex tw-flex-wrap tw-gap-2">
                <v-btn
                  v-if="selectedRow.nin"
                  variant="outlined"
                  color="primary"
                  prepend-icon="mdi-card-account-details-outline"
                  :loading="verifyingId === selectedRow.id"
                  @click="verifyNin(selectedRow, false)"
                >
                  {{ selectedRow.nin_verification_status === 'verified' ? 'Re-verify NIN' : 'Verify NIN' }}
                </v-btn>
                <v-btn
                  color="primary"
                  prepend-icon="mdi-check-decagram-outline"
                  :loading="approvingId === selectedRow.id"
                  :disabled="cannotApprove(selectedRow)"
                  @click="openApproveDialog(selectedRow)"
                >
                  Approve enrollee
                </v-btn>
              </div>
            </div>

            <AppAlert
              v-if="selectedRow.local_error"
              tone="danger"
              title="Action failed"
              :message="selectedRow.local_error"
            />

            <!-- Smart approval-readiness checklist: tells the officer exactly what is blocking (or clearing) approval -->
            <div>
              <p class="tw-mb-1.5 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Approval Readiness</p>
              <div class="tw-grid tw-gap-1.5 sm:tw-grid-cols-3">
                <div
                  v-for="check in approvalChecks(selectedRow)"
                  :key="check.label"
                  class="tw-flex tw-items-start tw-gap-2.5 tw-border tw-border-slate-200 tw-bg-white tw-p-2.5"
                >
                  <span class="qds-icon-shell-sm" :class="check.ok ? 'qds-tone-success' : (check.blocking ? 'qds-tone-danger' : 'qds-tone-warning')">
                    <v-icon size="15">{{ check.icon }}</v-icon>
                  </span>
                  <div class="tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ check.label }}</p>
                    <p class="tw-mt-0.5 tw-text-xs tw-text-slate-500">{{ check.note }}</p>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <p class="tw-mb-1.5 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Enrollment Details</p>
              <div class="tw-grid tw-gap-2 md:tw-grid-cols-2 xl:tw-grid-cols-4">
                <Info label="Phone" :value="selectedRow.phone || 'N/A'" />
                <Info label="NIN" :value="selectedRow.nin || 'Not provided'" />
                <Info label="Submitted">
                  <DateDisplay :value="selectedRow.enrollment_date || selectedRow.created_at" format="medium" />
                </Info>
                <Info label="Enrollment phase" :value="selectedRow.enrollment_phase?.name || 'N/A'" />
                <Info label="Programme" :value="selectedRow.insurance_programme?.name || 'N/A'" />
                <Info label="Premium plan" :value="selectedRow.premium_plan?.name || 'N/A'" />
                <Info label="Benefit package" :value="selectedRow.benefit_package?.name || 'N/A'" />
                <Info label="Funding type" :value="selectedRow.funding_type?.name || 'N/A'" />
                <Info label="Facility" :value="selectedRow.facility?.name || 'N/A'" />
                <Info label="Benefactor" :value="selectedRow.benefactor?.name || 'N/A'" />
              </div>
            </div>

            <AppCard title="NIN Comparison" icon="mdi-compare-horizontal" tone="primary">
              <template #actions>
                <AppBadge
                  v-if="selectedRow.comparison.length"
                  :tone="comparisonSummary(selectedRow).mismatched ? 'warning' : 'success'"
                  :icon="comparisonSummary(selectedRow).mismatched ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline'"
                  :label="`${comparisonSummary(selectedRow).matched} of ${comparisonSummary(selectedRow).total} fields match`"
                  size="sm"
                />
                <v-btn
                  v-if="selectedRow.mergeStrategy === 'manual' && comparisonSummary(selectedRow).mismatched"
                  variant="text"
                  color="primary"
                  size="small"
                  prepend-icon="mdi-auto-fix"
                  @click="applySuggestedDecisions(selectedRow)"
                >
                  Apply suggested choices
                </v-btn>
                <v-select
                  v-model="selectedRow.mergeStrategy"
                  :items="mergeStrategies"
                  item-title="label"
                  item-value="value"
                  label="Merge strategy"
                  density="compact"
                  variant="outlined"
                  hide-details
                  class="tw-w-full md:tw-w-64"
                />
              </template>

              <div v-if="selectedRow.provided_image_url || selectedRow.image_url || selectedRow.providerPhoto" class="tw-mb-4 tw-grid tw-gap-3 md:tw-grid-cols-2">
                <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                  <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Provided enrollment photo</p>
                  <div class="tw-mt-3 tw-flex tw-justify-center">
                    <div class="tw-flex tw-h-44 tw-w-44 tw-items-center tw-justify-center tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white">
                      <img v-if="selectedRow.provided_image_url || selectedRow.image_url" :src="selectedRow.provided_image_url || selectedRow.image_url" alt="Enrollment passport photo" class="tw-h-full tw-w-full tw-object-cover" />
                      <div v-else class="tw-flex tw-flex-col tw-items-center tw-gap-2 tw-text-slate-400">
                        <v-icon size="34">mdi-account-box-outline</v-icon>
                        <span class="tw-text-xs tw-font-medium">No uploaded photo</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                  <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Verified NIN photo</p>
                  <div class="tw-mt-3 tw-flex tw-justify-center">
                    <div class="tw-flex tw-h-44 tw-w-44 tw-items-center tw-justify-center tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white">
                      <img v-if="selectedRow.providerPhoto" :src="selectedRow.providerPhoto" alt="Verified NIN profile photo" class="tw-h-full tw-w-full tw-object-cover" />
                      <div v-else class="tw-flex tw-flex-col tw-items-center tw-gap-2 tw-text-slate-400">
                        <v-icon size="34">mdi-card-account-details-outline</v-icon>
                        <span class="tw-text-xs tw-font-medium">No provider photo returned</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="selectedRow.comparison.length" class="tw-space-y-3">
                <p class="tw-text-sm tw-text-slate-500">
                  Verified by {{ selectedRow.nin_verification_provider || 'configured provider' }}
                  <span v-if="selectedRow.nin_verified_at">on <DateDisplay :value="selectedRow.nin_verified_at" format="medium" /></span>
                </p>

                <div class="tw-grid tw-gap-3">
                  <div class="tw-overflow-x-auto">
                    <table class="tw-min-w-full tw-text-sm">
                      <thead>
                        <tr class="tw-border-b tw-border-slate-200">
                          <th class="tw-px-2.5 tw-py-1.5 tw-text-left tw-font-semibold tw-text-slate-700">Field</th>
                          <th class="tw-px-2.5 tw-py-1.5 tw-text-left tw-font-semibold tw-text-slate-700">Provided data</th>
                          <th class="tw-px-2.5 tw-py-1.5 tw-text-left tw-font-semibold tw-text-slate-700">Verified data</th>
                          <th class="tw-px-2.5 tw-py-1.5 tw-text-left tw-font-semibold tw-text-slate-700">Decision</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="field in selectedRow.comparison"
                          :key="`${selectedRow.id}-${field.field}`"
                          class="tw-border-b tw-border-slate-100"
                          :class="field.matches ? '' : 'tw-bg-amber-50/70'"
                        >
                          <td class="tw-px-2.5 tw-py-2 tw-font-medium tw-text-slate-900">
                            <div class="tw-flex tw-items-center tw-gap-2">
                              <v-icon :color="field.matches ? 'success' : 'warning'" size="16">
                                {{ field.matches ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline' }}
                              </v-icon>
                              {{ field.label }}
                            </div>
                          </td>
                          <td class="tw-px-2.5 tw-py-2 tw-text-slate-600">{{ field.provided || 'N/A' }}</td>
                          <td class="tw-px-2.5 tw-py-2 tw-text-slate-600">{{ field.verified || 'N/A' }}</td>
                          <td class="tw-px-2.5 tw-py-2">
                            <template v-if="selectedRow.mergeStrategy === 'manual'">
                              <v-select
                                v-model="selectedRow.fieldSelection[field.field]"
                                :items="decisionOptions"
                                item-title="label"
                                item-value="value"
                                density="compact"
                                variant="outlined"
                                hide-details
                                class="tw-min-w-40"
                              />
                              <p v-if="!field.matches" class="tw-mt-1 tw-text-[11px] tw-text-slate-500">
                                Suggested: {{ field.recommended_source === 'verified' ? 'Use verified data' : 'Keep provided data' }}
                              </p>
                            </template>
                            <template v-else>
                              <AppStatusBadge
                                :status="resolvedDecision(selectedRow, field.field)"
                                :label="resolvedDecisionLabel(selectedRow, field.field)"
                                size="sm"
                              />
                            </template>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <AppEmptyState
                v-else-if="selectedRow.nin"
                icon="mdi-card-search-outline"
                title="No verification comparison yet"
                description="Verify the enrollee's NIN to load provider data and compare it against the submitted enrollment data."
              />

              <AppEmptyState
                v-else
                icon="mdi-card-account-details-outline"
                title="No NIN provided"
                description="Approval can continue without NIN verification, but the enrollee will be marked as not provided in the verification status."
              />
            </AppCard>

            <AppCard title="Enrollment Location" icon="mdi-map-marker-radius-outline" tone="primary">
              <div v-if="selectedRow.enrollment_location?.capture_location || selectedRow.enrollment_location?.submit_location" class="tw-grid tw-gap-3 md:tw-grid-cols-2">
                <div class="tw-space-y-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                  <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Start Capture</p>
                  <p class="tw-text-sm tw-font-medium tw-text-slate-900">{{ formatLocationPoint(selectedRow.enrollment_location?.capture_location) }}</p>
                  <p class="tw-text-xs tw-text-slate-500">
                    Estimated GPS accuracy: {{ formatAccuracy(selectedRow.enrollment_location?.capture_location?.accuracy_meters) }}
                  </p>
                  <p class="tw-text-xs tw-text-slate-400">
                    {{ formatAccuracyHint(selectedRow.enrollment_location?.capture_location?.accuracy_meters) }}
                  </p>
                  <p class="tw-text-xs tw-text-slate-500">
                    Time:
                    <DateDisplay v-if="selectedRow.enrollment_location?.capture_location?.recorded_at" :value="selectedRow.enrollment_location.capture_location.recorded_at" format="medium" />
                    <span v-else>N/A</span>
                  </p>
                  <v-btn
                    v-if="selectedRow.enrollment_location?.capture_location?.google_maps_url"
                    variant="text"
                    color="primary"
                    size="small"
                    prepend-icon="mdi-map-search-outline"
                    class="tw-self-start"
                    @click="openLocationMap(selectedRow.enrollment_location?.capture_location, 'Start Capture Map')"
                  >
                    Open in map
                  </v-btn>
                </div>

                <div class="tw-space-y-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                  <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Submit Capture</p>
                  <p class="tw-text-sm tw-font-medium tw-text-slate-900">{{ formatLocationPoint(selectedRow.enrollment_location?.submit_location) }}</p>
                  <p class="tw-text-xs tw-text-slate-500">
                    Estimated GPS accuracy: {{ formatAccuracy(selectedRow.enrollment_location?.submit_location?.accuracy_meters) }}
                  </p>
                  <p class="tw-text-xs tw-text-slate-400">
                    {{ formatAccuracyHint(selectedRow.enrollment_location?.submit_location?.accuracy_meters) }}
                  </p>
                  <p class="tw-text-xs tw-text-slate-500">
                    Time:
                    <DateDisplay v-if="selectedRow.enrollment_location?.submit_location?.recorded_at" :value="selectedRow.enrollment_location.submit_location.recorded_at" format="medium" />
                    <span v-else>N/A</span>
                  </p>
                  <v-btn
                    v-if="selectedRow.enrollment_location?.submit_location?.google_maps_url"
                    variant="text"
                    color="primary"
                    size="small"
                    prepend-icon="mdi-map-search-outline"
                    class="tw-self-start"
                    @click="openLocationMap(selectedRow.enrollment_location?.submit_location, 'Submit Capture Map')"
                  >
                    Open in map
                  </v-btn>
                </div>
              </div>

              <AppEmptyState
                v-else
                icon="mdi-map-marker-off-outline"
                title="No enrollment location captured"
                :description="selectedRow.enrollment_location?.error || 'This mobile enrollment record did not include device location data.'"
              />
            </AppCard>
          </div>
        </template>

        <template #actions>
          <v-btn variant="text" @click="detailModalOpen = false">Close</v-btn>
        </template>
      </AppModal>

      <AppConfirmDialog
        :model-value="approvalDialogOpen"
        title="Approve enrollee"
        :message="approvalDialogMessage"
        :warning="approvalDialogWarning"
        confirm-text="Approve enrollee"
        @update:modelValue="approvalDialogOpen = $event"
        @cancel="closeApproveDialog"
        @confirm="confirmApprove"
      />

      <AppModal
        :model-value="locationMapOpen"
        :title="locationMapTitle"
        subtitle="Review the captured coordinates without leaving the approval workflow."
        icon="mdi-map-marker-radius-outline"
        size="xl"
        color="primary"
        @update:modelValue="locationMapOpen = $event"
      >
        <div class="tw-space-y-4">
          <div class="tw-grid tw-gap-2 md:tw-grid-cols-3">
            <Info label="Coordinates" :value="formatLocationPoint(locationMapPoint)" />
            <Info label="Estimated accuracy" :value="formatAccuracy(locationMapPoint?.accuracy_meters)" />
            <Info label="Meaning" :value="formatAccuracyHint(locationMapPoint?.accuracy_meters)" />
          </div>

          <div class="tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50">
            <iframe
              v-if="locationMapEmbedUrl"
              :src="locationMapEmbedUrl"
              title="Enrollment location map"
              class="tw-h-[420px] tw-w-full tw-border-0"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
            />
          </div>
        </div>

        <template #actions>
          <v-btn variant="text" @click="locationMapOpen = false">Close</v-btn>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppConfirmDialog from '../common/AppConfirmDialog.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppMetricCard from '../common/AppMetricCard.vue'
import AppModal from '../common/AppModal.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatusBadge from '../common/AppStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import { enrolleeAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const Info = defineComponent({
  name: 'ApprovalInfo',
  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
  },
  setup(props, { slots }) {
    return () => h('div', { class: 'tw-border tw-border-slate-200 tw-bg-white tw-p-2.5' }, [
      h('p', { class: 'tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500' }, props.label),
      h('div', { class: 'tw-mt-1 tw-text-sm tw-font-medium tw-text-slate-900' }, slots.default ? slots.default() : (props.value || 'N/A')),
    ])
  },
})

const { success, error } = useToast()

const rows = ref([])
const loading = ref(false)
const verifyingId = ref(null)
const approvingId = ref(null)
const limit = ref(50)
const search = ref('')
const detailModalOpen = ref(false)
const selectedRowId = ref(null)
const approvalDialogOpen = ref(false)
const approvalTarget = ref(null)
const locationMapOpen = ref(false)
const locationMapTitle = ref('Enrollment Location Map')
const locationMapPoint = ref(null)

const headers = [
  { title: 'Enrollee', key: 'enrollee', sortable: false, minWidth: 220 },
  { title: 'Coverage', key: 'coverage', sortable: false, minWidth: 220 },
  { title: 'Provider / Funding', key: 'provider', sortable: false, minWidth: 220 },
  { title: 'Verification', key: 'verification', sortable: false, minWidth: 150 },
  { title: 'Flags', key: 'flags', sortable: false, minWidth: 180 },
  { title: 'Submitted', key: 'created_at', sortable: false, minWidth: 150 },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', minWidth: 230 },
]

const metadata = reactive({
  insurance_programmes: [],
  facilities: [],
  benefactors: [],
  enrollment_phases: [],
  funding_types: [],
})

const filters = reactive({
  programme_id: null,
  facility_id: null,
  benefactor_id: null,
  enrollment_phase_id: null,
  funding_type_id: null,
})

const mergeStrategies = [
  { label: 'Keep provided data', value: 'keep_provided' },
  { label: 'Prefer verified NIN data', value: 'prefer_verified' },
  { label: 'Choose field by field', value: 'manual' },
]

const decisionOptions = [
  { label: 'Keep provided', value: 'provided' },
  { label: 'Use verified', value: 'verified' },
]

const approvedCount = computed(() => rows.value.filter((row) => row.local_status === 'approved').length)
const readyCount = computed(() => rows.value.filter((row) => !cannotApprove(row)).length)
const attentionCount = computed(() => rows.value.filter((row) => row.local_status === 'failed' || requiresVerification(row) || row.is_possible_duplicate).length)

const selectedRow = computed(() => rows.value.find((row) => row.id === selectedRowId.value) || null)

const filteredRows = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return rows.value

  return rows.value.filter((row) => {
    const haystack = [
      row.full_name,
      row.name,
      row.enrollee_id,
      row.phone,
      row.nin,
      row.facility?.name,
      row.insurance_programme?.name,
      row.benefactor?.name,
      row.funding_type?.name,
      row.premium_plan?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(query)
  })
})

const approvalDialogMessage = computed(() => {
  if (!approvalTarget.value) return 'Approve this enrollee?'
  return `Approve ${approvalTarget.value.full_name || approvalTarget.value.name || 'this enrollee'} and activate coverage using the current approval workflow.`
})

const approvalDialogWarning = computed(() => {
  if (!approvalTarget.value) return ''
  if (!approvalTarget.value.nin) {
    return 'This enrollee does not have a NIN. Approval will continue with a "NIN Not Provided" verification status.'
  }

  if (approvalTarget.value.mergeStrategy === 'manual') {
    return 'Manual merge choices will be recorded in the enrollee verification metadata before approval.'
  }

  return 'The selected NIN merge strategy will be stored in the audit trail and enrollee verification metadata.'
})

const apiItems = (response) => response?.data?.data?.data || response?.data?.data || []

const ninStatusLabel = (status) => ({
  verified: 'Verified',
  failed: 'Verification Failed',
  not_provided: 'NIN Not Provided',
  not_started: 'Not Verified',
}[status] || 'Not Verified')

const normalizeProviderPhoto = (photo) => {
  if (!photo) return ''
  return String(photo).startsWith('data:') ? photo : `data:image/jpeg;base64,${photo}`
}

const defaultFieldSelection = (comparison = []) => {
  return comparison.reduce((carry, field) => {
    carry[field.field] = field.recommended_source || 'provided'
    return carry
  }, {})
}

const formatLocationPoint = (point) => {
  if (!point?.latitude || !point?.longitude) return 'Not captured'
  return `${Number(point.latitude).toFixed(6)}, ${Number(point.longitude).toFixed(6)}`
}

const formatAccuracy = (accuracy) => {
  if (!accuracy && accuracy !== 0) return 'N/A'
  return `${Math.round(Number(accuracy))}m`
}

const formatAccuracyHint = (accuracy) => {
  if (!accuracy && accuracy !== 0) return 'No GPS accuracy estimate was recorded.'
  return `This point is estimated to be within about ${Math.round(Number(accuracy))} meters of the enrollee's actual capture position.`
}

const buildMapEmbedUrl = (point) => {
  if (!point?.latitude || !point?.longitude) return ''
  return `https://maps.google.com/maps?q=${encodeURIComponent(`${point.latitude},${point.longitude}`)}&z=15&output=embed`
}

const locationMapEmbedUrl = computed(() => buildMapEmbedUrl(locationMapPoint.value))

const openLocationMap = (point, title = 'Enrollment Location Map') => {
  if (!point?.latitude || !point?.longitude) return
  locationMapPoint.value = point
  locationMapTitle.value = title
  locationMapOpen.value = true
}

const normalizeRow = (row) => {
  const comparison = row.nin_verification?.data?.comparison || row.nin_verification_data?.comparison || []
  const providerData = row.nin_verification?.data?.provider_data || row.nin_verification_data?.provider_data || {}
  const storedSelection = row.nin_verification?.meta?.approval_selection?.fields || row.nin_verification_meta?.approval_selection?.fields || {}

  return {
    ...row,
    local_status: row.local_status || 'pending',
    local_error: row.local_error || '',
    mergeStrategy: row.nin ? (comparison.some((field) => !field.matches) ? 'manual' : 'keep_provided') : 'keep_provided',
    fieldSelection: { ...defaultFieldSelection(comparison), ...storedSelection },
    comparison,
    providerData,
    provided_image_url: row.provided_image_url || row.image_url || '',
    providerPhoto: normalizeProviderPhoto(providerData.photo),
  }
}

const loadMetadata = async () => {
  const response = await premiumAPI.metadata()
  Object.assign(metadata, response.data?.data || {})
}

const loadBatch = async () => {
  loading.value = true

  try {
    const params = { ...filters, limit: limit.value, random: true }
    Object.keys(params).forEach((key) => {
      if (params[key] === null || params[key] === '') delete params[key]
    })

    const response = await enrolleeAPI.pendingApproval(params)
    rows.value = apiItems(response).map(normalizeRow)

    if (selectedRowId.value && !rows.value.some((row) => row.id === selectedRowId.value)) {
      detailModalOpen.value = false
      selectedRowId.value = null
    }
  } catch (err) {
    error(err.response?.data?.message || 'Could not load the pending approval batch')
  } finally {
    loading.value = false
  }
}

const requiresVerification = (row) => !!row.nin && row.nin_verification_status !== 'verified'
const cannotApprove = (row) => row.local_status === 'approved' || row.is_possible_duplicate || requiresVerification(row)

// Smart readiness checklist — translates raw flags into plain-language blockers/clearances for the officer
const approvalChecks = (row) => {
  const checks = []

  if (row.nin) {
    const verified = row.nin_verification_status === 'verified'
    checks.push({
      label: 'NIN verification',
      icon: verified ? 'mdi-check-decagram' : 'mdi-card-account-details-outline',
      ok: verified,
      blocking: !verified,
      note: verified
        ? `Verified by ${row.nin_verification_provider || 'configured provider'}.`
        : 'NIN provided but not yet verified — verify before approving.',
    })
  } else {
    checks.push({
      label: 'NIN verification',
      icon: 'mdi-card-off-outline',
      ok: true,
      blocking: false,
      note: 'No NIN provided. Approval can continue and will be marked "Not Provided".',
    })
  }

  checks.push({
    label: 'Duplicate check',
    icon: row.is_possible_duplicate ? 'mdi-content-duplicate' : 'mdi-shield-check-outline',
    ok: !row.is_possible_duplicate,
    blocking: !!row.is_possible_duplicate,
    note: row.is_possible_duplicate
      ? 'Possible duplicate enrollee detected — resolve before approving.'
      : 'No matching enrollee records were found.',
  })

  checks.push({
    label: 'Payment status',
    icon: row.premium_plan?.payment_required ? 'mdi-cash-sync' : 'mdi-cash-check',
    ok: true,
    blocking: false,
    note: row.premium_plan?.payment_required
      ? 'Selected plan requires payment — confirm payment before activation.'
      : 'Selected plan does not require payment.',
  })

  return checks
}

// Summarises how many submitted fields agree with provider data, driving the match badge and the suggestion helper
const comparisonSummary = (row) => {
  const total = row.comparison.length
  const matched = row.comparison.filter((field) => field.matches).length
  return { total, matched, mismatched: total - matched }
}

const applySuggestedDecisions = (row) => {
  row.comparison.forEach((field) => {
    row.fieldSelection[field.field] = field.recommended_source
  })
}

const resolvedDecision = (row, field) => {
  if (row.mergeStrategy === 'manual') {
    return row.fieldSelection[field] || 'provided'
  }

  return row.mergeStrategy === 'prefer_verified' ? 'verified' : 'provided'
}

const resolvedDecisionLabel = (row, field) => {
  return resolvedDecision(row, field) === 'verified' ? 'Use verified data' : 'Keep provided data'
}

const openDetails = (row) => {
  selectedRowId.value = row.id
  detailModalOpen.value = true
}

const handleDetailModal = (value) => {
  detailModalOpen.value = value
  if (!value) {
    selectedRowId.value = null
  }
}

const applyVerificationResponse = (row, response) => {
  const enrollee = response.data?.data?.enrollee || response.data?.data?.data?.enrollee
  const verification = response.data?.data?.verification || response.data?.data?.data?.verification

  Object.assign(row, normalizeRow({
    ...row,
    ...enrollee,
    nin_verification_status: verification?.status || enrollee?.nin_verification_status,
    nin_verification_provider: verification?.provider_name || enrollee?.nin_verification_provider,
    nin_verified_at: verification?.verified_at || enrollee?.nin_verified_at,
    nin_verification_data: {
      provider_data: verification?.provider_data || {},
      comparison: verification?.comparison || [],
    },
  }))
}

const verifyNin = async (row, openModalAfter = false) => {
  verifyingId.value = row.id
  row.local_error = ''

  try {
    const response = await enrolleeAPI.verifyNin(row.id, { consent: true })
    applyVerificationResponse(row, response)

    if (openModalAfter) {
      openDetails(row)
    } else if (selectedRowId.value === row.id) {
      selectedRowId.value = row.id
    }

    success(`NIN verified for ${row.full_name || row.name}`)
  } catch (err) {
    row.local_error = err.response?.data?.message || 'NIN verification failed'
    row.nin_verification_status = 'failed'
    error(row.local_error)
  } finally {
    verifyingId.value = null
  }
}

const openApproveDialog = (row) => {
  approvalTarget.value = row
  approvalDialogOpen.value = true
}

const closeApproveDialog = () => {
  approvalDialogOpen.value = false
  approvalTarget.value = null
}

const approvalPayload = (row) => {
  const payload = {
    nin_merge_strategy: row.mergeStrategy,
  }

  if (row.mergeStrategy === 'manual') {
    payload.nin_field_selection = row.fieldSelection
  }

  return payload
}

const confirmApprove = async () => {
  if (!approvalTarget.value) return

  const row = approvalTarget.value
  approvingId.value = row.id
  row.local_error = ''

  try {
    const response = await enrolleeAPI.approve(row.id, approvalPayload(row))
    Object.assign(row, normalizeRow({
      ...row,
      ...(response.data?.data || {}),
      local_status: 'approved',
    }))
    success(`${row.full_name || row.name} approved`)
    closeApproveDialog()
  } catch (err) {
    row.local_status = 'failed'
    row.local_error = err.response?.data?.message || 'Approval failed'
    error(row.local_error)
  } finally {
    approvingId.value = null
  }
}

onMounted(async () => {
  await loadMetadata()
  await loadBatch()
})
</script>
