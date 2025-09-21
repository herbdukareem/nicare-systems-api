import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/js/utils/api'

export const useProjectStore = defineStore('project', () => {
  // State
  const projects = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1
  })
  const filters = ref({
    status: '',
    priority: '',
    project_manager_id: '',
    department_id: '',
    search: '',
    overdue: false,
    active: false,
    my_projects: false
  })
  const statistics = ref({
    total_projects: 0,
    active_projects: 0,
    completed_projects: 0,
    overdue_projects: 0,
    projects_by_status: {},
    projects_by_priority: {},
    total_budget: 0,
    total_spent: 0,
    average_progress: 0
  })

  // Getters
  const activeProjects = computed(() => {
    return projects.value.filter(project => project.status === 'active')
  })

  const completedProjects = computed(() => {
    return projects.value.filter(project => project.status === 'completed')
  })

  const overdueProjects = computed(() => {
    return projects.value.filter(project => {
      if (!project.end_date || project.status === 'completed' || project.status === 'cancelled') {
        return false
      }
      return new Date(project.end_date) < new Date()
    })
  })

  const myProjects = computed(() => {
    const userId = localStorage.getItem('user_id')
    return projects.value.filter(project => project.project_manager_id == userId)
  })

  const projectsByStatus = computed(() => {
    const grouped = {}
    const statuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled']
    
    statuses.forEach(status => {
      grouped[status] = projects.value.filter(project => project.status === status)
    })
    
    return grouped
  })

  // Actions
  const fetchProjects = async (params = {}) => {
    loading.value = true
    error.value = null
    
    try {
      const queryParams = {
        ...filters.value,
        ...params,
        page: pagination.value.current_page,
        per_page: pagination.value.per_page
      }

      const response = await api.get('/projects', { params: queryParams })
      
      if (response.data.success) {
        projects.value = response.data.data.data
        pagination.value = {
          current_page: response.data.data.current_page,
          per_page: response.data.data.per_page,
          total: response.data.data.total,
          last_page: response.data.data.last_page
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch projects'
      console.error('Error fetching projects:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchProject = async (id) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.get(`/projects/${id}`)
      
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch project'
      console.error('Error fetching project:', err)
    } finally {
      loading.value = false
    }
  }

  const createProject = async (projectData) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.post('/projects', projectData)
      
      if (response.data.success) {
        projects.value.unshift(response.data.data)
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create project'
      console.error('Error creating project:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateProject = async (id, projectData) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.put(`/projects/${id}`, projectData)
      
      if (response.data.success) {
        const index = projects.value.findIndex(project => project.id === id)
        if (index !== -1) {
          projects.value[index] = response.data.data
        }
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update project'
      console.error('Error updating project:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteProject = async (id) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.delete(`/projects/${id}`)
      
      if (response.data.success) {
        projects.value = projects.value.filter(project => project.id !== id)
        return true
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete project'
      console.error('Error deleting project:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchStatistics = async () => {
    try {
      const response = await api.get('/projects-statistics')
      
      if (response.data.success) {
        statistics.value = response.data.data
      }
    } catch (err) {
      console.error('Error fetching statistics:', err)
    }
  }

  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters }
    pagination.value.current_page = 1
  }

  const setPage = (page) => {
    pagination.value.current_page = page
    fetchProjects()
  }

  const clearFilters = () => {
    filters.value = {
      status: '',
      priority: '',
      project_manager_id: '',
      department_id: '',
      search: '',
      overdue: false,
      active: false,
      my_projects: false
    }
    pagination.value.current_page = 1
  }

  const addProject = (project) => {
    projects.value.unshift(project)
  }

  const removeProject = (id) => {
    projects.value = projects.value.filter(project => project.id !== id)
  }

  return {
    // State
    projects,
    loading,
    error,
    pagination,
    filters,
    statistics,
    
    // Getters
    activeProjects,
    completedProjects,
    overdueProjects,
    myProjects,
    projectsByStatus,
    
    // Actions
    fetchProjects,
    fetchProject,
    createProject,
    updateProject,
    deleteProject,
    fetchStatistics,
    setFilters,
    setPage,
    clearFilters,
    addProject,
    removeProject
  }
})
