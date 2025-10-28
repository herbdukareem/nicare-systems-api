<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Breadcrumb + Header Actions -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <div class="tw-text-sm tw-text-gray-500 tw-flex tw-items-center tw-gap-2">
            <v-icon size="16">mdi-home</v-icon>
            <span class="tw-cursor-pointer" @click="$router.push('/pas')">PAS</span>
            <v-icon size="16" class="tw-text-gray-400">mdi-chevron-right</v-icon>
            <span class="tw-text-gray-700">Referral Details</span>
          </div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900 tw-mt-1">Referral Details</h1>
          <p class="tw-text-gray-600 tw-mt-1">Review referral info, track status, and take action</p>
        </div>

        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <v-btn
            color="grey"
            variant="tonal"
            prepend-icon="mdi-arrow-left"
            class="tw-hover-lift"
            @click="$router.push('/pas')"
          >Back</v-btn>

          <v-btn
            color="primary"
            variant="tonal"
            prepend-icon="mdi-receipt"
            class="tw-hover-lift"
            @click="printPage"
          >Print Slip</v-btn>

          <v-btn
            color="info"
            variant="outlined"
            prepend-icon="mdi-eye"
            class="tw-hover-lift"
            @click="previewPrint"
          >Preview</v-btn>

          <v-btn
            color="primary"
            variant="text"
            prepend-icon="mdi-refresh"
            :loading="loading"
            class="tw-hover-lift"
            @click="fetchReferral"
          >Refresh</v-btn>

          <v-btn
            color="info"
            variant="outlined"
            prepend-icon="mdi-card-account-details"
            class="tw-hover-lift"
            @click="showIDCardDialog = true"
          >View ID Card</v-btn>
        </div>
      </div>

      <!-- Loading: Skeletons -->
      <div v-if="loading" class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-6">
        <v-skeleton-loader type="image, article, actions" class="tw-rounded-2xl" />
        <v-skeleton-loader type="image, article, actions" class="tw-rounded-2xl" />
        <v-skeleton-loader type="image, article, actions" class="tw-rounded-2xl lg:tw-col-span-2" />
      </div>

      <!-- Error -->
      <v-alert
        v-else-if="error"
        type="error"
        variant="tonal"
        class="tw-rounded-xl tw-mb-6"
      >
        <div class="tw-flex tw-items-center tw-justify-between">
          <span>{{ error }}</span>
          <v-btn size="small" color="error" variant="outlined" @click="fetchReferral">
            Retry
          </v-btn>
        </div>
      </v-alert>

      <!-- Content -->
      <div v-else-if="referral" class="tw-space-y-6">
        <!-- Top Summary -->
        <v-card class="tw-shadow-sm tw-rounded-2xl">
          <v-card-text>
            <div class="tw-flex tw-flex-col md:tw-flex-row tw-items-start md:tw-items-center tw-justify-between tw-gap-4">
              <div class="tw-space-y-1">
                <div class="tw-flex tw-items-center tw-flex-wrap tw-gap-3">
                  <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-tracking-tight">
                    {{ referral.referral_code }}
                  </h2>
                  <v-tooltip text="Copy referral code" location="bottom">
                    <template #activator="{ props }">
                      <v-btn v-bind="props" size="small" icon variant="text" @click="copyToClipboard(referral.referral_code)">
                        <v-icon>mdi-content-copy</v-icon>
                      </v-btn>
                    </template>
                  </v-tooltip>
                </div>
                <p class="tw-text-gray-700 tw-font-medium">
                  {{ referral.enrollee_full_name }}
                </p>
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-text-sm tw-text-gray-600">
                  <span>Created: {{ formatDate(referral.created_at) }}</span>
                  <span v-if="referral.updated_at" class="tw-text-gray-400">•</span>
                  <span v-if="referral.updated_at">Updated: {{ formatDate(referral.updated_at) }}</span>
                </div>
              </div>

              <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 md:tw-justify-end">
                <v-chip :color="getStatusColor(referral.status)" class="tw-font-semibold" size="large" variant="flat">
                  <v-icon start :icon="getStatusIcon(referral.status)" />
                  {{ (referral.status || '').toUpperCase() }}
                </v-chip>
                <v-chip :color="getSeverityColor(referral.severity_level)" variant="outlined">
                  <v-icon start :icon="getSeverityIcon(referral.severity_level)" />
                  {{ (referral.severity_level || '—').toUpperCase() }}
                </v-chip>
              </div>
            </div>

            <!-- Key Stats -->
            <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-3 tw-mt-6">
              <div class="tw-bg-gray-50 tw-rounded-xl tw-p-3 tw-flex tw-items-center tw-justify-between">
                <div>
                  <p class="tw-text-xs tw-text-gray-500">NiCare No.</p>
                  <p class="tw-font-semibold tw-text-gray-900">{{ referral.nicare_number || '—' }}</p>
                </div>
                <v-btn icon size="small" variant="text" @click="copyToClipboard(referral.nicare_number)" :disabled="!referral.nicare_number">
                  <v-icon>mdi-content-copy</v-icon>
                </v-btn>
              </div>
              <div class="tw-bg-gray-50 tw-rounded-xl tw-p-3">
                <p class="tw-text-xs tw-text-gray-500">Gender</p>
                <p class="tw-font-semibold tw-text-gray-900">{{ referral.gender || '—' }}</p>
              </div>
              <div class="tw-bg-gray-50 tw-rounded-xl tw-p-3">
                <p class="tw-text-xs tw-text-gray-500">Age</p>
                <p class="tw-font-semibold tw-text-gray-900">{{ referral.age != null ? referral.age + ' years' : '—' }}</p>
              </div>
              <div class="tw-bg-gray-50 tw-rounded-xl tw-p-3">
                <p class="tw-text-xs tw-text-gray-500">Phone</p>
                <p class="tw-font-semibold tw-text-gray-900">{{ referral.enrollee_phone_main || '—' }}</p>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Details -->
        <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-6">
          <!-- Patient -->
          <v-card class="tw-shadow-sm tw-rounded-2xl">
            <v-card-title class="tw-bg-blue-50 tw-text-blue-800 tw-rounded-t-2xl">
              <v-icon class="tw-mr-2">mdi-account</v-icon> Patient Information
            </v-card-title>
            <v-card-text>
              <v-list density="compact">
                <v-list-item title="Full Name" :subtitle="referral.enrollee_full_name || '—'" />
                <v-list-item title="NiCare Number" :subtitle="referral.nicare_number || '—'" />
                <v-list-item title="Gender" :subtitle="referral.gender || '—'" />
                <v-list-item title="Age" :subtitle="referral.age != null ? referral.age + ' years' : '—'" />
                <v-list-item title="Phone" :subtitle="referral.enrollee_phone_main || '—'" />
              </v-list>
            </v-card-text>
          </v-card>

          <!-- Referring Facility -->
          <v-card class="tw-shadow-sm tw-rounded-2xl">
            <v-card-title class="tw-bg-green-50 tw-text-green-800 tw-rounded-t-2xl">
              <v-icon class="tw-mr-2">mdi-hospital-building</v-icon> Referring Facility
            </v-card-title>
            <v-card-text>
              <v-list density="compact">
                <v-list-item title="Facility Name" :subtitle="referral.referring_facility_name || '—'" />
                <v-list-item title="NiCare Code" :subtitle="referral.referring_nicare_code || '—'" />
                <v-list-item title="Address" :subtitle="referral.referring_address || '—'" />
                <v-list-item title="Phone" :subtitle="referral.referring_phone || '—'" />
                <v-list-item title="Email" :subtitle="referral.referring_email || '—'" />
              </v-list>
            </v-card-text>
          </v-card>

          <!-- Receiving Facility -->
          <v-card class="tw-shadow-sm tw-rounded-2xl">
            <v-card-title class="tw-bg-purple-50 tw-text-purple-800 tw-rounded-t-2xl">
              <v-icon class="tw-mr-2">mdi-hospital-marker</v-icon> Receiving Facility
            </v-card-title>
            <v-card-text>
              <v-list density="compact">
                <v-list-item title="Facility Name" :subtitle="referral.receiving_facility_name || '—'" />
                <v-list-item title="NiCare Code" :subtitle="referral.receiving_nicare_code || '—'" />
                <v-list-item title="Address" :subtitle="referral.receiving_address || '—'" />
                <v-list-item title="Phone" :subtitle="referral.receiving_phone || '—'" />
                <v-list-item title="Email" :subtitle="referral.receiving_email || '—'" />
              </v-list>
            </v-card-text>
          </v-card>

          <!-- Clinical -->
          <v-card class="tw-shadow-sm tw-rounded-2xl">
            <v-card-title class="tw-bg-orange-50 tw-text-orange-800 tw-rounded-t-2xl">
              <v-icon class="tw-mr-2">mdi-medical-bag</v-icon> Clinical Information
            </v-card-title>
            <v-card-text>
              <v-expansion-panels variant="accordion" class="tw-rounded-xl">
                <v-expansion-panel title="Presenting Complaints">
                  <v-expansion-panel-text>
                    <p class="tw-text-gray-700">{{ referral.presenting_complaints || '—' }}</p>
                  </v-expansion-panel-text>
                </v-expansion-panel>
                <v-expansion-panel title="Reasons for Referral" eager>
                  <v-expansion-panel-text>
                    <p class="tw-text-gray-700">{{ referral.reasons_for_referral || '—' }}</p>
                  </v-expansion-panel-text>
                </v-expansion-panel>
                <v-expansion-panel title="Preliminary Diagnosis">
                  <v-expansion-panel-text>
                    <p class="tw-text-gray-700">{{ referral.preliminary_diagnosis || '—' }}</p>
                  </v-expansion-panel-text>
                </v-expansion-panel>
              </v-expansion-panels>

              <div class="tw-mt-4 tw-flex tw-justify-between tw-items-center">
                <span class="tw-font-medium">Severity:</span>
                <v-chip :color="getSeverityColor(referral.severity_level)" size="small" class="tw-font-semibold" variant="flat">
                  <v-icon start :icon="getSeverityIcon(referral.severity_level)" />
                  {{ (referral.severity_level || '—').toUpperCase() }}
                </v-chip>
              </div>
            </v-card-text>
          </v-card>
        </div>

        <!-- Contact & Personnel -->
        <v-card class="tw-shadow-sm tw-rounded-2xl">
          <v-card-title class="tw-bg-teal-50 tw-text-teal-800 tw-rounded-t-2xl">
            <v-icon class="tw-mr-2">mdi-account-circle</v-icon> Contact Person & Personnel
          </v-card-title>
          <v-card-text>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
              <div>
                <h4 class="tw-font-semibold tw-mb-3">Contact Person</h4>
                <v-list density="compact">
                  <v-list-item title="Name" :subtitle="referral.contact_full_name || '—'" />
                  <v-list-item title="Phone" :subtitle="referral.contact_phone || '—'" />
                  <v-list-item title="Email" :subtitle="referral.contact_email || '—'" />
                </v-list>
              </div>
              <div>
                <h4 class="tw-font-semibold tw-mb-3">Referring Personnel</h4>
                <v-list density="compact">
                  <v-list-item title="Name" :subtitle="referral.personnel_full_name || '—'" />
                  <v-list-item title="Specialization" :subtitle="referral.personnel_specialization || '—'" />
                  <v-list-item title="Cadre" :subtitle="referral.personnel_cadre || '—'" />
                  <v-list-item title="Phone" :subtitle="referral.personnel_phone || '—'" />
                </v-list>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Supporting Documents -->
        <v-card
          v-if="referral.enrollee_id_card_path || referral.referral_letter_path || referral.passport_path"
          class="tw-shadow-sm tw-rounded-2xl"
        >
          <v-card-title class="tw-bg-purple-50 tw-text-purple-800 tw-rounded-t-2xl">
            <v-icon class="tw-mr-2">mdi-paperclip</v-icon> Supporting Documents
          </v-card-title>
          <v-card-text>
            <div class="tw-space-y-4">
              <div v-if="referral.enrollee_id_card_path" class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-gray-50 tw-rounded-lg">
                <div class="tw-flex tw-items-center tw-gap-3">
                  <v-icon color="primary" size="32">mdi-card-account-details</v-icon>
                  <div>
                    <div class="tw-font-semibold tw-text-gray-800">Enrollee ID Card/Slip</div>
                    <div class="tw-text-sm tw-text-gray-600">{{ getFileName(referral.enrollee_id_card_path) }}</div>
                  </div>
                </div>
                <v-btn
                  color="primary"
                  variant="outlined"
                  size="small"
                  :href="getDocumentUrl(referral.enrollee_id_card_path)"
                  target="_blank"
                >
                  <v-icon left>mdi-download</v-icon>
                  View/Download
                </v-btn>
              </div>

              <div v-if="referral.referral_letter_path" class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-gray-50 tw-rounded-lg">
                <div class="tw-flex tw-items-center tw-gap-3">
                  <v-icon color="primary" size="32">mdi-file-document</v-icon>
                  <div>
                    <div class="tw-font-semibold tw-text-gray-800">Referral Letter/Slip</div>
                    <div class="tw-text-sm tw-text-gray-600">{{ getFileName(referral.referral_letter_path) }}</div>
                  </div>
                </div>
                <v-btn
                  color="primary"
                  variant="outlined"
                  size="small"
                  :href="getDocumentUrl(referral.referral_letter_path)"
                  target="_blank"
                >
                  <v-icon left>mdi-download</v-icon>
                  View/Download
                </v-btn>
              </div>

              <div v-if="referral.passport_path" class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-gray-50 tw-rounded-lg">
                <div class="tw-flex tw-items-center tw-gap-3">
                  <v-icon color="primary" size="32">mdi-passport</v-icon>
                  <div>
                    <div class="tw-font-semibold tw-text-gray-800">Passport/Travel Document</div>
                    <div class="tw-text-sm tw-text-gray-600">{{ getFileName(referral.passport_path) }}</div>
                  </div>
                </div>
                <v-btn
                  color="primary"
                  variant="outlined"
                  size="small"
                  :href="getDocumentUrl(referral.passport_path)"
                  target="_blank"
                >
                  <v-icon left>mdi-download</v-icon>
                  View/Download
                </v-btn>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Timeline (simple) -->
        <v-card class="tw-shadow-sm tw-rounded-2xl">
          <v-card-title class="tw-bg-gray-50 tw-text-gray-800 tw-rounded-t-2xl">
            <v-icon class="tw-mr-2">mdi-timeline-clock-outline</v-icon> Activity Timeline
          </v-card-title>
          <v-card-text>
            <v-timeline side="end" density="compact" line-inset="12">
              <v-timeline-item dot-color="primary" icon="mdi-file-plus-outline">
                <div class="tw-font-semibold">Referral Created</div>
                <div class="tw-text-sm tw-text-gray-600">{{ formatDate(referral.created_at) }}</div>
              </v-timeline-item>
              <v-timeline-item
                v-if="referral.approved_at"
                dot-color="success"
                icon="mdi-check"
              >
                <div class="tw-font-semibold">Approved</div>
                <div class="tw-text-sm tw-text-gray-600">{{ formatDate(referral.approved_at) }}</div>
              </v-timeline-item>
              <v-timeline-item
                v-if="referral.denied_at"
                dot-color="error"
                icon="mdi-close"
              >
                <div class="tw-font-semibold">Denied</div>
                <div class="tw-text-sm tw-text-gray-600">{{ formatDate(referral.denied_at) }}</div>
              </v-timeline-item>
            </v-timeline>
          </v-card-text>
        </v-card>

        <!-- Actions -->
        <v-card
          v-if="(referral.status || '').toLowerCase() === 'pending'"
          class="tw-shadow-sm tw-rounded-2xl tw-sticky tw-bottom-4 tw-z-10"
        >
          <v-card-text class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
            <div class="tw-text-gray-600 tw-text-sm">
              Take action on this referral
            </div>
            <div class="tw-flex tw-gap-3">
              <v-btn
                color="success"
                variant="elevated"
                :loading="processing === 'approve'"
                @click="confirmApprove"
              >
                <v-icon class="tw-mr-2">mdi-check</v-icon> Approve
              </v-btn>
              <v-btn
                color="error"
                variant="outlined"
                :loading="processing === 'deny'"
                @click="confirmDeny"
              >
                <v-icon class="tw-mr-2">mdi-close</v-icon> Deny
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </div>
    </div>

    <!-- Approve/Deny dialogs -->
    <v-dialog v-model="approveDialog" max-width="420">
      <v-card>
        <v-card-title class="tw-font-semibold">Approve Referral</v-card-title>
        <v-card-text>Are you sure you want to approve <b>{{ referral?.referral_code }}</b>?</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="approveDialog = false">Cancel</v-btn>
          <v-btn color="success" :loading="processing === 'approve'" @click="doApprove">Approve</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="denyDialog" max-width="500">
      <v-card>
        <v-card-title class="tw-font-semibold">Deny Referral</v-card-title>
        <v-card-text>
          <p class="tw-mb-4">Are you sure you want to deny <b>{{ referral?.referral_code }}</b>?</p>
          <v-textarea
            v-model="denyComments"
            label="Reason for denial (required)"
            placeholder="Please provide a reason for denying this referral..."
            rows="3"
            variant="outlined"
            :rules="[v => !!v || 'Comments are required for denial']"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeDenyDialog">Cancel</v-btn>
          <v-btn
            color="error"
            :loading="processing === 'deny'"
            :disabled="!denyComments?.trim()"
            @click="doDeny"
          >
            Deny
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ID Card Dialog -->
    <v-dialog v-model="showIDCardDialog" max-width="600px">
      <v-card>
        <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
          <v-icon class="tw-mr-2">mdi-card-account-details</v-icon>
          Enrollee ID Card
        </v-card-title>
        <v-card-text class="tw-pt-6">
          <EnrolleeIDCard
            v-if="referral?.enrollee"
            :enrollee="referral.enrollee"
          />
          <div v-else class="tw-text-center tw-py-8 tw-text-gray-600">
            <v-icon size="48" class="tw-mb-2">mdi-alert-circle-outline</v-icon>
            <p>Enrollee information not available</p>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showIDCardDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AdminLayout from '../layout/AdminLayout.vue'
