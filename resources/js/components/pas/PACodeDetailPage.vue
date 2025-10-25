<template>
  <div class="tw-min-h-screen tw-bg-gray-50">
    <!-- Header -->
    <div class="tw-bg-white tw-shadow-sm tw-border-b">
      <div class="tw-max-w-7xl tw-mx-auto tw-px-4 sm:tw-px-6 lg:tw-px-8">
        <div class="tw-flex tw-items-center tw-justify-between tw-h-16">
          <div class="tw-flex tw-items-center tw-space-x-4">
            <v-btn
              icon
              variant="text"
              @click="$router.go(-1)"
            >
              <v-icon>mdi-arrow-left</v-icon>
            </v-btn>
            <div>
              <h1 class="tw-text-xl tw-font-semibold tw-text-gray-900">PA Code Details</h1>
              <p class="tw-text-sm tw-text-gray-600">{{ paCode?.pa_code || 'Loading...' }}</p>
            </div>
          </div>
          <div class="tw-flex tw-items-center tw-space-x-3">
            <v-btn
              v-if="paCode"
              color="primary"
              variant="outlined"
              @click="previewPrint"
            >
              <v-icon left>mdi-eye</v-icon>
              Preview Slip
            </v-btn>
            <v-btn
              v-if="paCode"
              color="primary"
              @click="printPACodeSlip"
            >
              <v-icon left>mdi-printer</v-icon>
              Print Slip
            </v-btn>
            <v-chip
              v-if="paCode"
              :color="getStatusColor(paCode.status)"
              size="small"
              variant="flat"
            >
              {{ getStatusText(paCode.status) }}
            </v-chip>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="tw-flex tw-justify-center tw-items-center tw-h-64">
      <v-progress-circular indeterminate color="primary" size="64" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="tw-max-w-7xl tw-mx-auto tw-px-4 sm:tw-px-6 lg:tw-px-8 tw-py-8">
      <v-alert type="error" variant="tonal">
        {{ error }}
      </v-alert>
    </div>

    <!-- PA Code Details -->
    <div v-else-if="paCode" class="tw-max-w-7xl tw-mx-auto tw-px-4 sm:tw-px-6 lg:tw-px-8 tw-py-8">
      <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-8">
        
        <!-- Main Content -->
        <div class="lg:tw-col-span-2 tw-space-y-6">
          
          <!-- PA Code Information -->
          <v-card>
            <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
              <v-icon class="tw-mr-2">mdi-qrcode</v-icon>
              PA Code Information
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">PA Code</label>
                  <p class="tw-text-lg tw-font-mono tw-text-gray-900">{{ paCode.pa_code }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">UTN Number</label>
                  <p class="tw-text-lg tw-font-mono tw-text-gray-900">{{ paCode.utn || 'Not generated' }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Service Type</label>
                  <p class="tw-text-gray-900">{{ paCode.service_type }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Service Description</label>
                  <p class="tw-text-gray-900">{{ paCode.service_description }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Approved Amount</label>
                  <p class="tw-text-gray-900">₦{{ paCode.approved_amount || 'Not specified' }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Usage</label>
                  <p class="tw-text-gray-900">{{ paCode.usage_count }} / {{ paCode.max_usage }}</p>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Patient Information -->
          <v-card>
            <v-card-title class="tw-bg-green-50 tw-text-green-800">
              <v-icon class="tw-mr-2">mdi-account</v-icon>
              Patient Information
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Patient Name</label>
                  <p class="tw-text-gray-900">{{ paCode.enrollee_name }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">NiCare Number</label>
                  <p class="tw-text-gray-900">{{ paCode.nicare_number }}</p>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Facility Information -->
          <v-card>
            <v-card-title class="tw-bg-purple-50 tw-text-purple-800">
              <v-icon class="tw-mr-2">mdi-hospital-building</v-icon>
              Facility Information
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Facility Name</label>
                  <p class="tw-text-gray-900">{{ paCode.facility_name }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Facility Code</label>
                  <p class="tw-text-gray-900">{{ paCode.facility_nicare_code }}</p>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Conditions & Comments -->
          <v-card v-if="paCode.conditions || paCode.issuer_comments">
            <v-card-title class="tw-bg-amber-50 tw-text-amber-800">
              <v-icon class="tw-mr-2">mdi-note-text</v-icon>
              Additional Information
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div v-if="paCode.conditions" class="tw-mb-4">
                <label class="tw-text-sm tw-font-medium tw-text-gray-700">Conditions</label>
                <p class="tw-text-gray-900">{{ paCode.conditions }}</p>
              </div>
              <div v-if="paCode.issuer_comments">
                <label class="tw-text-sm tw-font-medium tw-text-gray-700">Issuer Comments</label>
                <p class="tw-text-gray-900">{{ paCode.issuer_comments }}</p>
              </div>
            </v-card-text>
          </v-card>
        </div>

        <!-- Sidebar -->
        <div class="tw-space-y-6">
          
          <!-- Status & Validity -->
          <v-card>
            <v-card-title class="tw-bg-gray-50">
              Status & Validity
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-space-y-4">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Status</label>
                  <div class="tw-mt-1">
                    <v-chip
                      :color="getStatusColor(paCode.status)"
                      size="small"
                      variant="flat"
                    >
                      {{ getStatusText(paCode.status) }}
                    </v-chip>
                  </div>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Issued Date</label>
                  <p class="tw-text-gray-900">{{ formatDate(paCode.issued_at) }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Expires Date</label>
                  <p class="tw-text-gray-900">{{ formatDate(paCode.expires_at) }}</p>
                </div>
                <div v-if="paCode.issued_by_user">
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Issued By</label>
                  <p class="tw-text-gray-900">{{ paCode.issued_by_user.name }}</p>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Actions -->
          <v-card>
            <v-card-title class="tw-bg-gray-50">
              Actions
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-space-y-3">
                <v-btn
                  v-if="paCode.referral_id"
                  color="blue"
                  variant="outlined"
                  block
                  @click="viewReferral"
                >
                  <v-icon left>mdi-file-document</v-icon>
                  View Referral
                </v-btn>
                <v-btn
                  v-if="canGenerateUTN"
                  color="green"
                  variant="outlined"
                  block
                  @click="generateUTN"
                >
                  <v-icon left>mdi-qrcode</v-icon>
                  Generate UTN
                </v-btn>
                <v-btn
                  v-if="canMarkUsed"
                  color="orange"
                  variant="outlined"
                  block
                  @click="markAsUsed"
                >
                  <v-icon left>mdi-check</v-icon>
                  Mark as Used
                </v-btn>
                <v-btn
                  v-if="canCancel"
                  color="red"
                  variant="outlined"
                  block
                  @click="cancelPACode"
                >
                  <v-icon left>mdi-cancel</v-icon>
                  Cancel PA Code
                </v-btn>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from '../../composables/useToast';
import { pasAPI } from '../../utils/api.js';

const route = useRoute();
const router = useRouter();
const { success, error: showError } = useToast();

// Reactive data
const paCode = ref(null);
const loading = ref(false);
const error = ref(null);

// Computed properties
const canGenerateUTN = computed(() => {
  return paCode.value && paCode.value.status === 1 && !paCode.value.utn;
});

const canMarkUsed = computed(() => {
  return paCode.value && paCode.value.status === 1 && paCode.value.usage_count < paCode.value.max_usage;
});

const canCancel = computed(() => {
  return paCode.value && paCode.value.status === 1;
});

// Methods
const fetchPACode = async () => {
  try {
    loading.value = true;
    error.value = null;

    const paCodeId = route.params.paCodeId;
    const response = await pasAPI.getPACodeById(paCodeId);

    if (response.data.success) {
      paCode.value = response.data.data;
    } else {
      error.value = response.data.message || 'PA Code not found';
    }
  } catch (err) {
    console.error('Error fetching PA code:', err);
    error.value = 'Failed to load PA code details';
    showError('Failed to load PA code details');
  } finally {
    loading.value = false;
  }
};

const getStatusColor = (status) => {
  switch (status) {
    case 1: return 'green';
    case 16: return 'orange';
    case 0: return 'red';
    default: return 'gray';
  }
};

const getStatusText = (status) => {
  switch (status) {
    case 1: return 'Active';
    case 16: return 'Used';
    case 0: return 'Cancelled';
    default: return 'Unknown';
  }
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const viewReferral = () => {
  if (paCode.value?.referral?.referral_code) {
    router.push(`/pas/referrals/${paCode.value.referral.referral_code}`);
  } else {
    showError('Referral information not available');
  }
};

const generateUTN = async () => {
  try {
    const response = await pasAPI.generateUTN(paCode.value.id);
    if (response.data.success) {
      success('UTN generated successfully');
      fetchPACode(); // Refresh data
    } else {
      showError(response.data.message || 'Failed to generate UTN');
    }
  } catch (err) {
    console.error('Error generating UTN:', err);
    showError('Failed to generate UTN');
  }
};

const markAsUsed = async () => {
  if (confirm('Are you sure you want to mark this PA code as used?')) {
    try {
      const response = await pasAPI.markPACodeAsUsed(paCode.value.id);
      if (response.data.success) {
        success('PA code marked as used');
        fetchPACode(); // Refresh data
      } else {
        showError(response.data.message || 'Failed to mark PA code as used');
      }
    } catch (err) {
      console.error('Error marking PA code as used:', err);
      showError('Failed to mark PA code as used');
    }
  }
};

const cancelPACode = async () => {
  if (confirm('Are you sure you want to cancel this PA code? This action cannot be undone.')) {
    try {
      const response = await pasAPI.cancelPACode(paCode.value.id);
      if (response.data.success) {
        success('PA code cancelled successfully');
        fetchPACode(); // Refresh data
      } else {
        showError(response.data.message || 'Failed to cancel PA code');
      }
    } catch (err) {
      console.error('Error cancelling PA code:', err);
      showError('Failed to cancel PA code');
    }
  }
};

const printPACodeSlip = () => {
  if (!paCode.value) return;

  const printWindow = window.open('', '_blank', 'width=400,height=600');
  const printContent = generatePrintContent();

  printWindow.document.write(printContent);
  printWindow.document.close();

  // Wait for content to load then print
  printWindow.onload = () => {
    printWindow.print();
    printWindow.close();
  };
};

const previewPrint = () => {
  if (!paCode.value) return;

  const previewWindow = window.open('', '_blank', 'width=400,height=700,scrollbars=yes');
  const printContent = generatePrintContent();

  previewWindow.document.write(printContent);
  previewWindow.document.close();
  previewWindow.focus();
};

const generatePrintContent = () => {
  const pa = paCode.value;
  const currentDate = new Date().toLocaleString('en-NG');

  return `
    <!DOCTYPE html>
    <html>
    <head>
      <title>PA Code Slip - ${pa.pa_code}</title>
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
          font-size: 11px;
        }

        .label {
          font-weight: bold;
          flex: 0 0 40%;
        }

        .value {
          flex: 1;
          text-align: right;
          word-break: break-word;
        }

        .status {
          text-align: center;
          font-weight: bold;
          font-size: 14px;
          padding: 6px;
          margin: 8px 0;
          border: 2px solid #000;
        }

        .status.active {
          background: #e8f5e8;
        }

        .status.used {
          background: #fff3cd;
        }

        .status.cancelled {
          background: #f8d7da;
        }

        .pa-code {
          text-align: center;
          font-size: 18px;
          font-weight: bold;
          font-family: 'Courier New', monospace;
          letter-spacing: 2px;
          margin: 12px 0;
          padding: 8px;
          border: 2px solid #000;
          background: #f0f0f0;
        }

        .utn-code {
          text-align: center;
          font-size: 14px;
          font-weight: bold;
          font-family: 'Courier New', monospace;
          letter-spacing: 1px;
          margin: 8px 0;
          padding: 6px;
          border: 1px solid #666;
          background: #f8f8f8;
        }

        .qr-placeholder {
          text-align: center;
          font-size: 10px;
          margin: 12px 0;
          padding: 8px;
          border: 1px dashed #666;
        }

        .footer {
          text-align: center;
          font-size: 9px;
          margin-top: 12px;
          padding-top: 8px;
          border-top: 1px solid #666;
        }

        .footer div {
          margin-bottom: 2px;
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
        <div class="subtitle">PRE-AUTHORIZATION CODE SLIP</div>
      </div>

      <div class="pa-code">
        ${pa.pa_code || '—'}
      </div>

      ${pa.utn ? `<div class="utn-code">UTN: ${pa.utn}</div>` : ''}

      <div class="section">
        <div class="row">
          <span class="label">ISSUED:</span>
          <span class="value">${formatDate(pa.issued_at)}</span>
        </div>
        <div class="row">
          <span class="label">EXPIRES:</span>
          <span class="value">${formatDate(pa.expires_at)}</span>
        </div>
        <div class="row">
          <span class="label">USAGE:</span>
          <span class="value">${pa.usage_count}/${pa.max_usage}</span>
        </div>
      </div>

      <div class="status ${getStatusText(pa.status).toLowerCase()}">
        STATUS: ${getStatusText(pa.status).toUpperCase()}
      </div>

      <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${pa.enrollee_name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">NiCare No:</span>
          <span class="value">${pa.nicare_number || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Facility Information</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${pa.facility_name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Code:</span>
          <span class="value">${pa.facility_nicare_code || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Service Information</div>
        <div class="row">
          <span class="label">Type:</span>
          <span class="value">${pa.service_type || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Description:</span>
          <span class="value">${pa.service_description || '—'}</span>
        </div>
        ${pa.approved_amount ? `
        <div class="row">
          <span class="label">Amount:</span>
          <span class="value">₦${pa.approved_amount}</span>
        </div>
        ` : ''}
      </div>

      ${pa.conditions ? `
      <div class="section">
        <div class="section-title">Conditions</div>
        <div style="font-size: 10px; text-align: justify;">
          ${pa.conditions}
        </div>
      </div>
      ` : ''}

      ${pa.issuer_comments ? `
      <div class="section">
        <div class="section-title">Comments</div>
        <div style="font-size: 10px; text-align: justify;">
          ${pa.issuer_comments}
        </div>
      </div>
      ` : ''}

      <div class="qr-placeholder">
        QR CODE: ${pa.pa_code}
        <br>Scan for verification
      </div>

      <div class="footer">
        <div>Printed: ${currentDate}</div>
        <div>Niger State Contributory Healthcare Agency</div>
        <div>www.ngscha.ni.gov.ng</div>
      </div>
    </body>
    </html>
  `;
};

onMounted(() => {
  fetchPACode();
});
</script>
