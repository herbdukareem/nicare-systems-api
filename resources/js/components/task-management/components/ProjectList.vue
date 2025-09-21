<template>
  <div class="project-list">
    <v-card variant="outlined">
      <v-card-title class="d-flex align-center justify-space-between">
        <span>Projects</span>
        <v-btn color="primary" size="small" prepend-icon="mdi-plus">
          New Project
        </v-btn>
      </v-card-title>
      
      <v-card-text>
        <div v-if="loading" class="text-center py-8">
          <v-progress-circular indeterminate color="primary" />
        </div>
        
        <v-row v-else>
          <v-col
            v-for="project in projects"
            :key="project.id"
            cols="12"
            md="6"
            lg="4"
          >
            <v-card
              variant="outlined"
              hover
              @click="$emit('project-clicked', project)"
            >
              <v-card-title class="d-flex align-center justify-space-between">
                <span class="text-h6">{{ project.name }}</span>
                <v-chip
                  :color="getStatusColor(project.status)"
                  size="small"
                  variant="tonal"
                >
                  {{ formatStatus(project.status) }}
                </v-chip>
              </v-card-title>
              
              <v-card-text>
                <p class="text-body-2 text-medium-emphasis mb-3">
                  {{ project.description || 'No description' }}
                </p>
                
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-caption">Progress</span>
                  <span class="text-caption">{{ project.progress_percentage || 0 }}%</span>
                </div>
                
                <v-progress-linear
                  :model-value="project.progress_percentage || 0"
                  :color="getStatusColor(project.status)"
                  height="6"
                  rounded
                />
                
                <div class="d-flex align-center justify-space-between mt-3">
                  <div v-if="project.project_manager" class="d-flex align-center">
                    <v-avatar size="24" class="mr-2">
                      <span class="text-caption">
                        {{ getInitials(project.project_manager.name) }}
                      </span>
                    </v-avatar>
                    <span class="text-caption">{{ project.project_manager.name }}</span>
                  </div>
                  
                  <div v-if="project.end_date" class="d-flex align-center">
                    <v-icon size="16" class="mr-1">mdi-calendar</v-icon>
                    <span class="text-caption">{{ formatDate(project.end_date) }}</span>
                  </div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
        
        <div v-if="!loading && !projects.length" class="text-center py-8">
          <v-icon size="64" class="mb-4 text-medium-emphasis">mdi-folder-outline</v-icon>
          <h3 class="text-h6 mb-2">No Projects</h3>
          <p class="text-body-2 text-medium-emphasis">Create your first project to get started</p>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
const props = defineProps({
  projects: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['project-clicked', 'project-updated'])

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
</script>

<style scoped>
.project-list .v-card {
  cursor: pointer;
  transition: all 0.2s ease;
}

.project-list .v-card:hover {
  transform: translateY(-2px);
}
</style>