import EnrolleeIDCard from './components/EnrolleeIDCard.vue'
import { pasAPI } from '../../utils/api.js'
import { useToast } from '../../composables/useToast'

const route = useRoute()
const { success, error: showError } = useToast()

const loading = ref(true)
const error = ref(null)
const referral = ref(null)

const processing = ref(null)
const approveDialog = ref(false)
const denyDialog = ref(false)
const denyComments = ref('')
const showIDCardDialog = ref(false)

const printPage = () => {
  printReferralSlip()
}

const printReferralSlip = () => {
  if (!referral.value) return

  const printWindow = window.open('', '_blank', 'width=400,height=600')
  const printContent = generatePrintContent()

  printWindow.document.write(printContent)
  printWindow.document.close()

  // Wait for content to load then print
  printWindow.onload = () => {
    printWindow.print()
    printWindow.close()
  }
}

const previewPrint = () => {
  if (!referral.value) return

  const previewWindow = window.open('', '_blank', 'width=400,height=700,scrollbars=yes')
  const printContent = generatePrintContent()

  previewWindow.document.write(printContent)
  previewWindow.document.close()
  previewWindow.focus()
}

const generatePrintContent = () => {
  const ref = referral.value
  const currentDate = new Date().toLocaleString('en-NG')

  return `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Referral Slip - ${ref.referral_code}</title>
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }

        body {
          font-family: 'Courier New', monospace;
          font-size: 12px;
          line-height: 1.4;
          color: #000;
          background: #fff;
          width: 80mm;
          margin: 0 auto;
          padding: 10px;
        }

        .header {
          text-align: center;
          border-bottom: 2px solid #000;
          padding-bottom: 8px;
          margin-bottom: 12px;
        }

        .logo {
          font-weight: bold;
          font-size: 16px;
          margin-bottom: 4px;
        }

        .subtitle {
          font-size: 10px;
          margin-bottom: 2px;
        }

        .section {
          margin-bottom: 12px;
          border-bottom: 1px dashed #666;
          padding-bottom: 8px;
        }

        .section:last-child {
          border-bottom: none;
        }

        .section-title {
          font-weight: bold;
          font-size: 11px;
          margin-bottom: 4px;
          text-transform: uppercase;
        }

        .row {
          display: flex;
          justify-content: space-between;
          margin-bottom: 2px;
          font-size: 10px;
        }

        .label {
          font-weight: bold;
          width: 40%;
        }

        .value {
          width: 58%;
          text-align: right;
        }

        .status {
          text-align: center;
          font-weight: bold;
          font-size: 14px;
          padding: 4px;
          border: 2px solid #000;
          margin: 8px 0;
        }

        .status.pending { background: #fff3cd; }
        .status.approved { background: #d1edff; }
        .status.denied { background: #f8d7da; }

        .footer {
          text-align: center;
          font-size: 9px;
          margin-top: 12px;
          padding-top: 8px;
          border-top: 1px solid #666;
        }

        .qr-placeholder {
          text-align: center;
          border: 1px solid #666;
          padding: 8px;
          margin: 8px 0;
          font-size: 10px;
        }

        @media print {
          body { margin: 0; padding: 5px; }
          .no-print { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="logo">NGSCHA</div>
        <div class="subtitle">Niger State Contributory Healthcare Agency</div>
        <div class="subtitle">REFERRAL SLIP</div>
      </div>

      <div class="section">
        <div class="row">
          <span class="label">REF CODE:</span>
          <span class="value">${ref.referral_code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">DATE:</span>
          <span class="value">${formatDate(ref.created_at)}</span>
        </div>
      </div>

      <div class="status ${(ref.status || '').toLowerCase()}">
        STATUS: ${(ref.status || 'PENDING').toUpperCase()}
      </div>

      <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${ref.enrollee_full_name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">NiCare No:</span>
          <span class="value">${ref.nicare_number || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Gender:</span>
          <span class="value">${ref.gender || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Age:</span>
          <span class="value">${ref.age || '—'} years</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.enrollee_phone_main || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">From (Referring)</div>
        <div class="row">
          <span class="label">Facility:</span>
          <span class="value">${ref.referring_facility_name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Code:</span>
          <span class="value">${ref.referring_nicare_code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.referring_phone || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">To (Receiving)</div>
        <div class="row">
          <span class="label">Facility:</span>
          <span class="value">${ref.receiving_facility_name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Code:</span>
          <span class="value">${ref.receiving_nicare_code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.receiving_phone || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Clinical Details</div>
        <div class="row">
          <span class="label">Severity:</span>
          <span class="value">${(ref.severity_level || '—').toUpperCase()}</span>
        </div>
        <div style="margin-top: 4px;">
          <div style="font-weight: bold; font-size: 10px;">Reason for Referral:</div>
          <div style="font-size: 9px; margin-top: 2px;">${ref.reasons_for_referral || '—'}</div>
        </div>
        ${ref.preliminary_diagnosis ? `
          <div style="margin-top: 4px;">
            <div style="font-weight: bold; font-size: 10px;">Diagnosis:</div>
            <div style="font-size: 9px; margin-top: 2px;">${ref.preliminary_diagnosis}</div>
          </div>
        ` : ''}
      </div>

      <div class="section">
        <div class="section-title">Personnel</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${ref.personnel_full_name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.personnel_phone || '—'}</span>
        </div>
      </div>

      <div class="qr-placeholder">
        QR CODE: ${ref.referral_code}
        <br>Scan for verification
      </div>

      <div class="footer">
        <div>Printed: ${currentDate}</div>
        <div>Niger State Contributory Healthcare Agency</div>
        <div>www.ngscha.ni.gov.ng</div>
      </div>
    </body>
    </html>
  `
}

