<template>
  <div class="tw-space-y-4">
    <!-- Print Button -->
    <div class="tw-flex tw-gap-2 tw-justify-end">
      <v-btn
        color="primary"
        variant="outlined"
        prepend-icon="mdi-printer"
        @click="printCard"
      >
        Print Card
      </v-btn>
      <v-btn
        color="primary"
        variant="outlined"
        prepend-icon="mdi-download"
        @click="downloadCard"
      >
        Download
      </v-btn>
    </div>

    <!-- ID Card Container -->
    <div ref="cardRef" class="tw-bg-white tw-p-4">
      <div class="tw-max-w-md tw-mx-auto">
        <!-- Front of Card -->
        <div class="tw-bg-gradient-to-r tw-from-blue-600 tw-to-blue-800 tw-rounded-lg tw-shadow-lg tw-p-6 tw-text-white tw-mb-4" style="width: 350px; height: 220px;">
          <!-- Header -->
          <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
            <div>
              <div class="tw-text-xs tw-font-bold tw-tracking-wider">NGSCHA</div>
              <div class="tw-text-xs tw-text-blue-100">Healthcare ID</div>
            </div>
            <v-icon size="32">mdi-hospital-box</v-icon>
          </div>

          <!-- Divider -->
          <div class="tw-border-t tw-border-blue-400 tw-mb-3"></div>

          <!-- Enrollee Info -->
          <div class="tw-space-y-2 tw-mb-4">
            <div>
              <div class="tw-text-xs tw-text-blue-100">NICARE NUMBER</div>
              <div class="tw-text-lg tw-font-bold tw-tracking-wider">{{ enrollee.nicare_number || 'N/A' }}</div>
            </div>
            <div>
              <div class="tw-text-xs tw-text-blue-100">NAME</div>
              <div class="tw-text-sm tw-font-semibold tw-line-clamp-2">{{ enrollee.full_name || 'N/A' }}</div>
            </div>
          </div>

          <!-- Footer Info -->
          <div class="tw-flex tw-justify-between tw-items-end tw-text-xs">
            <div>
              <div class="tw-text-blue-100">Valid Till</div>
              <div class="tw-font-bold">{{ formatDate(enrollee.valid_till) }}</div>
            </div>
            <div class="tw-text-right">
              <div class="tw-text-blue-100">Issued</div>
              <div class="tw-font-bold">{{ formatDate(enrollee.created_at) }}</div>
            </div>
          </div>
        </div>

        <!-- Back of Card -->
        <div class="tw-bg-gray-100 tw-rounded-lg tw-shadow-lg tw-p-6" style="width: 350px; height: 220px;">
          <!-- Barcode Section -->
          <div class="tw-flex tw-flex-col tw-items-center tw-justify-center tw-h-full tw-space-y-3">
            <div class="tw-text-center">
              <div class="tw-text-xs tw-text-gray-600 tw-font-semibold tw-mb-2">SCAN FOR VERIFICATION</div>
              <svg ref="barcodeRef" class="tw-mx-auto"></svg>
            </div>
            <div class="tw-text-center tw-text-xs tw-text-gray-700">
              <div class="tw-font-mono tw-font-bold">{{ enrollee.nicare_number || 'N/A' }}</div>
              <div class="tw-text-gray-600 tw-text-xs tw-mt-1">www.ngscha.ni.gov.ng</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import JsBarcode from 'jsbarcode';

const props = defineProps({
  enrollee: {
    type: Object,
    required: true
  }
});

const cardRef = ref(null);
const barcodeRef = ref(null);

const formatDate = (date) => {
  if (!date) return 'N/A';
  const d = new Date(date);
  return d.toLocaleDateString('en-NG', { year: 'numeric', month: 'short', day: '2-digit' });
};

const generateBarcode = () => {
  if (barcodeRef.value && props.enrollee.nicare_number) {
    try {
      JsBarcode(barcodeRef.value, props.enrollee.nicare_number, {
        format: 'CODE128',
        width: 2,
        height: 50,
        displayValue: false,
        margin: 5
      });
    } catch (err) {
      console.error('Failed to generate barcode:', err);
    }
  }
};

const printCard = () => {
  const printWindow = window.open('', '_blank', 'width=800,height=600');
  const cardHTML = cardRef.value.innerHTML;

  const htmlContent = '<!DOCTYPE html><html><head><title>NiCare ID Card</title><script src="https://cdn.tailwindcss.com"><\/script><style>@media print { body { margin: 0; padding: 20px; } .no-print { display: none; } }</style></head><body>' + cardHTML + '</body></html>';

  printWindow.document.write(htmlContent);
  printWindow.document.close();
  setTimeout(() => {
    printWindow.print();
  }, 250);
};

const downloadCard = () => {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  const cardElement = cardRef.value;
  
  // Use html2canvas if available, otherwise just print
  if (window.html2canvas) {
    window.html2canvas(cardElement).then(canvas => {
      const link = document.createElement('a');
      link.href = canvas.toDataURL('image/png');
      link.download = `nicare-id-${props.enrollee.nicare_number}.png`;
      link.click();
    });
  } else {
    // Fallback to print
    printCard();
  }
};

onMounted(() => {
  generateBarcode();
});
</script>

<style scoped>
/* Print styles */
@media print {
  .no-print {
    display: none !important;
  }
}
</style>

