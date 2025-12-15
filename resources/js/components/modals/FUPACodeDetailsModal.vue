<template>
  <v-dialog v-model="isOpen" max-width="1200" scrollable @update:model-value="handleClose">
    <v-card v-if="paCode">
      <v-card-title class="bg-primary text-white d-flex align-center pa-4">
        <v-icon left class="mr-2">mdi-shield-check</v-icon>
        <span class="text-h6">FU-PA Code Details</span>
        <v-spacer></v-spacer>
        <v-btn icon variant="text" @click="handleClose" color="white">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-container fluid>
          <!-- Header Info -->
          <v-row class="bg-grey-lighten-4 pa-4">
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">PA Code</div>
                <div class="text-h6 font-weight-bold">{{ paCode.code }}</div>
              </div>
            </v-col>
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">Type</div>
                <v-chip :color="paCode.type === 'FFS_TOP_UP' ? 'orange' : 'blue'" variant="flat" class="mt-1">
                  {{ paCode.type }}
                </v-chip>
              </div>
            </v-col>
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">Status</div>
                <v-chip :color="getStatusColor(paCode.status)" variant="flat" class="mt-1">
                  <v-icon left size="small">{{ getStatusIcon(paCode.status) }}</v-icon>
                  {{ paCode.status }}
                </v-chip>
              </div>
            </v-col>
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">Requested</div>
                <div class="font-weight-medium">{{ formatDate(paCode.created_at) }}</div>
              </div>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Referral Information Section -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-file-document-check</v-icon>
                Referral Information
              </h3>
            </v-col>
            <v-col cols="12" md="6">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-file-document</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Referral Code</v-list-item-title>
                  <v-list-item-subtitle>{{ paCode.referral?.referral_code || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-identifier</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">UTN</v-list-item-title>
                  <v-list-item-subtitle>{{ paCode.referral?.utn || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="6">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-calendar</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Referral Date</v-list-item-title>
                  <v-list-item-subtitle>{{ formatDate(paCode.referral?.request_date) }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-alert-circle</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Severity</v-list-item-title>
                  <v-list-item-subtitle>{{ paCode.referral?.severity_level || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Patient Information -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-account</v-icon>
                Patient Information
              </h3>
            </v-col>
            <v-col cols="12" md="6">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-account-circle</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Full Name</v-list-item-title>
                  <v-list-item-subtitle>{{ paCode.enrollee?.first_name }} {{ paCode.enrollee?.last_name }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-card-account-details</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Enrollee ID</v-list-item-title>
                  <v-list-item-subtitle>{{ paCode.enrollee?.enrollee_id || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="6">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-phone</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Phone Number</v-list-item-title>
                  <v-list-item-subtitle>{{ paCode.enrollee?.phone_number || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-email</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Email</v-list-item-title>
                  <v-list-item-subtitle>{{ paCode.enrollee?.email || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Facility Information -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-hospital-building</v-icon>
                Facility Information
              </h3>
            </v-col>
            <v-col cols="12">
              <v-card variant="outlined">
                <v-card-title class="bg-blue-lighten-5 text-subtitle-1">
                  <v-icon left color="blue">mdi-hospital</v-icon>
                  Requesting Facility
                </v-card-title>
                <v-card-text>
                  <div class="font-weight-bold text-body-1 mb-1">{{ paCode.facility?.name || 'N/A' }}</div>
                  <div class="text-caption text-grey">{{ paCode.facility?.type || 'N/A' }} Facility</div>
                  <div class="text-caption text-grey mt-1">{{ paCode.facility?.address || 'N/A' }}</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Service Selection -->
          <v-row class="pa-4" v-if="paCode.service_selection_type">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-medical-bag</v-icon>
                Service Selection
              </h3>
            </v-col>
            <v-col cols="12">
              <!-- Bundle Service -->
              <v-alert
                v-if="paCode.service_selection_type === 'bundle'"
                type="info"
                variant="tonal"
                density="compact"
              >
                <div class="d-flex align-center">
                  <v-icon left>mdi-package-variant</v-icon>
                  <div>
                    <div class="font-weight-bold">Bundle Service Selected</div>
                    <div v-if="paCode.service_bundle" class="mt-2">
                      <div class="text-subtitle-2">{{ paCode.service_bundle.description || paCode.service_bundle.name }}</div>
                      <div class="text-caption">Code: {{ paCode.service_bundle.code }} | Price: ₦{{ Number(paCode.service_bundle.fixed_price).toLocaleString() }}</div>
                      <div class="text-caption" v-if="paCode.service_bundle.diagnosis_icd10">ICD-10: {{ paCode.service_bundle.diagnosis_icd10 }}</div>
                    </div>
                  </div>
                </div>
              </v-alert>

              <!-- Direct Services (Multiple) -->
              <div v-if="paCode.service_selection_type === 'direct'">
                <v-alert
                  type="success"
                  variant="tonal"
                  density="compact"
                  class="mb-3"
                >
                  <div class="font-weight-bold">
                    <v-icon left>mdi-medical-bag</v-icon>
                    Direct Services Selected ({{ getDirectServicesCount(paCode) }})
                  </div>
                </v-alert>

                <v-list density="compact" class="bg-grey-lighten-5">
                  <v-list-item
                    v-for="(caseRecord, index) in getDirectServices(paCode)"
                    :key="index"
                    class="mb-2"
                  >
                    <template v-slot:prepend>
                      <v-avatar :color="getCaseRecordColor(caseRecord.detail_type)" size="32">
                        <v-icon color="white" size="18">{{ getCaseRecordIcon(caseRecord.detail_type) }}</v-icon>
                      </v-avatar>
                    </template>
                    <v-list-item-title class="font-weight-medium">{{ caseRecord.case_name }}</v-list-item-title>
                    <v-list-item-subtitle>
                      <v-chip size="x-small" :color="getCaseRecordColor(caseRecord.detail_type)" variant="flat" class="mr-2">
                        {{ getCaseTypeLabel(caseRecord.detail_type) }}
                      </v-chip>
                      NiCare Code: {{ caseRecord.nicare_code }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </v-list>
              </div>
            </v-col>
          </v-row>

          <v-divider v-if="paCode.service_selection_type"></v-divider>

          <!-- Clinical Justification -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-text-box-outline</v-icon>
                Clinical Justification
              </h3>
            </v-col>
            <v-col cols="12">
              <v-card variant="outlined">
                <v-card-text>{{ paCode.justification || 'No justification provided' }}</v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Requested FFS Services -->
          <v-row class="pa-4" v-if="paCode.requested_services && paCode.requested_services.length > 0">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-format-list-bulleted</v-icon>
                Requested FFS Services
              </h3>
            </v-col>
            <v-col cols="12">
              <v-table density="compact">
                <thead>
                  <tr>
                    <th class="text-left">#</th>
                    <th class="text-left">Service</th>
                    <th class="text-center">Quantity</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(service, index) in paCode.requested_services" :key="index">
                    <td>{{ index + 1 }}</td>
                    <td>{{ getServiceName(service.case_record_id) }}</td>
                    <td class="text-center">{{ service.quantity }}</td>
                  </tr>
                </tbody>
              </v-table>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- PA Documents -->
          <v-row class="pa-4" v-if="paCode.documents && paCode.documents.length > 0">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-file-document-multiple</v-icon>
                Supporting Documents
              </h3>
            </v-col>
            <v-col cols="12">
              <v-list density="compact">
                <v-list-item
                  v-for="(doc, index) in paCode.documents"
                  :key="index"
                  class="mb-2"
                >
                  <template v-slot:prepend>
                    <v-icon color="primary">mdi-file-pdf-box</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">{{ doc.document_type }}</v-list-item-title>
                  <v-list-item-subtitle>
                    <v-chip size="x-small" color="blue" variant="flat" class="mr-2">
                      {{ doc.file_name }}
                    </v-chip>
                    <span class="text-caption">Uploaded: {{ formatDate(doc.created_at) }}</span>
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
          </v-row>
        </v-container>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4">
        <v-btn
          color="purple"
          variant="elevated"
          @click="printPASlip"
          prepend-icon="mdi-printer"
        >
          Print Slip
        </v-btn>
        <v-spacer></v-spacer>
        <slot name="actions"></slot>
        <v-btn variant="outlined" @click="handleClose">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: Boolean,
  paCode: Object,
});

const emit = defineEmits(['update:modelValue', 'print-slip']);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const paCode = computed(() => props.paCode);

const handleClose = () => {
  isOpen.value = false;
};

const getStatusColor = (status) => {
  const colors = {
    PENDING: 'orange',
    APPROVED: 'green',
    REJECTED: 'red',
  };
  return colors[status] || 'grey';
};

const getStatusIcon = (status) => {
  const icons = {
    PENDING: 'mdi-clock-outline',
    APPROVED: 'mdi-check-circle',
    REJECTED: 'mdi-close-circle',
  };
  return icons[status] || 'mdi-information';
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getDirectServicesCount = (pa) => {
  if (pa.service_selection_type === 'direct' && pa.case_records) {
    return pa.case_records.length;
  }
  return 0;
};

const getDirectServices = (pa) => {
  if (pa.service_selection_type === 'direct' && pa.case_records) {
    return pa.case_records;
  }
  return [];
};

const getCaseRecordColor = (detailType) => {
  const colors = {
    drug: 'blue',
    laboratory: 'purple',
    professional_service: 'green',
    radiology: 'orange',
    consultation: 'red',
    consumable: 'amber',
  };
  return colors[detailType] || 'grey';
};

const getCaseRecordIcon = (detailType) => {
  const icons = {
    drug: 'mdi-pill',
    laboratory: 'mdi-flask',
    professional_service: 'mdi-stethoscope',
    radiology: 'mdi-hospital-box',
    consultation: 'mdi-doctor',
    consumable: 'mdi-package-variant',
  };
  return icons[detailType] || 'mdi-medical-bag';
};

const getCaseTypeLabel = (detailType) => {
  const labels = {
    drug: 'Drug',
    laboratory: 'Lab',
    professional_service: 'Service',
    radiology: 'Radiology',
    consultation: 'Consultation',
    consumable: 'Consumable',
  };
  return labels[detailType] || 'Service';
};

const getServiceName = (caseRecordId) => {
  // This would need to be passed as a prop or fetched
  return `Service ${caseRecordId}`;
};

const printPASlip = () => {
  if (!paCode.value) return;
  const printWindow = window.open('', '_blank', 'width=400,height=700,scrollbars=yes');
  const printContent = generatePAPrintContent(paCode.value);

  printWindow.document.write(printContent);
  printWindow.document.close();
  printWindow.focus();
};

const generatePAPrintContent = (pa) => {
  const currentDate = new Date().toLocaleString('en-NG');

  // Helper function for case type labels
  const getCaseTypeLabel = (detailType) => {
    const labels = {
      drug: 'Drug',
      laboratory: 'Lab',
      professional_service: 'Service',
      radiology: 'Radiology',
      consultation: 'Consultation',
      consumable: 'Consumable',
    };
    return labels[detailType] || 'Service';
  };

  return `
    <!DOCTYPE html>
    <html>
    <head>
      <title>PA Code Slip - ${pa.code || 'Pending'}</title>
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

        .full-row {
          margin-bottom: 4px;
          font-size: 10px;
        }

        .full-row .label {
          font-weight: bold;
          display: block;
          margin-bottom: 2px;
        }

        .full-row .value {
          display: block;
          text-align: left;
          font-size: 9px;
          padding-left: 8px;
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
        .status.rejected { background: #f8d7da; }

        .table {
          width: 100%;
          border-collapse: collapse;
          margin: 4px 0;
          font-size: 9px;
        }

        .table th, .table td {
          border: 1px solid #666;
          padding: 2px 4px;
          text-align: left;
        }

        .table th {
          background: #f0f0f0;
          font-weight: bold;
        }

        .qr-placeholder {
          text-align: center;
          border: 1px solid #666;
          padding: 8px;
          margin: 8px 0;
          font-size: 10px;
        }

        .footer {
          text-align: center;
          font-size: 9px;
          margin-top: 12px;
          padding-top: 8px;
          border-top: 1px solid #666;
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
        <div class="subtitle">Niger State Contributory Health Agency</div>
        <div class="subtitle">FU-PA CODE SLIP</div>
      </div>

      <div class="section">
        <div class="row">
          <span class="label">PA CODE:</span>
          <span class="value">${pa.code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">TYPE:</span>
          <span class="value">${pa.type === 'FFS_TOP_UP' ? 'FFS TOP-UP' : 'BUNDLE'}</span>
        </div>
        <div class="row">
          <span class="label">DATE:</span>
          <span class="value">${formatDateShort(pa.created_at)}</span>
        </div>
      </div>

      <div class="status ${(pa.status || '').toLowerCase()}">
        STATUS: ${(pa.status || 'PENDING').toUpperCase()}
      </div>

      <div class="section">
        <div class="section-title">Referral Information</div>
        <div class="row">
          <span class="label">Ref Code:</span>
          <span class="value">${pa.referral?.referral_code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">UTN:</span>
          <span class="value">${pa.referral?.utn || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${pa.enrollee?.first_name || ''} ${pa.enrollee?.last_name || ''}</span>
        </div>
        <div class="row">
          <span class="label">Enrollee ID:</span>
          <span class="value">${pa.enrollee?.enrollee_id || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${pa.enrollee?.phone_number || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Requesting Facility</div>
        <div class="row">
          <span class="label">Facility:</span>
          <span class="value">${pa.facility?.name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Code:</span>
          <span class="value">${pa.facility?.facility_code || '—'}</span>
        </div>
      </div>

      ${pa.service_selection_type === 'bundle' && pa.service_bundle ? `
      <div class="section">
        <div class="section-title">Bundle Service</div>
        <div class="row">
          <span class="label">Service:</span>
          <span class="value">${pa.service_bundle.description || pa.service_bundle.name}</span>
        </div>
        <div class="row">
          <span class="label">Code:</span>
          <span class="value">${pa.service_bundle.code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Price:</span>
          <span class="value">₦${Number(pa.service_bundle.fixed_price || 0).toLocaleString()}</span>
        </div>
        ${pa.service_bundle.diagnosis_icd10 ? `
        <div class="row">
          <span class="label">ICD-10:</span>
          <span class="value">${pa.service_bundle.diagnosis_icd10}</span>
        </div>
        ` : ''}
      </div>
      ` : ''}

      ${pa.service_selection_type === 'direct' && pa.case_records && pa.case_records.length > 0 ? `
      <div class="section">
        <div class="section-title">Direct Services (${pa.case_records.length})</div>
        <table class="table">
          <thead>
            <tr>
              <th>Service</th>
              <th>Type</th>
              <th>Code</th>
            </tr>
          </thead>
          <tbody>
            ${pa.case_records.map(record => `
            <tr>
              <td>${record.case_name || '—'}</td>
              <td>${getCaseTypeLabel(record.detail_type)}</td>
              <td>${record.nicare_code || '—'}</td>
            </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
      ` : ''}

      ${pa.justification ? `
      <div class="section">
        <div class="full-row">
          <span class="label">Clinical Justification:</span>
          <span class="value">${pa.justification}</span>
        </div>
      </div>
      ` : ''}

      <div class="qr-placeholder">
        QR CODE: ${pa.code || 'PENDING'}
        <br>Scan for verification
      </div>

      <div class="footer">
        <div>Printed: ${currentDate}</div>
        <div>Niger State Contributory Health Agency</div>
        <div>www.ngscha.ng.gov.ng</div>
      </div>
    </body>
    </html>
  `;
};

const formatDateShort = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};
</script>

<style scoped>
.detail-item {
  padding: 8px 0;
}
</style>