const copyToClipboard = async (text) => {
  if (!text) return
  try {
    await navigator.clipboard.writeText(String(text))
    success('Copied to clipboard')
  } catch {
    showError('Unable to copy')
  }
}

const formatDate = (dateString) => {
  if (!dateString) return '—'
  const d = new Date(dateString)
  if (isNaN(d)) return '—'
  return d.toLocaleString('en-NG', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getStatusColor = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'pending': return 'warning'
    case 'approved': return 'success'
    case 'denied': return 'error'
    case 'expired': return 'grey'
    default: return 'grey'
  }
}
const getStatusIcon = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'pending': return 'mdi-clock-outline'
    case 'approved': return 'mdi-check-circle'
    case 'denied': return 'mdi-close-circle'
    case 'expired': return 'mdi-alert-circle-outline'
    default: return 'mdi-help-circle-outline'
  }
}
const getSeverityColor = (sev) => {
  switch ((sev || '').toLowerCase()) {
    case 'emergency': return 'error'
    case 'urgent': return 'warning'
    case 'routine': return 'info'
    default: return 'grey'
  }
}
const getSeverityIcon = (sev) => {
  switch ((sev || '').toLowerCase()) {
    case 'emergency': return 'mdi-hospital-box'
    case 'urgent': return 'mdi-alert'
    case 'routine': return 'mdi-information'
    default: return 'mdi-help-circle-outline'
  }
}

