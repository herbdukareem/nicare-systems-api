<template>
  <div class="kanban-board">
    <v-row class="fill-height">
      <v-col
        v-for="(column, status) in columns"
        :key="status"
        cols="12"
        md="2"
        class="kanban-column"
      >
        <v-card class="fill-height" variant="outlined">
          <v-card-title class="d-flex align-center justify-space-between pa-3">
            <div class="d-flex align-center">
              <v-icon :color="column.color" class="mr-2">{{ column.icon }}</v-icon>
              <span class="text-subtitle-1 font-weight-medium">{{ column.title }}</span>
            </div>
            <v-chip
              :color="column.color"
              variant="tonal"
              size="small"
            >
              {{ tasksByStatus[status]?.length || 0 }}
            </v-chip>
          </v-card-title>
          
          <v-divider />
          
          <v-card-text class="pa-2" style="min-height: 500px;">
            <div
              v-if="loading"
              class="d-flex justify-center align-center"
              style="height: 200px;"
            >
              <v-progress-circular indeterminate color="primary" />
            </div>
            
            <div v-else>
              <TaskCard
                v-for="task in tasksByStatus[status]"
                :key="task.id"
                :task="task"
                class="mb-2"
                @click="$emit('task-clicked', task)"
                @status-changed="handleStatusChange"
              />
              
              <div
                v-if="!tasksByStatus[status]?.length"
                class="text-center text-medium-emphasis py-8"
              >
                <v-icon size="48" class="mb-2">mdi-clipboard-outline</v-icon>
                <div>No tasks</div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import TaskCard from './TaskCard.vue'

const props = defineProps({
  tasks: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['task-clicked', 'task-updated'])

// Kanban columns configuration
const columns = {
  todo: {
    title: 'To Do',
    icon: 'mdi-clipboard-list-outline',
    color: 'grey'
  },
  in_progress: {
    title: 'In Progress',
    icon: 'mdi-clock-outline',
    color: 'blue'
  },
  review: {
    title: 'Review',
    icon: 'mdi-eye-outline',
    color: 'orange'
  },
  testing: {
    title: 'Testing',
    icon: 'mdi-test-tube',
    color: 'purple'
  },
  done: {
    title: 'Done',
    icon: 'mdi-check-circle-outline',
    color: 'green'
  },
  cancelled: {
    title: 'Cancelled',
    icon: 'mdi-close-circle-outline',
    color: 'red'
  }
}

// Group tasks by status
const tasksByStatus = computed(() => {
  const grouped = {}
  
  // Initialize all statuses with empty arrays
  Object.keys(columns).forEach(status => {
    grouped[status] = []
  })
  
  // Group tasks by status
  props.tasks.forEach(task => {
    if (grouped[task.status]) {
      grouped[task.status].push(task)
    }
  })
  
  return grouped
})

const handleStatusChange = (task, newStatus) => {
  emit('task-updated', { ...task, status: newStatus })
}
</script>

<style scoped>
.kanban-board {
  height: 100%;
  overflow-x: auto;
}

.kanban-column {
  min-width: 280px;
}

.kanban-column .v-card {
  height: 100%;
}
</style>
