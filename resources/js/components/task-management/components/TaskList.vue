<template>
  <div class="task-list">
    <!-- Filters and Search -->
    <v-card class="mb-4" variant="outlined">
      <v-card-text class="pa-4">
        <v-row>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="search"
              label="Search tasks..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              @input="handleSearch"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="statusFilter"
              label="Status"
              :items="statusOptions"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="handleFilter"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="priorityFilter"
              label="Priority"
              :items="priorityOptions"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="handleFilter"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="typeFilter"
              label="Type"
              :items="typeOptions"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="handleFilter"
            />
          </v-col>
          <v-col cols="12" md="3">
            <div class="d-flex gap-2">
              <v-btn
                :color="myTasksFilter ? 'primary' : 'default'"
                :variant="myTasksFilter ? 'flat' : 'outlined'"
                size="small"
                @click="toggleMyTasks"
              >
                My Tasks
              </v-btn>
              <v-btn
                :color="overdueFilter ? 'error' : 'default'"
                :variant="overdueFilter ? 'flat' : 'outlined'"
                size="small"
                @click="toggleOverdue"
              >
                Overdue
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Task Table -->
    <v-card variant="outlined">
      <v-data-table
        :headers="headers"
        :items="tasks"
        :loading="loading"
        :items-per-page="pagination.per_page"
        :page="pagination.current_page"
        :server-items-length="pagination.total"
        class="task-table"
        @click:row="handleRowClick"
        @update:page="handlePageChange"
      >
        <!-- Task Number Column -->
        <template #item.task_number="{ item }">
          <v-chip size="small" variant="outlined">
            {{ item.task_number }}
          </v-chip>
        </template>

        <!-- Title Column -->
        <template #item.title="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.title }}</div>
            <div v-if="item.description" class="text-caption text-medium-emphasis line-clamp-1">
              {{ item.description }}
            </div>
          </div>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <v-chip
            :color="getStatusColor(item.status)"
            size="small"
            variant="tonal"
          >
            {{ formatStatus(item.status) }}
          </v-chip>
        </template>

        <!-- Priority Column -->
        <template #item.priority="{ item }">
          <v-chip
            :color="getPriorityColor(item.priority)"
            size="small"
            variant="tonal"
          >
            {{ item.priority }}
          </v-chip>
        </template>

        <!-- Type Column -->
        <template #item.type="{ item }">
          <v-chip
            :color="getTypeColor(item.type)"
            size="small"
            variant="outlined"
          >
            {{ formatType(item.type) }}
          </v-chip>
        </template>

        <!-- Assignee Column -->
        <template #item.assignee="{ item }">
          <div v-if="item.assignee" class="d-flex align-center">
            <v-avatar size="24" class="mr-2">
              <v-img
                v-if="item.assignee.avatar"
                :src="item.assignee.avatar"
                :alt="item.assignee.name"
              />
              <span v-else class="text-caption">
                {{ getInitials(item.assignee.name) }}
              </span>
            </v-avatar>
            <span class="text-body-2">{{ item.assignee.name }}</span>
          </div>
          <span v-else class="text-medium-emphasis">Unassigned</span>
        </template>

        <!-- Due Date Column -->
        <template #item.due_date="{ item }">
          <div v-if="item.due_date" class="d-flex align-center">
            <v-icon
              :color="isOverdue(item) ? 'error' : 'medium-emphasis'"
              size="16"
              class="mr-1"
            >
              mdi-calendar
            </v-icon>
            <span
              :class="[
                'text-body-2',
                isOverdue(item) ? 'text-error' : 'text-medium-emphasis'
              ]"
            >
              {{ formatDate(item.due_date) }}
            </span>
          </div>
          <span v-else class="text-medium-emphasis">No due date</span>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <v-menu>
            <template #activator="{ props }">
              <v-btn
                icon="mdi-dots-vertical"
                size="small"
                variant="text"
                v-bind="props"
              />
            </template>
            <v-list density="compact">
              <v-list-item @click="$emit('task-clicked', item)">
                <template #prepend>
                  <v-icon>mdi-eye</v-icon>
                </template>
                <v-list-item-title>View Details</v-list-item-title>
              </v-list-item>
              <v-list-item @click="editTask(item)">
                <template #prepend>
                  <v-icon>mdi-pencil</v-icon>
                </template>
                <v-list-item-title>Edit</v-list-item-title>
              </v-list-item>
              <v-divider />
              <v-list-item @click="deleteTask(item)" class="text-error">
                <template #prepend>
                  <v-icon color="error">mdi-delete</v-icon>
                </template>
                <v-list-item-title>Delete</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  tasks: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  pagination: {
    type: Object,
    default: () => ({
      current_page: 1,
      per_page: 15,
      total: 0,
      last_page: 1
    })
  }
})

const emit = defineEmits(['task-clicked', 'task-updated', 'page-changed'])

// Filters
const search = ref('')
const statusFilter = ref('')
const priorityFilter = ref('')
const typeFilter = ref('')
const myTasksFilter = ref(false)
const overdueFilter = ref(false)

// Table headers
const headers = [
  { title: 'Task #', key: 'task_number', sortable: false, width: '100px' },
  { title: 'Title', key: 'title', sortable: true },
  { title: 'Status', key: 'status', sortable: true, width: '120px' },
  { title: 'Priority', key: 'priority', sortable: true, width: '100px' },
  { title: 'Type', key: 'type', sortable: true, width: '100px' },
  { title: 'Assignee', key: 'assignee', sortable: false, width: '150px' },
  { title: 'Due Date', key: 'due_date', sortable: true, width: '120px' },
  { title: 'Actions', key: 'actions', sortable: false, width: '80px' }
]

// Filter options
const statusOptions = [
  { title: 'To Do', value: 'todo' },
  { title: 'In Progress', value: 'in_progress' },
  { title: 'Review', value: 'review' },
  { title: 'Testing', value: 'testing' },
  { title: 'Done', value: 'done' },
  { title: 'Cancelled', value: 'cancelled' }
]

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

// Helper functions
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

const getTypeColor = (type) => {
  const colors = {
    task: 'primary',
    bug: 'error',
    feature: 'success',
    improvement: 'info',
    research: 'purple'
  }
  return colors[type] || 'grey'
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

const isOverdue = (task) => {
  if (!task.due_date || task.status === 'done' || task.status === 'cancelled') {
    return false
  }
  return new Date(task.due_date) < new Date()
}

// Event handlers
const handleRowClick = (event, { item }) => {
  emit('task-clicked', item)
}

const handlePageChange = (page) => {
  emit('page-changed', page)
}

const handleSearch = () => {
  // Implement search logic
  console.log('Search:', search.value)
}

const handleFilter = () => {
  // Implement filter logic
  console.log('Filters:', {
    status: statusFilter.value,
    priority: priorityFilter.value,
    type: typeFilter.value
  })
}

const toggleMyTasks = () => {
  myTasksFilter.value = !myTasksFilter.value
  console.log('My tasks filter:', myTasksFilter.value)
}

const toggleOverdue = () => {
  overdueFilter.value = !overdueFilter.value
  console.log('Overdue filter:', overdueFilter.value)
}

const editTask = (task) => {
  console.log('Edit task:', task)
}

const deleteTask = (task) => {
  console.log('Delete task:', task)
}
</script>

<style scoped>
.task-table :deep(.v-data-table__tr) {
  cursor: pointer;
}

.task-table :deep(.v-data-table__tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.gap-2 {
  gap: 8px;
}
</style>
