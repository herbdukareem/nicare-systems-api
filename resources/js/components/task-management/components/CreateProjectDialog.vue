<template>
  <v-dialog v-model="dialog" max-width="800px" persistent>
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between">
        <span class="text-h5">Create New Project</span>
        <v-btn icon="mdi-close" variant="text" @click="close" />
      </v-card-title>
      
      <v-divider />
      
      <v-card-text class="pa-6">
        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="project.name"
                label="Project Name"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
            </v-col>
            
            <v-col cols="12">
              <v-textarea
                v-model="project.description"
                label="Description"
                variant="outlined"
                rows="3"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-select
                v-model="project.priority"
                label="Priority"
                :items="priorityOptions"
                variant="outlined"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-text-field
                v-model="project.budget"
                label="Budget"
                type="number"
                variant="outlined"
                prefix="₦"
                min="0"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-text-field
                v-model="project.start_date"
                label="Start Date"
                type="date"
                variant="outlined"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-text-field
                v-model="project.end_date"
                label="End Date"
                type="date"
                variant="outlined"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
      
      <v-divider />
      
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="close">Cancel</v-btn>
        <v-btn
          color="primary"
          :loading="loading"
          :disabled="!valid"
          @click="save"
        >
          Create Project
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'project-created'])

const dialog = ref(false)
const valid = ref(false)
const loading = ref(false)
const form = ref(null)

const project = ref({
  name: '',
  description: '',
  priority: 'medium',
  budget: null,
  start_date: '',
  end_date: ''
})

const rules = {
  required: value => !!value || 'This field is required'
}

const priorityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
  { title: 'Critical', value: 'critical' }
]

watch(() => props.modelValue, (val) => {
  dialog.value = val
})

watch(dialog, (val) => {
  emit('update:modelValue', val)
  if (!val) {
    resetForm()
  }
})

const close = () => {
  dialog.value = false
}

const resetForm = () => {
  project.value = {
    name: '',
    description: '',
    priority: 'medium',
    budget: null,
    start_date: '',
    end_date: ''
  }
  form.value?.resetValidation()
}

const save = async () => {
  if (!form.value?.validate()) return
  
  loading.value = true
  
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    emit('project-created', {
      ...project.value,
      id: Date.now(),
      status: 'planning',
      progress_percentage: 0,
      created_at: new Date().toISOString()
    })
    
    close()
  } catch (error) {
    console.error('Error creating project:', error)
  } finally {
    loading.value = false
  }
}
</script>
