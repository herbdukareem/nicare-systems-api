<template>
  <v-dialog v-model="dialog" max-width="800px" persistent>
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between">
        <span class="text-h5">Create New Task</span>
        <v-btn icon="mdi-close" variant="text" @click="close" />
      </v-card-title>
      
      <v-divider />
      
      <v-card-text class="pa-6">
        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="task.title"
                label="Task Title"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
            </v-col>
            
            <v-col cols="12">
              <v-textarea
                v-model="task.description"
                label="Description"
                variant="outlined"
                rows="3"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-select
                v-model="task.priority"
                label="Priority"
                :items="priorityOptions"
                variant="outlined"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-select
                v-model="task.type"
                label="Type"
                :items="typeOptions"
                variant="outlined"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-text-field
                v-model="task.due_date"
                label="Due Date"
                type="date"
                variant="outlined"
              />
            </v-col>
            
            <v-col cols="12" md="6">
              <v-text-field
                v-model="task.story_points"
                label="Story Points"
                type="number"
                variant="outlined"
                min="1"
                max="100"
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
          Create Task
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

const emit = defineEmits(['update:modelValue', 'task-created'])

const dialog = ref(false)
const valid = ref(false)
const loading = ref(false)
const form = ref(null)

const task = ref({
  title: '',
  description: '',
  priority: 'medium',
  type: 'task',
  due_date: '',
  story_points: null
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

const typeOptions = [
  { title: 'Task', value: 'task' },
  { title: 'Bug', value: 'bug' },
  { title: 'Feature', value: 'feature' },
  { title: 'Improvement', value: 'improvement' },
  { title: 'Research', value: 'research' }
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
  task.value = {
    title: '',
    description: '',
    priority: 'medium',
    type: 'task',
    due_date: '',
    story_points: null
  }
  form.value?.resetValidation()
}

const save = async () => {
  if (!form.value?.validate()) return
  
  loading.value = true
  
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    emit('task-created', {
      ...task.value,
      id: Date.now(),
      status: 'todo',
      created_at: new Date().toISOString()
    })
    
    close()
  } catch (error) {
    console.error('Error creating task:', error)
  } finally {
    loading.value = false
  }
}
</script>
