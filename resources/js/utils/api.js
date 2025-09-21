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
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);


api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;

    if (status === 401) {
      // Let the auth store decide how to handle this.
      // We'll just emit a lightweight event the app can listen to.
      window.dispatchEvent(new Event('auth:unauthorized'));
    }

    const message = error?.response?.data?.message || error.message || 'An error occurred';
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
  getStatsByFacility: (facilityId) => api.get(`/v1/enrollees/stats/facility/${facilityId}`),
  getActivity: (id) => api.get(`/v1/enrollees/${id}/activity`),
  getMedicalSummary: (id) => api.get(`/v1/enrollees/${id}/medical-summary`),
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

export const lgaAPI = {
  getAll: (params) => api.get('/v1/lgas', { params }),
  getById: (id) => api.get(`/v1/lgas/${id}`),
};

export const userAPI = {
  getAll: (params) => api.get('/v1/users', { params }),
  getById: (id) => api.get(`/v1/users/${id}`),
  create: (data) => api.post('/v1/users', data),
  update: (id, data) => api.put(`/v1/users/${id}`, data),
  delete: (id) => api.delete(`/v1/users/${id}`),
  syncRoles: (id, roles) => api.post(`/v1/users/${id}/roles`, { roles }),
  getWithRoles: (params) => api.get('/v1/users-with-roles', { params }),
  getProfile: (id) => api.get(`/v1/users/${id}/profile`),
  updatePassword: (id, data) => api.patch(`/v1/users/${id}/password`, data),
  toggleStatus: (id) => api.patch(`/v1/users/${id}/toggle-status`),
  bulkUpdateStatus: (data) => api.post('/v1/users/bulk-update-status', data),
  bulkDelete: (data) => api.delete('/v1/users/bulk-delete', { data }),
  // Profile management
  getActivities: (id, params) => api.get(`/v1/users/${id}/activities`, { params }),
  updateRoles: (id, data) => api.post(`/v1/users/${id}/roles`, data),
  uploadAvatar: (id, formData) => api.post(`/v1/users/${id}/avatar`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  toggle2FA: (id) => api.patch(`/v1/users/${id}/toggle-2fa`),
  revokeAllSessions: (id) => api.post(`/v1/users/${id}/revoke-sessions`),
  // Advanced features
  impersonate: (id) => api.post(`/v1/users/${id}/impersonate`),
  stopImpersonation: () => api.post('/v1/users/stop-impersonation'),
  export: (params) => api.get('/v1/users/export', { params }),
  import: (formData) => api.post('/v1/users/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  getActivityStats: (id) => api.get(`/v1/users/${id}/activity-stats`),
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
  getWithUserCounts: () => api.get('/v1/roles-with-user-counts'),
  clone: (id, data) => api.post(`/v1/roles/${id}/clone`, data),
  bulkDelete: (data) => api.delete('/v1/roles/bulk-delete', { data }),
};

export const permissionAPI = {
  getAll: (params) => api.get('/v1/permissions', { params }),
  getById: (id) => api.get(`/v1/permissions/${id}`),
  create: (data) => api.post('/v1/permissions', data),
  update: (id, data) => api.put(`/v1/permissions/${id}`, data),
  delete: (id) => api.delete(`/v1/permissions/${id}`),
  getByCategory: () => api.get('/v1/permissions/by-category'),
  bulkCreate: (data) => api.post('/v1/permissions/bulk-create', data),
  bulkDelete: (data) => api.delete('/v1/permissions/bulk-delete', { data }),
};

export const securityAPI = {
  getDashboard: () => api.get('/security/dashboard'),
  getLogs: (params) => api.get('/security/logs', { params }),
  resolve: (id, data) => api.post(`/security/logs/${id}/resolve`, data),
  bulkResolve: (data) => api.post('/security/logs/bulk-resolve', data),
  getAuditTrail: (params) => api.get('/security/audit-trail', { params }),
  getSessions: () => api.get('/security/sessions'),
  revokeSessions: (data) => api.post('/security/sessions/revoke', data),
};

// PAS (Pre-Authorization System) API
export const pasAPI = {
  // Referral APIs
  getReferrals: (params) => api.get('/v1/pas/referrals', { params }),
  createReferral: (data) => api.post('/v1/pas/workflow/referral', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  getReferral: (id) => api.get(`/v1/pas/referrals/${id}`),
  getReferralByCode: (code) => api.get(`/v1/pas/referrals`, { params: { search: code } }),
  approveReferral: (id, data) => api.post(`/v1/pas/referrals/${id}/approve`, data),
  denyReferral: (id, data) => api.post(`/v1/pas/referrals/${id}/deny`, data),
  generatePACodeFromReferral: (referralId, data) => api.post(`/v1/pas/referrals/${referralId}/generate-pa-code`, data),
  getReferralStatistics: () => api.get('/v1/pas/referrals-statistics'),

  // PA Code APIs
  getPACodes: (params) => api.get('/v1/pas/pa-codes', { params }),
  generatePACode: (data) => api.post('/v1/pas/workflow/pa-code', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  getPACode: (id) => api.get(`/v1/pas/pa-codes/${id}`),
  markPACodeUsed: (id, data) => api.post(`/v1/pas/pa-codes/${id}/mark-used`, data),
  cancelPACode: (id) => api.post(`/v1/pas/pa-codes/${id}/cancel`),
  verifyPACode: (data) => api.post('/v1/pas/pa-codes/verify', data),
  getPACodeStatistics: () => api.get('/v1/pas/pa-codes-statistics'),
};

// Drug Management API
export const drugAPI = {
  getAll: (params) => api.get('/v1/drugs', { params }),
  getById: (id) => api.get(`/v1/drugs/${id}`),
  create: (data) => api.post('/v1/drugs', data),
  update: (id, data) => api.put(`/v1/drugs/${id}`, data),
  delete: (id) => api.delete(`/v1/drugs/${id}`),
  import: (formData) => api.post('/v1/drugs/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  export: (params) => api.get('/v1/drugs-export', {
    params,
    responseType: 'blob'
  }),
  downloadTemplate: () => api.get('/v1/drugs-template', {
    responseType: 'blob'
  }),
  getStatistics: () => api.get('/v1/drugs-statistics'),
};

// Service Management API
export const serviceAPI = {
  getAll: (params) => api.get('/v1/services', { params }),
  getById: (id) => api.get(`/v1/services/${id}`),
  create: (data) => api.post('/v1/services', data),
  update: (id, data) => api.put(`/v1/services/${id}`, data),
  delete: (id) => api.delete(`/v1/services/${id}`),
  import: (formData) => api.post('/v1/services/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  export: (params) => api.get('/v1/services-export', {
    params,
    responseType: 'blob'
  }),
  downloadTemplate: () => api.get('/v1/services-template', {
    responseType: 'blob'
  }),
  getStatistics: () => api.get('/v1/services-statistics'),
  getGroups: () => api.get('/v1/services-groups'),
};

// Feedback Management API
export const feedbackAPI = {
  getAll: (params) => api.get('/v1/feedback', { params }),
  getById: (id) => api.get(`/v1/feedback/${id}`),
  create: (data) => api.post('/v1/feedback', data),
  update: (id, data) => api.put(`/v1/feedback/${id}`, data),
  getStatistics: () => api.get('/v1/feedback/statistics'),
  searchEnrollees: (params) => api.get('/v1/feedback/search-enrollees', { params }),
  getFeedbackOfficers: () => api.get('/v1/feedback/officers'),
  getMyFeedbacks: (params) => api.get('/v1/feedback/my-feedbacks', { params }),
  getEnrolleeComprehensiveData: (enrolleeId) => api.get(`/v1/feedback/enrollee/${enrolleeId}/comprehensive-data`),
  assignToOfficer: (id, data) => api.post(`/v1/feedback/${id}/assign`, data),
};





export default api;
