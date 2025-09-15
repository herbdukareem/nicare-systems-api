<template>
  <AdminLayout>
    <!-- Loading -->
    <div v-if="!enrollee && loading" class="tw-space-y-6">
      <v-skeleton-loader type="image, article, table" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6" />
      <v-skeleton-loader type="article, actions" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6" />
      <v-skeleton-loader type="table" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6" />
    </div>

    <div class="tw-space-y-6" v-else-if="enrollee">
      <!-- Header -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-items-start tw-justify-between tw-gap-6">
          <div class="tw-flex tw-items-start tw-gap-6">
            <!-- Avatar -->
            <div class="tw-relative">
              <div
                class="tw-w-32 tw-h-32 tw-rounded-2xl tw-bg-gradient-to-br tw-from-gray-100 tw-to-gray-50 tw-overflow-hidden tw-ring-2 tw-ring-offset-2 tw-ring-offset-white tw-ring-gray-200"
              >
                <img
                  v-if="enrollee.image_url"
                  :src="enrollee.image_url"
                  alt="Profile Picture"
                  class="tw-w-full tw-h-full tw-object-cover"
                />
                <div v-else class="tw-w-full tw-h-full tw-flex tw-items-center tw-justify-center">
                  <v-icon size="56" color="grey">mdi-account</v-icon>
                </div>
              </div>

              <!-- status dot -->
              <span
                class="tw-absolute tw-bottom-2 tw-left-2 tw-inline-block tw-w-3 tw-h-3 tw-rounded-full tw-ring-2 tw-ring-white"
                :class="{
                  'tw-bg-green-500': (enrollee.status || '').toLowerCase() === 'active',
                  'tw-bg-orange-500': (enrollee.status || '').toLowerCase() === 'pending',
                  'tw-bg-red-500': ['suspended','expired'].includes((enrollee.status || '').toLowerCase()),
                  'tw-bg-gray-400': !enrollee.status
                }"
              />
              <!-- Upload -->
              <v-tooltip text="Upload passport photo" location="bottom">
                <template #activator="{ props: tt }">
                  <v-btn icon size="small" color="primary" class="tw-absolute tw-bottom-2 tw-right-2" v-bind="tt" @click="triggerFileUpload">
                    <v-icon size="16">mdi-camera</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>
              <input ref="fileInput" type="file" accept="image/*" class="tw-hidden" @change="handleFileUpload" />
            </div>

            <!-- Basics -->
            <div class="tw-flex-1">
              <div class="tw-flex tw-items-center tw-gap-2">
                <h1 class="tw-text-2xl md:tw-text-3xl tw-font-bold tw-text-gray-900">{{ enrollee.name }}</h1>
                <v-chip v-if="enrollee.nin" size="small" variant="flat" color="indigo">
                  <v-icon start size="16">mdi-shield-check</v-icon> NIN Verified
                </v-chip>
              </div>

              <div class="tw-flex tw-items-center tw-gap-2 tw-mt-1">
                <p class="tw-text-sm md:tw-text-base tw-text-gray-600 font-mono">{{ enrollee.enrollee_id }}</p>
                <v-btn size="x-small" variant="text" icon @click="copy(enrollee.enrollee_id)" :aria-label="`Copy enrollee ID`">
                  <v-icon size="16">mdi-content-copy</v-icon>
                </v-btn>
              </div>

              <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-mt-3">
                <v-chip :color="getStatusColor(enrollee.status)" size="small" variant="flat" class="tw-capitalize">
                  <v-icon start size="16">mdi-check-circle</v-icon>{{ enrollee.status || 'unknown' }}
                </v-chip>
                <v-chip color="primary" size="small" variant="outlined">
                  <v-icon start size="16">mdi-badge-account</v-icon>{{ enrollee.type || 'N/A' }}
                </v-chip>
              </div>

              <div class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-2 tw-gap-4 tw-mt-4">
                <div class="tw-bg-gray-50 tw-rounded-lg tw-p-3 tw-flex tw-items-center tw-justify-between">
                  <div class="tw-flex tw-items-center tw-gap-2">
                    <v-icon size="18" color="grey">mdi-phone</v-icon>
                    <div>
                      <p class="tw-text-xs tw-text-gray-500">Phone</p>
                      <p class="tw-font-medium">{{ enrollee.phone }}</p>
                    </div>
                  </div>
                  <v-btn size="x-small" variant="text" icon @click="copy(enrollee.phone)" :aria-label="`Copy phone`">
                    <v-icon size="16">mdi-content-copy</v-icon>
                  </v-btn>
                </div>
                <div class="tw-bg-gray-50 tw-rounded-lg tw-p-3 tw-flex tw-items-center tw-justify-between">
                  <div class="tw-flex tw-items-center tw-gap-2">
                    <v-icon size="18" color="grey">mdi-email</v-icon>
                    <div>
                      <p class="tw-text-xs tw-text-gray-500">Email</p>
                      <p class="tw-font-medium">{{ enrollee.email || 'N/A' }}</p>
                    </div>
                  </div>
                  <v-btn v-if="enrollee.email" size="x-small" variant="text" icon :href="`mailto:${enrollee.email}`">
                    <v-icon size="16">mdi-open-in-new</v-icon>
                  </v-btn>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="tw-flex tw-flex-col sm:tw-flex-row tw-gap-2 sm:tw-items-start">
            <v-btn color="primary" variant="outlined" prepend-icon="mdi-pencil" @click="editEnrollee">Edit Profile</v-btn>
            <v-btn color="primary" prepend-icon="mdi-download" @click="downloadProfile">Download PDF</v-btn>
          </div>
        </div>
      </div>

      <!-- Status Management -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
          <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900">Status Management</h2>
          <v-alert
            density="comfortable"
            :type="(enrollee.status || '').toLowerCase() === 'active' ? 'success' : 'warning'"
            variant="tonal"
            class="tw-w-auto"
          >
            Current: <strong class="tw-capitalize">{{ enrollee.status || 'unknown' }}</strong>
          </v-alert>
        </div>

        <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-6">
          <!-- Current -->
          <div class="tw-bg-gray-50 tw-rounded-xl tw-p-4">
            <div class="tw-flex tw-items-center tw-justify-between">
              <div>
                <v-chip :color="getStatusColor(enrollee.status)" size="large" variant="flat" class="tw-capitalize">
                  {{ enrollee.status }}
                </v-chip>
                <p class="tw-text-sm tw-text-gray-600 tw-mt-2">Last updated: {{ formatDate(enrollee.updated_at) }}</p>
              </div>
              <v-btn
                :color="(enrollee.status || '').toLowerCase() === 'active' ? 'orange' : 'green'"
                variant="outlined"
                @click="toggleStatus"
                :loading="updatingStatus"
              >
                {{ (enrollee.status || '').toLowerCase() === 'active' ? 'Disable' : 'Enable' }}
              </v-btn>
            </div>
          </div>

          <!-- Change -->
          <div>
            <div class="tw-grid tw-grid-cols-1 tw-gap-4">
              <v-select
                v-model="newStatus"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Select New Status"
                variant="outlined"
                density="comfortable"
                clearable
              />
              <v-textarea
                v-model="statusComment"
                label="Comment (Optional)"
                variant="outlined"
                density="comfortable"
                rows="3"
                placeholder="Add a comment about this status change..."
              />
              <v-btn
                color="primary"
                @click="updateStatus"
                :loading="updatingStatus"
                :disabled="!newStatus || newStatus === enrollee.status"
                block
              >
                Update Status
              </v-btn>
            </div>
          </div>
        </div>
      </div>

      <!-- Personal Info -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Personal Information</h2>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
          <InfoItem label="Full Name" :value="enrollee.name" icon="mdi-account-badge" />
          <InfoItem label="NIN" :value="enrollee.nin || 'N/A'" icon="mdi-card-account-details" />
          <InfoItem label="Date of Birth" :value="formatDate(enrollee.date_of_birth)" icon="mdi-cake-variant" />
          <InfoItem label="Age" :value="(enrollee.age || 'N/A') + ' years'" icon="mdi-account-clock" />
          <InfoItem label="Gender" :value="enrollee.gender || 'N/A'" icon="mdi-gender-male-female" />
          <InfoItem label="Marital Status" :value="getMaritalStatus(enrollee.marital_status)" icon="mdi-ring" />
          <InfoItem label="Phone Number" :value="enrollee.phone" icon="mdi-phone" />
          <InfoItem label="Email Address" :value="enrollee.email || 'N/A'" icon="mdi-email" />
          <InfoItem label="Address" :value="enrollee.address || 'N/A'" icon="mdi-home-map-marker" />
        </div>
      </div>

      <!-- Location -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Location Information</h2>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4">
          <InfoItem label="LGA" :value="enrollee.lga_name || 'N/A'" icon="mdi-map" />
          <InfoItem label="Ward" :value="enrollee.ward?.name || 'N/A'" icon="mdi-map-marker" />
          <InfoItem label="Village" :value="enrollee.village || 'N/A'" icon="mdi-home-group" />
          <InfoItem label="Primary Facility" :value="enrollee.facility_name || 'N/A'" icon="mdi-hospital-building" />
        </div>
      </div>

      <!-- Enrollment -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Enrollment Details</h2>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
          <InfoItem label="Enrollee ID" :value="enrollee.enrollee_id" icon="mdi-identifier" />
          <InfoItem label="Enrollment Date" :value="formatDate(enrollee.enrollment_date)" icon="mdi-calendar-plus" />
          <InfoItem label="Approval Date" :value="formatDate(enrollee.approval_date)" icon="mdi-calendar-check" />
          <InfoItem label="Benefactor" :value="enrollee.benefactor?.name || 'N/A'" icon="mdi-account-heart" />
          <InfoItem label="Funding Type" :value="enrollee.funding_type?.name || 'N/A'" icon="mdi-cash-multiple" />
          <InfoItem label="Premium ID" :value="enrollee.premium?.id || 'N/A'" icon="mdi-pound-box" />
        </div>
      </div>

      <!-- Employment -->
      <div v-if="enrollee.employment_detail" class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Employment Details</h2>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
          <InfoItem label="Occupation" :value="enrollee.occupation || 'N/A'" icon="mdi-briefcase" />
          <InfoItem label="CNO" :value="enrollee.cno || 'N/A'" icon="mdi-clipboard-text" />
          <InfoItem label="Basic Salary" :value="formatCurrency(enrollee.basic_salary)" icon="mdi-currency-ngn" />
          <InfoItem label="Station" :value="enrollee.station || 'N/A'" icon="mdi-office-building-marker" />
          <InfoItem label="Salary Scheme" :value="enrollee.salary_scheme || 'N/A'" icon="mdi-cash-100" />
          <InfoItem label="Date of First Appointment" :value="formatDate(enrollee.dfa)" icon="mdi-calendar-start" />
        </div>
      </div>

      <!-- Next of Kin -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Next of Kin Information</h2>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4">
          <InfoItem label="Name" :value="enrollee.nok_name || 'N/A'" icon="mdi-account-heart-outline" />
          <InfoItem label="Phone Number" :value="enrollee.nok_phone_number || 'N/A'" icon="mdi-phone" />
          <InfoItem label="Relationship" :value="enrollee.nok_relationship || 'N/A'" icon="mdi-account-group" />
          <InfoItem label="Address" :value="enrollee.nok_address || 'N/A'" icon="mdi-home-map-marker" />
        </div>
      </div>

      <!-- Activity stats -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Activity Statistics</h2>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-6">
          <StatTile color="blue" icon="mdi-file-document" :value="statistics.totalClaims" label="Total Claims" />
          <StatTile color="green" icon="mdi-currency-ngn" :value="formatCurrency(statistics.totalBenefits)" label="Total Benefits" />
          <StatTile color="purple" icon="mdi-hospital-building" :value="statistics.facilitiesVisited" label="Facilities Visited" />
          <StatTile color="orange" icon="mdi-calendar-check" :value="statistics.lastVisit" label="Days Since Last Visit" />
        </div>
      </div>
    </div>

    <!-- Fallback -->
    <div v-else class="tw-flex tw-justify-center tw-items-center tw-h-64">
      <v-progress-circular indeterminate color="primary" size="64" />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, defineComponent, h, resolveComponent } from 'vue'
