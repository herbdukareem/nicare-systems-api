<template>
  <v-dialog v-model="dialog" max-width="900px" persistent>
    <v-card v-if="project">
      <v-card-title class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <v-icon class="mr-3" color="primary">mdi-folder</v-icon>
          <span class="text-h6">{{ project.name }}</span>
        </div>
        <v-btn icon="mdi-close" variant="text" @click="close" />
      </v-card-title>
      
      <v-divider />
      
      <v-card-text class="pa-0">
        <v-row no-gutters>
          <!-- Main Content -->
          <v-col cols="12" md="8" class="pa-6">
            <div class="mb-4">
              <h4 class="text-subtitle-1 mb-2">Description</h4>
              <p class="text-body-2">
                {{ project.description || 'No description provided' }}
              </p>
            </div>
            
            <div class="mb-4">
              <h4 class="text-subtitle-1 mb-2">Progress</h4>
              <div class="d-flex align-center mb-2">
                <span class="text-body-2 mr-2">{{ project.progress_percentage || 0 }}%</span>
                <v-progress-linear
                  :model-value="project.progress_percentage || 0"
                  :color="getStatusColor(project.status)"
                  height="8"
                  rounded
                  class="flex-grow-1"
                />
              </div>
            </div>
            
            <div class="mb-4">
              <h4 class="text-subtitle-1 mb-2">Tasks Overview</h4>
              <v-row>
                <v-col cols="6" md="3">
                  <v-card variant="tonal" color="primary">
                    <v-card-text class="text-center pa-3">
                      <div class="text-h6">{{ project.total_tasks || 0 }}</div>
                      <div class="text-caption">Total Tasks</div>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="6" md="3">
                  <v-card variant="tonal" color="success">
                    <v-card-text class="text-center pa-3">
                      <div class="text-h6">{{ project.completed_tasks || 0 }}</div>
                      <div class="text-caption">Completed</div>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="6" md="3">
                  <v-card variant="tonal" color="warning">
                    <v-card-text class="text-center pa-3">
                      <div class="text-h6">{{ project.active_tasks || 0 }}</div>
                      <div class="text-caption">Active</div>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="6" md="3">
                  <v-card variant="tonal" color="error">
                    <v-card-text class="text-center pa-3">
                      <div class="text-h6">{{ project.overdue_tasks || 0 }}</div>
                      <div class="text-caption">Overdue</div>
                    </v-card-text>
                  </v-card>
                </v-col>
              </v-row>
            </div>
            
            <div v-if="project.notes" class="mb-4">
              <h4 class="text-subtitle-1 mb-2">Notes</h4>
              <p class="text-body-2">{{ project.notes }}</p>
            </div>
          </v-col>
          
          <!-- Sidebar -->
          <v-col cols="12" md="4" class="border-s">
            <div class="pa-6">
              <div class="mb-4">
                <h4 class="text-subtitle-1 mb-3">Details</h4>
                
                <div class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Status</span>
                  <v-chip
                    :color="getStatusColor(project.status)"
                    size="small"
                    variant="tonal"
                    class="mt-1"
                  >
                    {{ formatStatus(project.status) }}
                  </v-chip>
                </div>
                
                <div class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Priority</span>
                  <v-chip
                    :color="getPriorityColor(project.priority)"
                    size="small"
                    variant="tonal"
                    class="mt-1"
                  >
                    {{ project.priority }}
                  </v-chip>
                </div>
                
                <div v-if="project.project_manager" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Project Manager</span>
                  <div class="d-flex align-center mt-1">
                    <v-avatar size="24" class="mr-2">
                      <span class="text-caption">
                        {{ getInitials(project.project_manager.name) }}
                      </span>
                    </v-avatar>
                    <span class="text-body-2">{{ project.project_manager.name }}</span>
                  </div>
                </div>
                
                <div v-if="project.budget" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Budget</span>
                  <span class="text-body-2 mt-1">₦{{ formatCurrency(project.budget) }}</span>
                </div>
                
                <div v-if="project.spent_amount" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Spent</span>
                  <span class="text-body-2 mt-1">₦{{ formatCurrency(project.spent_amount) }}</span>
                </div>
                
                <div v-if="project.start_date" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Start Date</span>
                  <span class="text-body-2 mt-1">{{ formatDate(project.start_date) }}</span>
                </div>
                
                <div v-if="project.end_date" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">End Date</span>
                  <div class="d-flex align-center mt-1">
                    <v-icon
                      :color="isOverdue ? 'error' : 'medium-emphasis'"
                      size="16"
                      class="mr-1"
                    >
                      mdi-calendar
                    </v-icon>
                    <span
                      :class="[
                        'text-body-2',
                        isOverdue ? 'text-error' : ''
                      ]"
                    >
                      {{ formatDate(project.end_date) }}
                    </span>
                  </div>
                </div>
              </div>
              
              <v-divider class="mb-4" />
              
              <div class="mb-4">
                <h4 class="text-subtitle-1 mb-3">Actions</h4>
                <v-btn
                  block
                  variant="outlined"
                  prepend-icon="mdi-pencil"
                  class="mb-2"
                  @click="editProject"
                >
                  Edit Project
                </v-btn>
                <v-btn
                  block
                  variant="outlined"
                  prepend-icon="mdi-plus"
                  class="mb-2"
                  @click="addTask"
                >
                  Add Task
                </v-btn>
                <v-btn
                  block
                  variant="outlined"
                  prepend-icon="mdi-eye"
                  class="mb-2"
                  @click="viewTasks"
                >
                  View Tasks
                </v-btn>
                <v-btn
                  block
                  variant="outlined"
                  color="error"
                  prepend-icon="mdi-delete"
                  @click="deleteProject"
                >
                  Delete
                </v-btn>
              </div>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  project: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'project-updated'])

const dialog = ref(false)

const isOverdue = computed(() => {
  if (!props.project?.end_date || props.project.status === 'completed' || props.project.status === 'cancelled') {
    return false
  }
  return new Date(props.project.end_date) < new Date()
})

watch(() => props.modelValue, (val) => {
  dialog.value = val
})

watch(dialog, (val) => {
  emit('update:modelValue', val)
})

const close = () => {
  dialog.value = false
}

const getStatusColor = (status) => {
  const colors = {
    planning: 'grey',
    active: 'primary',
    on_hold: 'warning',
    completed: 'success',
    cancelled: 'error'
  }
  return colors[status] || 'grey'
}

const getPriorityColor = (priority) => {
  const colors = {
    low: 'success',
    medium: 'warning',
    high: 'error',
    critical: 'error'
  }
  return colors[priority] || 'grey'
}

const formatStatus = (status) => {
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const getInitials = (name) => {
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-NG').format(amount)
}

const editProject = () => {
  console.log('Edit project:', props.project)
}

const addTask = () => {
  console.log('Add task to project:', props.project)
}

const viewTasks = () => {
  console.log('View tasks for project:', props.project)
}

const deleteProject = () => {
  console.log('Delete project:', props.project)
}
</script>

<style scoped>
.detail-item {
  display: flex;
  flex-direction: column;
}
</style>
