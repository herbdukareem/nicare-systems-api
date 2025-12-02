<template>
  <v-dialog v-model="dialog" max-width="600" persistent>
    <v-card>
      <v-card-title class="tw-bg-orange-50 tw-text-orange-800 tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-hospital</v-icon>
        Discharge Patient
      </v-card-title>

      <v-card-text class="tw-p-6">
        <!-- Patient Info Summary -->
        <v-alert v-if="admission" type="info" variant="tonal" class="tw-mb-4">
          <div class="tw-font-medium">{{ admission.enrollee?.full_name }}</div>
          <div class="tw-text-sm">{{ admission.enrollee?.nicare_number }} | Admitted: {{ formatDate(admission.admission_date) }}</div>
          <div class="tw-text-sm">Principal Diagnosis: {{ admission.principal_diagnosis_code }} - {{ admission.principal_diagnosis_description }}</div>
        </v-alert>

        <v-form ref="formRef" v-model="formValid" @submit.prevent="submitForm">
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.discharge_date"
                type="datetime-local"
                label="Discharge Date/Time *"
                variant="outlined"
                :rules="[rules.required]"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="form.discharge_type"
                :items="dischargeTypes"
                item-title="text"
                item-value="value"
                label="Discharge Type *"
                variant="outlined"
                :rules="[rules.required]"
              />
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="form.final_diagnosis_summary"
                label="Final Diagnosis Summary"
                variant="outlined"
                rows="3"
              />
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="form.discharge_notes"
                label="Discharge Notes"
                variant="outlined"
                rows="2"
              />
            </v-col>

            <!-- Total Stay Summary -->
            <v-col cols="12">
              <v-card variant="outlined" class="tw-p-4">
                <div class="tw-text-sm tw-text-gray-600">Total Length of Stay</div>
                <div class="tw-text-2xl tw-font-bold tw-text-primary">{{ lengthOfStay }} days</div>
                <div v-if="admission?.planned_ward_days" class="tw-text-sm tw-text-gray-500">
                  Planned: {{ admission.planned_ward_days }} days
                  <v-chip v-if="lengthOfStay > admission.planned_ward_days" size="x-small" color="warning" class="tw-ml-2">
                    Extended Stay (+{{ lengthOfStay - admission.planned_ward_days }})
                  </v-chip>
                </div>
              </v-card>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions class="tw-px-6 tw-pb-4">
        <v-spacer />
        <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
        <v-btn color="warning" :loading="submitting" :disabled="!formValid" @click="submitForm">
          Discharge Patient
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { claimsAutomationAPI } from '@/js/utils/api'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  modelValue: Boolean,
  admission: Object
})
const emit = defineEmits(['update:modelValue', 'discharged'])

const dialog = ref(props.modelValue)
watch(() => props.modelValue, (val) => dialog.value = val)
watch(dialog, (val) => emit('update:modelValue', val))

const toast = useToast()
const formRef = ref(null)
const formValid = ref(false)
const submitting = ref(false)

const dischargeTypes = [
  { value: 'normal', text: 'Normal Discharge' },
  { value: 'transfer', text: 'Transfer to Another Facility' },
  { value: 'against_advice', text: 'Discharge Against Medical Advice' },
  { value: 'deceased', text: 'Deceased' },
  { value: 'absconded', text: 'Absconded' }
]

const form = ref({
  discharge_date: new Date().toISOString().slice(0, 16),
  discharge_type: 'normal',
  final_diagnosis_summary: '',
  discharge_notes: ''
})

const rules = { required: v => !!v || 'This field is required' }

const lengthOfStay = computed(() => {
  if (!props.admission?.admission_date) return 0
  const admDate = new Date(props.admission.admission_date)
  const disDate = form.value.discharge_date ? new Date(form.value.discharge_date) : new Date()
  return Math.ceil((disDate - admDate) / (1000 * 60 * 60 * 24))
})

const formatDate = (date) => new Date(date).toLocaleDateString()

const submitForm = async () => {
  if (!formRef.value?.validate() || !props.admission) return
  submitting.value = true
  try {
    await claimsAutomationAPI.dischargePatient(props.admission.id, form.value)
    toast.add({ severity: 'success', summary: 'Success', detail: 'Patient discharged successfully', life: 3000 })
    emit('discharged')
  } catch (error) {
    const msg = error.response?.data?.message || 'Failed to discharge patient'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 })
  } finally { submitting.value = false }
}

const closeDialog = () => { dialog.value = false }
</script>