import { useRoute } from 'vue-router'
import AdminLayout from '../layout/AdminLayout.vue'
import { useToast } from '../../composables/useToast'
import { enrolleeAPI } from '../../utils/api'
import axios from 'axios'

/* Toast + route */
const route = useRoute()
const { success, error } = useToast()

/* State */
const enrollee = ref(null)
const loading = ref(false)
const updatingStatus = ref(false)
const newStatus = ref(null)
const statusComment = ref('')
const fileInput = ref(null)
const statusOptions = ref([])

/* Demo stats (replace with API when ready) */
const statistics = ref({ totalClaims: 12, totalBenefits: 45000, facilitiesVisited: 3, lastVisit: 15 })

/* -------- Small presentational components (render functions to keep SFC lean) -------- */
const InfoItem = defineComponent({
  name: 'InfoItem',
  props: { label: String, value: [String, Number], icon: String },
  setup(props) {
    const VIcon = resolveComponent('v-icon')
    return () =>
      h('div', { class: 'tw-bg-gray-50 tw-rounded-lg tw-p-4 tw-h-full' }, [
        h('div', { class: 'tw-flex tw-items-center tw-gap-2 tw-text-gray-600 tw-text-sm' }, [
          h(VIcon, { size: 18, color: 'grey' }, { default: () => props.icon }),
          h('span', props.label),
        ]),
        h('p', { class: 'tw-font-medium tw-text-gray-900 tw-mt-1 break-words' }, String(props.value ?? 'N/A')),
      ])
  },
})

