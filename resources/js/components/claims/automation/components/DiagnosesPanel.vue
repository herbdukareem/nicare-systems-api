<template>
  <v-card>
    <v-card-title class="tw-bg-purple-50 tw-text-purple-800 tw-flex tw-items-center tw-justify-between">
      <div class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-stethoscope</v-icon>
        Diagnoses
      </div>
      <v-btn size="small" color="purple" variant="tonal" @click="showAddDialog = true">
        <v-icon left size="small">mdi-plus</v-icon>
        Add Diagnosis
      </v-btn>
    </v-card-title>

    <v-card-text class="tw-p-0">
      <v-list v-if="claim?.diagnoses?.length">
        <v-list-item
          v-for="diagnosis in claim.diagnoses"
          :key="diagnosis.id"
          class="tw-border-b"
        >
          <template #prepend>
            <v-chip :color="getDiagnosisColor(diagnosis.type)" size="small" class="tw-mr-2">
              {{ diagnosis.type }}
            </v-chip>
          </template>

          <v-list-item-title class="tw-font-medium">
            <span class="tw-font-mono tw-bg-gray-100 tw-px-2 tw-py-0.5 tw-rounded">{{ diagnosis.icd_10_code }}</span>
            {{ diagnosis.icd_10_description }}
          </v-list-item-title>

          <v-list-item-subtitle v-if="diagnosis.illness_description">
            {{ diagnosis.illness_description }}
          </v-list-item-subtitle>

          <template #append>
            <div class="tw-flex tw-items-center tw-gap-2">
              <v-chip v-if="diagnosis.doctor_validated" size="x-small" color="success" variant="outlined">
                <v-icon size="small">mdi-check</v-icon> Validated
              </v-chip>
              <v-chip v-if="diagnosis.requires_pa" size="x-small" color="warning" variant="outlined">
                <v-icon size="small">mdi-alert</v-icon> PA Required
              </v-chip>
            </div>
          </template>
        </v-list-item>
      </v-list>

      <div v-else class="tw-p-6 tw-text-center tw-text-gray-500">
        No diagnoses added yet
      </div>
    </v-card-text>

    <!-- Add Diagnosis Dialog -->
    <v-dialog v-model="showAddDialog" max-width="500">
      <v-card>
        <v-card-title class="tw-bg-purple-50 tw-text-purple-800">Add Diagnosis</v-card-title>
        <v-card-text class="tw-p-4">
          <v-form ref="formRef" v-model="formValid">
            <v-text-field
              v-model="newDiagnosis.icd_10_code"
              label="ICD-10 Code *"
              variant="outlined"
              placeholder="e.g., B50.9"
              :rules="[v => !!v || 'Required']"
              class="tw-mb-3"
            />
            <v-text-field
              v-model="newDiagnosis.description"
              label="Description *"
              variant="outlined"
              :rules="[v => !!v || 'Required']"
              class="tw-mb-3"
            />
            <v-checkbox
              v-model="newDiagnosis.is_complication"
              label="This is a complication of the principal diagnosis"
              color="warning"
              hide-details
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showAddDialog = false">Cancel</v-btn>
          <v-btn color="purple" :loading="adding" :disabled="!formValid" @click="addDiagnosis">
            Add Diagnosis
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref } from 'vue'
import { claimsAutomationAPI } from '@/js/utils/api'
import { useToast } from 'primevue/usetoast'

const props = defineProps({ claim: Object })
const emit = defineEmits(['diagnosis-added'])

const toast = useToast()
const showAddDialog = ref(false)
const formRef = ref(null)
const formValid = ref(false)
const adding = ref(false)

const newDiagnosis = ref({
  icd_10_code: '',
  description: '',
  is_complication: false
})

const getDiagnosisColor = (type) => ({
  primary: 'blue',
  secondary: 'teal',
  complication: 'orange'
}[type] || 'grey')

const addDiagnosis = async () => {
  if (!formRef.value?.validate() || !props.claim) return
  adding.value = true
  try {
    await claimsAutomationAPI.addDiagnosis(props.claim.id, newDiagnosis.value)
    toast.add({ severity: 'success', summary: 'Success', detail: 'Diagnosis added', life: 3000 })
    showAddDialog.value = false
    newDiagnosis.value = { icd_10_code: '', description: '', is_complication: false }
    emit('diagnosis-added')
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to add', life: 5000 })
  } finally { adding.value = false }
}
</script>

