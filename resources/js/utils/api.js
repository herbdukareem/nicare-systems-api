import axios from 'axios';

// Determine API base URL based on environment
const getApiBaseUrl = () => {
  // Check if we're in development mode with Vite
  if (import.meta.env.DEV) {
    return 'http://ngscha-api.test/api';
  }
  // In production, use relative URL
  return '/api';
};

// Create axios instance
const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 10000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    console.log('token', token);
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      localStorage.removeItem('token');
      window.location.href = '/login';
    }

    // Handle other errors
    const message = error.response?.data?.message || error.message || 'An error occurred';
    console.error('API Error:', message);

    return Promise.reject(error);
  }
);

// API methods
export const authAPI = {
  login: (credentials) => api.post('/login', credentials),
  logout: () => api.post('/logout'),
  getUser: () => api.get('/user'),
  forgotPassword: (email) => api.post('/forget-password', { email }),
  resetPassword: (data) => api.post('/reset-password', data),
};

export const enrolleeAPI = {
  getAll: (params) => api.get('/v1/enrollees', { params }),
  getById: (id) => api.get(`/v1/enrollees/${id}`),
  create: (data) => api.post('/v1/enrollees', data),
  update: (id, data) => api.put(`/v1/enrollees/${id}`, data),
  delete: (id) => api.delete(`/v1/enrollees/${id}`),
  exportExcel: (params) => api.get('/v1/enrollees-export', {
    params,
    responseType: 'blob'
  }),
  exportPdf: (id) => api.get(`/v1/enrollees/${id}/export-pdf`, {
    responseType: 'blob'
  }),
};

export const dashboardAPI = {
  getOverview: () => api.get('/dashboard/overview'),
  getEnrolleeStats: () => api.get('/dashboard/enrollee-stats'),
  getFacilityStats: () => api.get('/dashboard/facility-stats'),
  getChartData: () => api.get('/dashboard/chart-data'),
  getRecentActivities: () => api.get('/dashboard/recent-activities'),
  getLgas: () => api.get('/v1/lgas'),
  getBenefactors: () => api.get('/v1/benefactors'),
};

export const facilityAPI = {
  getAll: (params) => api.get('/v1/facilities', { params }),
  getById: (id) => api.get(`/v1/facilities/${id}`),
  create: (data) => api.post('/v1/facilities', data),
  update: (id, data) => api.put(`/v1/facilities/${id}`, data),
  delete: (id) => api.delete(`/v1/facilities/${id}`),
  getEnrollees: (id, params) => api.get(`/v1/facilities/${id}/enrollees`, { params }),
};

export const userAPI = {
  getAll: (params) => api.get('/v1/users', { params }),
  getById: (id) => api.get(`/v1/users/${id}`),
  create: (data) => api.post('/v1/users', data),
  update: (id, data) => api.put(`/v1/users/${id}`, data),
  delete: (id) => api.delete(`/v1/users/${id}`),
  syncRoles: (id, roles) => api.post(`/v1/users/${id}/roles`, { roles }),
  getWithRoles: (params) => api.get('/v1/users-with-roles', { params }),
};

export const benefactorAPI = {
  getAll: (params) => api.get('/v1/benefactors', { params }),
  getById: (id) => api.get(`/v1/benefactors/${id}`),
  create: (data) => api.post('/v1/benefactors', data),
  update: (id, data) => api.put(`/v1/benefactors/${id}`, data),
  delete: (id) => api.delete(`/v1/benefactors/${id}`),
};

export const roleAPI = {
  getAll: (params) => api.get('/v1/roles', { params }),
  getById: (id) => api.get(`/v1/roles/${id}`),
  create: (data) => api.post('/v1/roles', data),
  update: (id, data) => api.put(`/v1/roles/${id}`, data),
  delete: (id) => api.delete(`/v1/roles/${id}`),
  syncPermissions: (id, permissions) => api.post(`/v1/roles/${id}/permissions`, { permissions }),
};

export const permissionAPI = {
  getAll: (params) => api.get('/v1/permissions', { params }),
  getById: (id) => api.get(`/v1/permissions/${id}`),
  create: (data) => api.post('/v1/permissions', data),
  update: (id, data) => api.put(`/v1/permissions/${id}`, data),
  delete: (id) => api.delete(`/v1/permissions/${id}`),
};

export default api;