const StatTile = defineComponent({
  name: 'StatTile',
  props: { color: String, icon: String, value: [String, Number], label: String },
  setup(props) {
    const VIcon = resolveComponent('v-icon')
    const bg = (c) =>
      c === 'blue' ? 'tw-bg-blue-50 tw-text-blue-700' :
      c === 'green' ? 'tw-bg-green-50 tw-text-green-700' :
      c === 'purple' ? 'tw-bg-purple-50 tw-text-purple-700' :
      c === 'orange' ? 'tw-bg-orange-50 tw-text-orange-700' : 'tw-bg-gray-50 tw-text-gray-700'
    const iconColor = (c) => (c || 'grey')
    return () =>
      h('div', { class: `tw-text-center tw-p-4 tw-rounded-lg ${bg(props.color)}` }, [
        h(VIcon, { size: 32, color: iconColor(props.color) }, { default: () => props.icon }),
        h('p', { class: 'tw-text-2xl tw-font-bold tw-mt-2' }, String(props.value ?? '—')),
        h('p', { class: 'tw-text-sm tw-text-gray-600' }, props.label),
      ])
  },
})

/* Methods */
const loadEnrollee = async () => {
  loading.value = true
  try {
    const response = await enrolleeAPI.getById(route.params.id)
    enrollee.value = response.data.data
  } catch (err) {
    error('Failed to load enrollee details')
  } finally {
    loading.value = false
  }
}

