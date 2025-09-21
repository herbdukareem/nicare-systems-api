import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api  from '@/js/utils/api'

export const useTaskStore = defineStore('task', () => {
  // State
  const tasks = ref([])
  const categories = ref([])
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
    type: '',
    project_id: '',
    category_id: '',
    assignee_id: '',
    search: '',
    overdue: false,
    due_today: false,
    due_this_week: false,
    my_tasks: false
  })
  const statistics = ref({
    total_tasks: 0,
    completed_tasks: 0,
    overdue_tasks: 0,
    in_progress_tasks: 0,
    tasks_by_status: {},
    tasks_by_priority: {}
  })

  // Getters
  const tasksByStatus = computed(() => {
    const grouped = {}
    const statuses = ['todo', 'in_progress', 'review', 'testing', 'done', 'cancelled']
    
    statuses.forEach(status => {
      grouped[status] = tasks.value.filter(task => task.status === status)
    })
    
    return grouped
  })

  const overdueTasks = computed(() => {
    return tasks.value.filter(task => {
      if (!task.due_date || task.status === 'done' || task.status === 'cancelled') {
        return false
      }
      return new Date(task.due_date) < new Date()
    })
  })

  const myTasks = computed(() => {
    const userId = localStorage.getItem('user_id')
    return tasks.value.filter(task => task.assignee_id == userId)
  })

  // Actions
  const fetchTasks = async (params = {}) => {
    loading.value = true
    error.value = null
    
    try {
      const queryParams = {
        ...filters.value,
        ...params,
        page: pagination.value.current_page,
        per_page: pagination.value.per_page
      }

      const response = await api.get('/tasks', { params: queryParams })
      
      if (response.data.success) {
        tasks.value = response.data.data.data
        pagination.value = {
          current_page: response.data.data.current_page,
          per_page: response.data.data.per_page,
          total: response.data.data.total,
          last_page: response.data.data.last_page
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch tasks'
      console.error('Error fetching tasks:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchTask = async (id) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.get(`/tasks/${id}`)
      
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch task'
      console.error('Error fetching task:', err)
    } finally {
      loading.value = false
    }
  }

  const createTask = async (taskData) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.post('/tasks', taskData)
      
      if (response.data.success) {
        tasks.value.unshift(response.data.data)
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create task'
      console.error('Error creating task:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateTask = async (id, taskData) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.put(`/tasks/${id}`, taskData)
      
      if (response.data.success) {
        const index = tasks.value.findIndex(task => task.id === id)
        if (index !== -1) {
          tasks.value[index] = response.data.data
        }
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update task'
      console.error('Error updating task:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteTask = async (id) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.delete(`/tasks/${id}`)
      
      if (response.data.success) {
        tasks.value = tasks.value.filter(task => task.id !== id)
        return true
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete task'
      console.error('Error deleting task:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchCategories = async () => {
    try {
      const response = await api.get('/categories-dropdown')
      
      if (response.data.success) {
        categories.value = response.data.data
      }
    } catch (err) {
      console.error('Error fetching categories:', err)
    }
  }

  const fetchStatistics = async () => {
    try {
      const response = await api.get('/tasks-statistics')
      
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
    fetchTasks()
  }

  const clearFilters = () => {
    filters.value = {
      status: '',
      priority: '',
      type: '',
      project_id: '',
      category_id: '',
      assignee_id: '',
      search: '',
      overdue: false,
      due_today: false,
      due_this_week: false,
      my_tasks: false
    }
    pagination.value.current_page = 1
  }

  const addTask = (task) => {
    tasks.value.unshift(task)
  }

  const removeTask = (id) => {
    tasks.value = tasks.value.filter(task => task.id !== id)
  }

  return {
    // State
    tasks,
    categories,
    loading,
    error,
    pagination,
    filters,
    statistics,
    
    // Getters
    tasksByStatus,
    overdueTasks,
    myTasks,
    
    // Actions
    fetchTasks,
    fetchTask,
    createTask,
    updateTask,
    deleteTask,
    fetchCategories,
    fetchStatistics,
    setFilters,
    setPage,
    clearFilters,
    addTask,
    removeTask
  }
})