const getDocumentUrl = (path) => {
  if (!path) return '#'
  // If the path is already a full URL, return it
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  // Otherwise, construct the URL using the S3/Wasabi endpoint
  const s3Endpoint = import.meta.env.VITE_AWS_ENDPOINT || 'https://s3.wasabisys.com'
  const bucket = import.meta.env.VITE_AWS_BUCKET || 'nicare-documents'
  return `${s3Endpoint}/${bucket}/${path}`
}

const getFileName = (path) => {
  if (!path) return 'Document'
  // Extract filename from path
  const parts = path.split('/')
  return parts[parts.length - 1] || 'Document'
}

const fetchReferral = async () => {
  try {
    loading.value = true
    error.value = null

    const referralCode = String(route.params.referralCode || '').trim()
    const resp = await pasAPI.getReferralByCode(referralCode)

    const page = resp?.data?.data ?? {}
    const items = Array.isArray(page.data) ? page.data : []

    const found = items.find(r => String(r.referral_code).trim() === referralCode)
    referral.value = found || items[0] || null

    if (!referral.value) error.value = 'Referral not found'
  } catch (err) {
    console.error('Error fetching referral:', err)
    error.value = 'Failed to load referral details'
    showError('Failed to load referral details')
  } finally {
    loading.value = false
  }
}

const confirmApprove = () => (approveDialog.value = true)
const confirmDeny = () => {
  denyComments.value = ''
  denyDialog.value = true
}

const closeDenyDialog = () => {
  denyDialog.value = false
  denyComments.value = ''
}

const doApprove = async () => {
  try {
    processing.value = 'approve'
    await pasAPI.approveReferral(referral.value.id, { comments: 'Approved via referral detail page' })
    approveDialog.value = false
    success('Referral approved successfully')
    await fetchReferral()
  } catch (e) {
    console.error('Approval error:', e)
    showError(e.response?.data?.message || 'Failed to approve referral')
  } finally {
    processing.value = null
  }
}

const doDeny = async () => {
  if (!denyComments.value?.trim()) {
    showError('Please provide a reason for denial')
    return
  }

  try {
    processing.value = 'deny'
    await pasAPI.denyReferral(referral.value.id, { comments: denyComments.value.trim() })
    closeDenyDialog()
    success('Referral denied successfully')
    await fetchReferral()
  } catch (e) {
    console.error('Denial error:', e)
    showError(e.response?.data?.message || 'Failed to deny referral')
  } finally {
    processing.value = null
  }
}

onMounted(fetchReferral)
</script>