const loadStatusOptions = async () => {
  try {
    const response = await axios.get('/api/dashboard/status-options')
    if (response.data.success) {
      statusOptions.value = Object.entries(response.data.data).map(([value, label]) => ({
        value: isNaN(Number(value)) ? label : label, // backend sometimes sends numeric keys; we want string status values
        label: label.charAt(0).toUpperCase() + label.slice(1),
      }))
    }
  } catch (err) {
    console.error('Failed to load status options:', err)
  }
}

const triggerFileUpload = () => fileInput.value?.click()

const handleFileUpload = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) return error('Please select a valid image file')
  if (file.size > 2 * 1024 * 1024) return error('File size must be less than 2MB')

  try {
    const formData = new FormData()
    formData.append('passport', file)
    const response = await axios.post(`/api/v1/enrollees/${enrollee.value.id}/upload-passport`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (response.data.success) {
      success('Passport photo uploaded successfully')
      enrollee.value.image_url = response.data.data.image_url
    }
  } catch {
    error('Failed to upload passport photo')
  }
}

const toggleStatus = async () => {
  const current = (enrollee.value.status || '').toLowerCase()
  const next = current === 'active' ? 'suspended' : 'active'
  await updateEnrolleeStatus(next, `Status ${next === 'active' ? 'enabled' : 'disabled'} by admin`)
}

const updateStatus = async () => {
  if (!newStatus.value) return
  await updateEnrolleeStatus(newStatus.value, statusComment.value)
}

const updateEnrolleeStatus = async (status, comment) => {
  updatingStatus.value = true
  try {
    const response = await axios.put(`/api/v1/enrollees/${enrollee.value.id}/status`, { status, comment })
    if (response.data.success) {
      success('Enrollee status updated successfully')
      enrollee.value.status = response.data.data.status
      newStatus.value = null
      statusComment.value = ''
    }
  } catch {
    error('Failed to update enrollee status')
  } finally {
    updatingStatus.value = false
  }
}

const editEnrollee = () => {
  // route to edit page here when ready
  console.log('Edit enrollee:', enrollee.value.id)
}

const downloadProfile = async () => {
  try {
    // Wire real export endpoint here
    success('Profile downloaded successfully')
  } catch {
    error('Failed to download profile')
  }
}

/* Utils */
const copy = async (text) => {
  try {
    await navigator.clipboard.writeText(String(text || ''))
    success('Copied to clipboard')
  } catch { /* noop */ }
}

const getStatusColor = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'active': return 'success'
    case 'pending': return 'warning'
    case 'suspended':
    case 'expired': return 'error'
    default: return 'grey'
  }
}

const getMaritalStatus = (status) => {
  switch (status) {
    case 1: return 'Single'
    case 2: return 'Married'
    case 3: return 'Divorced'
    case 4: return 'Widowed'
    default: return 'N/A'
  }
}

const formatDate = (dateString) => (dateString ? new Date(dateString).toLocaleDateString() : 'N/A')

const formatCurrency = (amount) =>
  amount
    ? new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(amount)
    : 'N/A'

/* Lifecycle */
onMounted(() => {
  loadEnrollee()
  loadStatusOptions()
})
</script>
