<template>
  <v-dialog v-model="dialog" max-width="900px" persistent>
    <v-card v-if="task">
      <v-card-title class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <v-chip size="small" variant="outlined" class="mr-3">
            {{ task.task_number }}
          </v-chip>
          <span class="text-h6">{{ task.title }}</span>
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
                {{ task.description || 'No description provided' }}
              </p>
            </div>
            
            <div v-if="task.acceptance_criteria" class="mb-4">
              <h4 class="text-subtitle-1 mb-2">Acceptance Criteria</h4>
              <p class="text-body-2">{{ task.acceptance_criteria }}</p>
            </div>
            
            <div class="mb-4">
              <h4 class="text-subtitle-1 mb-2">Comments</h4>
              <div class="comments-section">
                <div
                  v-for="comment in mockComments"
                  :key="comment.id"
                  class="comment mb-3"
                >
                  <div class="d-flex align-start">
                    <v-avatar size="32" class="mr-3">
                      <span class="text-caption">{{ getInitials(comment.user.name) }}</span>
                    </v-avatar>
                    <div class="flex-grow-1">
                      <div class="d-flex align-center mb-1">
                        <span class="font-weight-medium mr-2">{{ comment.user.name }}</span>
                        <span class="text-caption text-medium-emphasis">
                          {{ formatDate(comment.created_at) }}
                        </span>
                      </div>
                      <p class="text-body-2">{{ comment.comment }}</p>
                    </div>
                  </div>
                </div>
                
                <!-- Add Comment -->
                <div class="d-flex align-start mt-4">
                  <v-avatar size="32" class="mr-3">
                    <span class="text-caption">ME</span>
                  </v-avatar>
                  <v-textarea
                    v-model="newComment"
                    placeholder="Add a comment..."
                    variant="outlined"
                    rows="2"
                    class="flex-grow-1"
                  />
                </div>
                <div class="d-flex justify-end mt-2">
                  <v-btn color="primary" size="small" @click="addComment">
                    Add Comment
                  </v-btn>
                </div>
              </div>
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
                    :color="getStatusColor(task.status)"
                    size="small"
                    variant="tonal"
                    class="mt-1"
                  >
                    {{ formatStatus(task.status) }}
                  </v-chip>
                </div>
                
                <div class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Priority</span>
                  <v-chip
                    :color="getPriorityColor(task.priority)"
                    size="small"
                    variant="tonal"
                    class="mt-1"
                  >
                    {{ task.priority }}
                  </v-chip>
                </div>
                
                <div class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Type</span>
                  <v-chip
                    size="small"
                    variant="outlined"
                    class="mt-1"
                  >
                    {{ formatType(task.type) }}
                  </v-chip>
                </div>
                
                <div v-if="task.assignee" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Assignee</span>
                  <div class="d-flex align-center mt-1">
                    <v-avatar size="24" class="mr-2">
                      <span class="text-caption">
                        {{ getInitials(task.assignee.name) }}
                      </span>
                    </v-avatar>
                    <span class="text-body-2">{{ task.assignee.name }}</span>
                  </div>
                </div>
                
                <div v-if="task.due_date" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Due Date</span>
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
                      {{ formatDate(task.due_date) }}
                    </span>
                  </div>
                </div>
                
                <div v-if="task.story_points" class="detail-item mb-3">
                  <span class="text-caption text-medium-emphasis">Story Points</span>
                  <v-chip
                    size="small"
                    variant="tonal"
                    color="primary"
                    class="mt-1"
                  >
                    {{ task.story_points }}
                  </v-chip>
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
                  @click="editTask"
                >
                  Edit Task
                </v-btn>
                <v-btn
                  block
                  variant="outlined"
                  prepend-icon="mdi-account-plus"
                  class="mb-2"
                  @click="assignTask"
                >
                  Assign
                </v-btn>
                <v-btn
                  block
                  variant="outlined"
                  color="error"
                  prepend-icon="mdi-delete"
                  @click="deleteTask"
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
  task: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'task-updated'])

const dialog = ref(false)
const newComment = ref('')

// Mock comments data
const mockComments = ref([
  {
    id: 1,
    user: { name: 'John Doe' },
    comment: 'This task looks good to me. Let me know if you need any help.',
    created_at: '2024-01-15T10:30:00Z'
  },
  {
    id: 2,
    user: { name: 'Jane Smith' },
    comment: 'I have some concerns about the timeline. Can we discuss?',
    created_at: '2024-01-15T14:20:00Z'
  }
])

const isOverdue = computed(() => {
  if (!props.task?.due_date || props.task.status === 'done' || props.task.status === 'cancelled') {
    return false
  }
  return new Date(props.task.due_date) < new Date()
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
    todo: 'grey',
    in_progress: 'blue',
    review: 'orange',
    testing: 'purple',
    done: 'green',
    cancelled: 'red'
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

const formatType = (type) => {
  return type.charAt(0).toUpperCase() + type.slice(1)
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

const addComment = () => {
  if (!newComment.value.trim()) return
  
  mockComments.value.push({
    id: Date.now(),
    user: { name: 'Current User' },
    comment: newComment.value,
    created_at: new Date().toISOString()
  })
  
  newComment.value = ''
}

const editTask = () => {
  console.log('Edit task:', props.task)
}

const assignTask = () => {
  console.log('Assign task:', props.task)
}

const deleteTask = () => {
  console.log('Delete task:', props.task)
}
</script>

<style scoped>
.detail-item {
  display: flex;
  flex-direction: column;
}

.comments-section {
  max-height: 400px;
  overflow-y: auto;
}

.comment {
  padding: 12px;
  border-radius: 8px;
  background-color: rgba(var(--v-theme-surface-variant), 0.3);
}
</style>
