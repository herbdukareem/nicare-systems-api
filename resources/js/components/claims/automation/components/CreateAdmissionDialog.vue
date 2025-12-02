<template>
  <v-dialog v-model="dialog" max-width="700" persistent>
    <v-card>
      <v-card-title class="tw-bg-blue-50 tw-text-blue-800 tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-hospital-building</v-icon>
        Create New Admission
      </v-card-title>

      <v-card-text class="tw-p-6">
        <v-form ref="formRef" v-model="formValid" @submit.prevent="submitForm">
          <v-row>
            <!-- Patient Selection -->
            <v-col cols="12">
              <v-autocomplete
                v-model="form.enrollee_id"
                :items="enrollees"
                item-title="full_name"
                item-value="id"
                label="Select Patient *"
                variant="outlined"
                :loading="loadingEnrollees"
                :rules="[rules.required]"
                @update:search="searchEnrollees"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <v-list-item-subtitle>{{ item.raw.nicare_number }}</v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <!-- Facility Selection -->
            <v-col cols="12" md="6">
              <v-autocomplete
                v-model="form.facility_id"
                :items="facilities"
                item-title="name"
                item-value="id"
                label="Facility *"
                variant="outlined"
                :loading="loadingFacilities"
                :rules="[rules.required]"
              />
            </v-col>

            <!-- Admission Type -->
            <v-col cols="12" md="6">
              <v-select
                v-model="form.admission_type"
                :items="admissionTypes"
                label="Admission Type *"
                variant="outlined"
                :rules="[rules.required]"
              />
            </v-col>

            <!-- Diagnosis Code -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.principal_diagnosis_code"
                label="Principal Diagnosis Code (ICD-10)"
                variant="outlined"
                placeholder="e.g., O82.0"
              />
            </v-col>

            <!-- Diagnosis Description -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.principal_diagnosis_description"
                label="Diagnosis Description"
                variant="outlined"
                placeholder="e.g., Cesarean Section"
              />
            </v-col>

            <!-- Attending Physician -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.attending_physician_name"
                label="Attending Physician *"
                variant="outlined"
                :rules="[rules.required]"
              />
            </v-col>

            <!-- Physician License -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.attending_physician_license"
                label="License Number"
                variant="outlined"
              />
            </v-col>

            <!-- Ward Type -->
            <v-col cols="12" md="6">
              <v-select
                v-model="form.ward_type"
                :items="wardTypes"
                label="Ward Type"
                variant="outlined"
              />
            </v-col>

            <!-- Planned Ward Days -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.planned_ward_days"
                label="Planned Ward Days"
                type="number"
                min="1"
                variant="outlined"
              />
            </v-col>

            <!-- Admission Reason -->
            <v-col cols="12">
              <v-textarea
                v-model="form.admission_reason"
                label="Admission Reason"
                variant="outlined"
                rows="2"
              />
            </v-col>

            <!-- Optional: Link to Referral or PA Code -->
            <v-col cols="12" md="6">
              <v-autocomplete
                v-model="form.referral_id"
                :items="referrals"
                item-title="referral_code"
                item-value="id"
                label="Link to Referral (Optional)"
                variant="outlined"
                clearable
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-autocomplete
                v-model="form.pa_code_id"
                :items="paCodes"
                item-title="pa_code"
                item-value="id"
                label="Link to PA Code (Optional)"
                variant="outlined"
                clearable
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions class="tw-px-6 tw-pb-4">
        <v-spacer />
        <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
        <v-btn color="primary" :loading="submitting" :disabled="!formValid" @click="submitForm">
          Create Admission
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { claimsAutomationAPI, enrolleeAPI, facilityAPI, pasAPI } from '@/js/utils/api'
import { useToast } from 'primevue/usetoast'

const props = defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue', 'created'])

const dialog = ref(props.modelValue)
watch(() => props.modelValue, (val) => dialog.value = val)
watch(dialog, (val) => emit('update:modelValue', val))

const toast = useToast()
const formRef = ref(null)
const formValid = ref(false)
const submitting = ref(false)

// Data
const enrollees = ref([])
const facilities = ref([])
const referrals = ref([])
const paCodes = ref([])
const loadingEnrollees = ref(false)
const loadingFacilities = ref(false)

const admissionTypes = ['elective', 'emergency', 'transfer']
const wardTypes = ['general', 'private', 'icu', 'maternity', 'pediatric', 'surgical']

const form = ref({
  enrollee_id: null,
  facility_id: null,
  admission_type: 'elective',
  principal_diagnosis_code: '',
  principal_diagnosis_description: '',
  attending_physician_name: '',
  attending_physician_license: '',
  ward_type: 'general',
  planned_ward_days: 3,
  admission_reason: '',
  referral_id: null,
  pa_code_id: null
})

const rules = { required: v => !!v || 'This field is required' }

const searchEnrollees = async (query) => {
  if (!query || query.length < 2) return
  loadingEnrollees.value = true
  try {
    const res = await enrolleeAPI.getAll({ search: query, per_page: 20 })
    enrollees.value = res.data.data || []
  } catch (e) { console.error(e) }
  finally { loadingEnrollees.value = false }
}

const fetchFacilities = async () => {
  loadingFacilities.value = true
  try {
    const res = await facilityAPI.getAll({ per_page: 100 })
    facilities.value = res.data.data || []
  } catch (e) { console.error(e) }
  finally { loadingFacilities.value = false }
}

const fetchReferralsAndPACodes = async () => {
  try {
    const [refRes, paRes] = await Promise.all([
      pasAPI.getReferrals({ status: 'approved', per_page: 50 }),
      pasAPI.getPACodes({ status: 'active', per_page: 50 })
    ])
    referrals.value = refRes.data.data || []
    paCodes.value = paRes.data.data || []
  } catch (e) { console.error(e) }
}

const submitForm = async () => {
  if (!formRef.value?.validate()) return
  submitting.value = true
  try {
    await claimsAutomationAPI.createAdmission(form.value)
    toast.add({ severity: 'success', summary: 'Success', detail: 'Admission created successfully', life: 3000 })
    emit('created')
  } catch (error) {
    const msg = error.response?.data?.message || 'Failed to create admission'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 })
  } finally { submitting.value = false }
}

const closeDialog = () => { dialog.value = false }

onMounted(() => { fetchFacilities(); fetchReferralsAndPACodes() })
</script>

