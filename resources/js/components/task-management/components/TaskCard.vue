<template>
  <v-card
    class="task-card"
    variant="outlined"
    hover
    @click="$emit('click')"
  >
    <v-card-text class="pa-3">
      <!-- Task Header -->
      <div class="d-flex align-start justify-space-between mb-2">
        <div class="flex-grow-1">
          <div class="d-flex align-center mb-1">
            <v-chip
              :color="priorityColor"
              size="x-small"
              variant="tonal"
              class="mr-2"
            >
              {{ task.priority }}
            </v-chip>
            <span class="text-caption text-medium-emphasis">{{ task.task_number }}</span>
          </div>
          <h4 class="text-subtitle-2 font-weight-medium line-clamp-2">
            {{ task.title }}
          </h4>
        </div>
        
        <v-menu>
          <template #activator="{ props }">
            <v-btn
              icon="mdi-dots-vertical"
              size="small"
              variant="text"
              v-bind="props"
              @click.stop
            />
          </template>
          <v-list density="compact">
            <v-list-item
              v-for="status in availableStatuses"
              :key="status.value"
              @click="changeStatus(status.value)"
            >
              <template #prepend>
                <v-icon :color="status.color">{{ status.icon }}</v-icon>
              </template>
              <v-list-item-title>{{ status.title }}</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-menu>
      </div>

      <!-- Task Description -->
      <p
        v-if="task.description"
        class="text-caption text-medium-emphasis line-clamp-2 mb-2"
      >
        {{ task.description }}
      </p>

      <!-- Task Labels -->
      <div v-if="task.labels && task.labels.length" class="mb-2">
        <v-chip
          v-for="label in task.labels.slice(0, 2)"
          :key="label"
          size="x-small"
          variant="outlined"
          class="mr-1"
        >
          {{ label }}
        </v-chip>
        <span v-if="task.labels.length > 2" class="text-caption text-medium-emphasis">
          +{{ task.labels.length - 2 }} more
        </span>
      </div>

      <!-- Task Footer -->
      <div class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <!-- Assignee Avatar -->
          <v-avatar
            v-if="task.assignee"
            size="24"
            class="mr-2"
          >
            <v-img
              v-if="task.assignee.avatar"
              :src="task.assignee.avatar"
              :alt="task.assignee.name"
            />
            <span v-else class="text-caption">
              {{ getInitials(task.assignee.name) }}
            </span>
          </v-avatar>

          <!-- Due Date -->
          <div v-if="task.due_date" class="d-flex align-center">
            <v-icon
              :color="isOverdue ? 'error' : 'medium-emphasis'"
              size="16"
              class="mr-1"
            >
              mdi-calendar
            </v-icon>
            <span
              :class="[
                'text-caption',
                isOverdue ? 'text-error' : 'text-medium-emphasis'
              ]"
            >
              {{ formatDate(task.due_date) }}
            </span>
          </div>
        </div>

        <div class="d-flex align-center">
          <!-- Story Points -->
          <v-chip
            v-if="task.story_points"
            size="x-small"
            variant="tonal"
            color="primary"
            class="mr-1"
          >
            {{ task.story_points }}
          </v-chip>

          <!-- Comments Count -->
          <div v-if="task.comments_count" class="d-flex align-center">
            <v-icon size="16" class="mr-1">mdi-comment-outline</v-icon>
            <span class="text-caption">{{ task.comments_count }}</span>
          </div>
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  task: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['click', 'status-changed'])

// Priority colors
const priorityColors = {
  low: 'success',
  medium: 'warning',
  high: 'error',
  critical: 'error'
}

const priorityColor = computed(() => priorityColors[props.task.priority] || 'grey')

// Available status transitions
const availableStatuses = [
  { value: 'todo', title: 'To Do', icon: 'mdi-clipboard-list-outline', color: 'grey' },
  { value: 'in_progress', title: 'In Progress', icon: 'mdi-clock-outline', color: 'blue' },
  { value: 'review', title: 'Review', icon: 'mdi-eye-outline', color: 'orange' },
  { value: 'testing', title: 'Testing', icon: 'mdi-test-tube', color: 'purple' },
  { value: 'done', title: 'Done', icon: 'mdi-check-circle-outline', color: 'green' },
  { value: 'cancelled', title: 'Cancelled', icon: 'mdi-close-circle-outline', color: 'red' }
]

// Check if task is overdue
const isOverdue = computed(() => {
  if (!props.task.due_date || props.task.status === 'done' || props.task.status === 'cancelled') {
    return false
  }
  return new Date(props.task.due_date) < new Date()
})

// Helper functions
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
    day: 'numeric'
  })
}

const changeStatus = (newStatus) => {
  emit('status-changed', props.task, newStatus)
}
</script>

<style scoped>
.task-card {
  cursor: pointer;
  transition: all 0.2s ease;
}

.task-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
