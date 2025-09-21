<template>
  <div class="task-management-page">
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold text-primary">Task Management</h1>
        <p class="text-subtitle-1 text-medium-emphasis">Manage projects, tasks, and team collaboration</p>
      </div>
      <div class="d-flex gap-3">
        <v-btn
          color="primary"
          variant="outlined"
          prepend-icon="mdi-plus"
          @click="showCreateProjectDialog = true"
        >
          New Project
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="showCreateTaskDialog = true"
        >
          New Task
        </v-btn>
      </div>
    </div>

    <!-- Statistics Cards -->
    <v-row class="mb-6">
      <v-col cols="12" md="3">
        <v-card class="pa-4 text-center" color="primary" variant="tonal">
          <v-icon size="40" class="mb-2">mdi-clipboard-list</v-icon>
          <div class="text-h4 font-weight-bold">{{ statistics.total_tasks || 0 }}</div>
          <div class="text-subtitle-2">Total Tasks</div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card class="pa-4 text-center" color="success" variant="tonal">
          <v-icon size="40" class="mb-2">mdi-check-circle</v-icon>
          <div class="text-h4 font-weight-bold">{{ statistics.completed_tasks || 0 }}</div>
          <div class="text-subtitle-2">Completed</div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card class="pa-4 text-center" color="warning" variant="tonal">
          <v-icon size="40" class="mb-2">mdi-clock-alert</v-icon>
          <div class="text-h4 font-weight-bold">{{ statistics.overdue_tasks || 0 }}</div>
          <div class="text-subtitle-2">Overdue</div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card class="pa-4 text-center" color="info" variant="tonal">
          <v-icon size="40" class="mb-2">mdi-folder-multiple</v-icon>
          <div class="text-h4 font-weight-bold">{{ statistics.active_projects || 0 }}</div>
          <div class="text-subtitle-2">Active Projects</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Tabs -->
    <v-tabs v-model="activeTab" class="mb-4">
      <v-tab value="kanban">
        <v-icon start>mdi-view-column</v-icon>
        Kanban Board
      </v-tab>
      <v-tab value="list">
        <v-icon start>mdi-format-list-bulleted</v-icon>
        Task List
      </v-tab>
      <v-tab value="projects">
        <v-icon start>mdi-folder-multiple</v-icon>
        Projects
      </v-tab>
      <v-tab value="calendar">
        <v-icon start>mdi-calendar</v-icon>
        Calendar
      </v-tab>
    </v-tabs>

    <!-- Tab Content -->
    <v-window v-model="activeTab">
      <!-- Kanban Board -->
      <v-window-item value="kanban">
        <KanbanBoard
          :tasks="tasks"
          :loading="loading"
          @task-updated="handleTaskUpdated"
          @task-clicked="handleTaskClicked"
        />
      </v-window-item>

      <!-- Task List -->
      <v-window-item value="list">
        <TaskList
          :tasks="tasks"
          :loading="loading"
          :pagination="pagination"
          @task-updated="handleTaskUpdated"
          @task-clicked="handleTaskClicked"
          @page-changed="handlePageChanged"
        />
      </v-window-item>

      <!-- Projects -->
      <v-window-item value="projects">
        <ProjectList
          :projects="projects"
          :loading="projectsLoading"
          @project-updated="handleProjectUpdated"
          @project-clicked="handleProjectClicked"
        />
      </v-window-item>

      <!-- Calendar -->
      <v-window-item value="calendar">
        <TaskCalendar
          :tasks="tasks"
          :loading="loading"
          @task-clicked="handleTaskClicked"
          @date-clicked="handleDateClicked"
        />
      </v-window-item>
    </v-window>

    <!-- Create Task Dialog -->
    <CreateTaskDialog
      v-model="showCreateTaskDialog"
      @task-created="handleTaskCreated"
    />

    <!-- Create Project Dialog -->
    <CreateProjectDialog
      v-model="showCreateProjectDialog"
      @project-created="handleProjectCreated"
    />

    <!-- Task Details Dialog -->
    <TaskDetailsDialog
      v-model="showTaskDetailsDialog"
      :task="selectedTask"
      @task-updated="handleTaskUpdated"
    />

    <!-- Project Details Dialog -->
    <ProjectDetailsDialog
      v-model="showProjectDetailsDialog"
      :project="selectedProject"
      @project-updated="handleProjectUpdated"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useTaskStore } from '@/js/stores/taskStore'
import { useProjectStore } from '@/js/stores/projectStore'
import KanbanBoard from './components/KanbanBoard.vue'
import TaskList from './components/TaskList.vue'
import ProjectList from './components/ProjectList.vue'
import TaskCalendar from './components/TaskCalendar.vue'
import CreateTaskDialog from './components/CreateTaskDialog.vue'
import CreateProjectDialog from './components/CreateProjectDialog.vue'
import TaskDetailsDialog from './components/TaskDetailsDialog.vue'
import ProjectDetailsDialog from './components/ProjectDetailsDialog.vue'

// Stores
const taskStore = useTaskStore()
const projectStore = useProjectStore()

// Reactive data
const activeTab = ref('kanban')
const showCreateTaskDialog = ref(false)
const showCreateProjectDialog = ref(false)
const showTaskDetailsDialog = ref(false)
const showProjectDetailsDialog = ref(false)
const selectedTask = ref(null)
const selectedProject = ref(null)

// Computed properties
const tasks = computed(() => taskStore.tasks)
const projects = computed(() => projectStore.projects)
const loading = computed(() => taskStore.loading)
const projectsLoading = computed(() => projectStore.loading)
const pagination = computed(() => taskStore.pagination)
const statistics = computed(() => ({
  ...taskStore.statistics,
  ...projectStore.statistics
}))

// Methods
const loadData = async () => {
  await Promise.all([
    taskStore.fetchTasks(),
    projectStore.fetchProjects(),
    taskStore.fetchStatistics(),
    projectStore.fetchStatistics()
  ])
}

const handleTaskUpdated = (task) => {
  taskStore.updateTask(task)
}

const handleTaskClicked = (task) => {
  selectedTask.value = task
  showTaskDetailsDialog.value = true
}

const handleTaskCreated = (task) => {
  taskStore.addTask(task)
  showCreateTaskDialog.value = false
}

const handleProjectUpdated = (project) => {
  projectStore.updateProject(project)
}

const handleProjectClicked = (project) => {
  selectedProject.value = project
  showProjectDetailsDialog.value = true
}

const handleProjectCreated = (project) => {
  projectStore.addProject(project)
  showCreateProjectDialog.value = false
}

const handlePageChanged = (page) => {
  taskStore.setPage(page)
}

const handleDateClicked = (date) => {
  // Handle calendar date click - could open create task dialog with pre-filled date
  console.log('Date clicked:', date)
}

// Lifecycle
onMounted(() => {
  loadData()
})
</script>

<style scoped>
.task-management-page {
  padding: 24px;
}

.gap-3 {
  gap: 12px;
}
</style>
