import axios from 'axios';

// Determine API base URL based on environment
const getApiBaseUrl = () => {
  // Check if we're in development mode with Vite
  if (import.meta.env.DEV) {
    return import.meta.env.VITE_API_URL;
  }
  // In production, use relative URL
  return '/api';
};

// Create axios instance
const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 30000, // Increased to 30 seconds for heavy operations
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
  getAll: (params) => api.get('/enrollees', { params }),
  getById: (id) => api.get(`/enrollees/${id}`),
  create: (data) => api.post('/enrollees', data),
  update: (id, data) => api.put(`/enrollees/${id}`, data),
  delete: (id) => api.delete(`/enrollees/${id}`),
  getStatsByFacility: (facilityId) => api.get(`/enrollees/stats/facility/${facilityId}`),
  getActivity: (id) => api.get(`/enrollees/${id}/activity`),
  getMedicalSummary: (id) => api.get(`/enrollees/${id}/medical-summary`),
  exportExcel: (params) => api.get('/enrollees-export', {
    params,
    responseType: 'blob'
  }),
  exportPdf: (id) => api.get(`/enrollees/${id}/export-pdf`, {
    responseType: 'blob'
  }),
};

export const dashboardAPI = {
  getOverview: () => api.get('/dashboard/overview'),
  getEnrolleeStats: () => api.get('/dashboard/enrollee-stats'),
  getFacilityStats: () => api.get('/dashboard/facility-stats'),
  getChartData: () => api.get('/dashboard/chart-data'),
  getRecentActivities: () => api.get('/dashboard/recent-activities'),
  getLgas: () => api.get('/lgas'),
  getBenefactors: () => api.get('/benefactors'),
};

export const facilityAPI = {
  getAll: (params) => api.get('/facilities', { params }),
  getById: (id) => api.get(`/facilities/${id}`),
  create: (data) => api.post('/facilities', data),
  update: (id, data) => api.put(`/facilities/${id}`, data),
  delete: (id) => api.delete(`/facilities/${id}`),
  getEnrollees: (id, params) => api.get(`/facilities/${id}/enrollees`, { params }),
};

export const lgaAPI = {
  getAll: (params) => api.get('/lgas', { params }),
  getById: (id) => api.get(`/lgas/${id}`),
};

export const userAPI = {
  getAll: (params) => api.get('/users', { params }),
  getById: (id) => api.get(`/users/${id}`),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  delete: (id) => api.delete(`/users/${id}`),
  syncRoles: (id, roles) => api.post(`/users/${id}/roles`, { roles }),
  syncPermissions: (id, permissions) => api.post(`/users/${id}/permissions`, { permissions }),
  getPermissions: (id) => api.get(`/users/${id}/permissions`),
  switchRole: (id, roleId) => api.post(`/users/${id}/switch-role`, { role_id: roleId }),
  getAvailableModules: () => api.get('/users/available-modules'),
  getWithRoles: (params) => api.get('/users-with-roles', { params }),
  getProfile: (id) => api.get(`/users/${id}/profile`),
  updatePassword: (id, data) => api.patch(`/users/${id}/password`, data),
  toggleStatus: (id) => api.patch(`/users/${id}/toggle-status`),
  bulkUpdateStatus: (data) => api.post('/users/bulk-update-status', data),
  bulkDelete: (data) => api.delete('/users/bulk-delete', { data }),
  // Profile management
  getActivities: (id, params) => api.get(`/users/${id}/activities`, { params }),
  updateRoles: (id, data) => api.post(`/users/${id}/roles`, data),
  uploadAvatar: (id, formData) => api.post(`/users/${id}/avatar`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  toggle2FA: (id) => api.patch(`/users/${id}/toggle-2fa`),
  revokeAllSessions: (id) => api.post(`/users/${id}/revoke-sessions`),
  // Advanced features
  impersonate: (id) => api.post(`/users/${id}/impersonate`),
  stopImpersonation: () => api.post('/users/stop-impersonation'),
  // export: (params) => api.get('/users/export', { params }),
    async export (params = {}) {
    return api.get('/cases/export', {
      params,
      responseType: 'blob'
    })
  },
  import: (formData) => api.post('/users/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    maxBodyLength: Infinity
  }),
  getActivityStats: (id) => api.get(`/users/${id}/activity-stats`),
};

export const benefactorAPI = {
  getAll: (params) => api.get('/benefactors', { params }),
  getById: (id) => api.get(`/benefactors/${id}`),
  create: (data) => api.post('/benefactors', data),
  update: (id, data) => api.put(`/benefactors/${id}`, data),
  delete: (id) => api.delete(`/benefactors/${id}`),
};

export const roleAPI = {
  getAll: (params) => api.get('/roles', { params }),
  getById: (id) => api.get(`/roles/${id}`),
  create: (data) => api.post('/roles', data),
  update: (id, data) => api.put(`/roles/${id}`, data),
  delete: (id) => api.delete(`/roles/${id}`),
  syncPermissions: (id, permissions) => api.post(`/roles/${id}/permissions`, { permissions }),
  getWithUserCounts: () => api.get('/roles-with-user-counts'),
  clone: (id, data) => api.post(`/roles/${id}/clone`, data),
  bulkDelete: (data) => api.delete('/roles/bulk-delete', { data }),
};

export const permissionAPI = {
  getAll: (params) => api.get('/permissions', { params }),
  getById: (id) => api.get(`/permissions/${id}`),
  create: (data) => api.post('/permissions', data),
  update: (id, data) => api.put(`/permissions/${id}`, data),
  delete: (id) => api.delete(`/permissions/${id}`),
  getByCategory: () => api.get('/permissions/by-category'),
  bulkCreate: (data) => api.post('/permissions/bulk-create', data),
  bulkDelete: (data) => api.delete('/permissions/bulk-delete', { data }),
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
  getReferrals: (params) => api.get('/pas/referrals', { params }),
  createReferral: (data) => api.post('/pas/workflow/referral', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  getReferral: (id) => api.get(`/pas/referrals/${id}`),
  getReferralByCode: (code) => api.get(`/pas/referrals`, { params: { search: code } }),
  approveReferral: (id, data) => api.post(`/pas/referrals/${id}/approve`, data),
  denyReferral: (id, data) => api.post(`/pas/referrals/${id}/deny`, data),
  generatePACodeFromReferral: (referralId, data) => api.post(`/pas/referrals/${referralId}/generate-pa-code`, data),
  getReferralStatistics: () => api.get('/pas/referrals-statistics'),
  getPendingReferralsByFacility: (facilityId) => api.get(`/pas/referrals/pending/${facilityId}`),
  modifyReferral: (referralId, data) => api.put(`/pas/referrals/${referralId}/modify`, data),

  // PA Code APIs
  getPACodes: (params) => api.get('/pas/pa-codes', { params }),
  generatePACode: (data) => api.post('/pas/workflow/pa-code', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  getPACode: (id) => api.get(`/pas/pa-codes/${id}`),
  getPACodeById: (id) => api.get(`/pas/pa-codes/${id}`), // Alias for consistency
  generatePACodeFromReferral: (referralId, data) => api.post(`/pas/referrals/${referralId}/generate-pa-code`, data),
  markPACodeUsed: (id, data) => api.post(`/pas/pa-codes/${id}/mark-used`, data),
  markPACodeAsUsed: (id) => api.post(`/pas/pa-codes/${id}/mark-used`), // Alias without data
  cancelPACode: (id) => api.post(`/pas/pa-codes/${id}/cancel`),
  generateUTN: (id) => api.post(`/pas/pa-codes/${id}/generate-utn`),
  verifyPACode: (data) => api.post('/pas/pa-codes/verify', data),
  getPACodeStatistics: () => api.get('/pas/pa-codes-statistics'),

  // UTN Validation APIs
  validateUTN: (referralId, data) => api.post(`/pas/referrals/${referralId}/validate-utn`, data),
};

// Drug Management API
export const drugAPI = {
  getAll: (params) => api.get('/drugs', { params }),
  getById: (id) => api.get(`/drugs/${id}`),
  create: (data) => api.post('/drugs', data),
  update: (id, data) => api.put(`/drugs/${id}`, data),
  delete: (id) => api.delete(`/drugs/${id}`),
  import: (formData) => api.post('/drugs/import', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    timeout: 60000
  }),
  export: (params) => api.get('/drugs-export', {
    params,
    responseType: 'blob'
  }),
  downloadTemplate: () => api.get('/drugs-template', {
    responseType: 'blob'
  }),
  getStatistics: () => api.get('/drugs-statistics'),
};

// Service Management API
export const serviceAPI = {
  getAll: (params) => api.get('/services', { params }),
  getById: (id) => api.get(`/services/${id}`),
  create: (data) => api.post('/services', data),
  update: (id, data) => api.put(`/services/${id}`, data),
  delete: (id) => api.delete(`/services/${id}`),
  import: (formData) => api.post('/services/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  export: (params) => api.get('/services-export', {
    params,
    responseType: 'blob'
  }),
  downloadTemplate: () => api.get('/services-template', {
    responseType: 'blob'
  }),
  getStatistics: () => api.get('/services-statistics'),
  getGroups: () => api.get('/services-groups'),
};

// Case Management API
export const caseAPI = {
  getAll: (params) => api.get('/cases', { params }),
  getById: (id) => api.get(`/cases/${id}`),
  create: (data) => api.post('/cases', data),
  update: (id, data) => api.put(`/cases/${id}`, data),
  delete: (id) => api.delete(`/cases/${id}`),
  getStatistics: () => api.get('/cases-statistics'),
  getGroups: () => api.get('/cases-groups'),
  import: (formData) => api.post('/cases/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  export: (params) => api.get('/cases-export', {
    params,
    responseType: 'blob'
  }),
  downloadTemplate: () => api.get('/cases-template', {
    responseType: 'blob'
  })
};

// Tariff Item Management API
export const tariffItemAPI = {
  getAll: (params) => api.get('/tariff-items', { params }),
  getById: (id) => api.get(`/tariff-items/${id}`),
  create: (data) => api.post('/tariff-items', data),
  update: (id, data) => api.put(`/tariff-items/${id}`, data),
  delete: (id) => api.delete(`/tariff-items/${id}`),
  getStatistics: () => api.get('/tariff-items-statistics'),
  import: (formData) => api.post('/tariff-items/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data',
       'X-Requested-With': 'XMLHttpRequest'
     },
    timeout: 60000
  }),
  export: (params) => api.get('/tariff-items-export', {
    params,
    responseType: 'blob'
  }),
  downloadTemplate: () => api.get('/tariff-items-template', {
    responseType: 'blob'
  })
};

// Service Type API
export const serviceTypeAPI = {
  getAll: (params) => api.get('/service-types', { params }),
  getById: (id) => api.get(`/service-types/${id}`)
};

// Case Type API
export const caseTypeAPI = {
  getAll: (params) => api.get('/case-types', { params }),
  getById: (id) => api.get(`/case-types/${id}`)
};

// Feedback Management API
export const feedbackAPI = {
  getAll: (params) => api.get('/feedback', { params }),
  getById: (id) => api.get(`/feedback/${id}`),
  create: (data) => api.post('/feedback', data),
  update: (id, data) => api.put(`/feedback/${id}`, data),
  getStatistics: () => api.get('/feedback/statistics'),
  searchEnrollees: (params) => api.get('/feedback/search-enrollees', { params }),
  getFeedbackOfficers: () => api.get('/feedback/officers'),
  getMyFeedbacks: (params) => api.get('/feedback/my-feedbacks', { params }),
  getEnrolleeComprehensiveData: (enrolleeId) => api.get(`/feedback/enrollee/${enrolleeId}/comprehensive-data`),
  assignToOfficer: (id, data) => api.post(`/feedback/${id}/assign`, data),
  getApprovedReferrals: (params) => api.get('/feedback/approved-referrals', { params }),
};

// Case Category Management API
export const caseCategoryAPI = {
  getAll: (params) => api.get('/case-categories', { params }),
  getById: (id) => api.get(`/case-categories/${id}`),
  create: (data) => api.post('/case-categories', data),
  update: (id, data) => api.put(`/case-categories/${id}`, data),
  delete: (id) => api.delete(`/case-categories/${id}`),
  toggleStatus: (id) => api.post(`/case-categories/${id}/toggle-status`),
};

// Service Category Management API
export const serviceCategoryAPI = {
  getAll: (params) => api.get('/service-categories', { params }),
  getById: (id) => api.get(`/service-categories/${id}`),
  create: (data) => api.post('/service-categories', data),
  update: (id, data) => api.put(`/service-categories/${id}`, data),
  delete: (id) => api.delete(`/service-categories/${id}`),
  toggleStatus: (id) => api.post(`/service-categories/${id}/toggle-status`),
};
// DOFacility Management API
export const doFacilityAPI = {
  getAll: (params) => api.get('/do-facilities', { params }),
  getById: (id) => api.get(`/do-facilities/${id}`),
  create: (data) => api.post('/do-facilities', data),
  update: (id, data) => api.put(`/do-facilities/${id}`, data),
  delete: (id) => api.delete(`/do-facilities/${id}`),
  getDeskOfficers: () => api.get('/do-facilities/desk-officers'),
  getFacilities: () => api.get('/do-facilities/facilities'),
  getUserFacilities: (userId) => api.get(`/do-facilities/user/${userId}/facilities`),
};

export const doDashboardAPI = {
  getOverview: () => api.get('/do-dashboard/overview'),
  getReferrals: (params) => api.get('/do-dashboard/referrals', { params }),
  getPACodes: (params) => api.get('/do-dashboard/pa-codes', { params }),
  validateUTN: (data) => api.post('/do-dashboard/validate-utn', data),
};

export const departmentAPI = {
  getAll: (params) => api.get('/departments', { params }),
  getById: (id) => api.get(`/departments/${id}`),
  create: (data) => api.post('/departments', data),
  update: (id, data) => api.put(`/departments/${id}`, data),
  delete: (id) => api.delete(`/departments/${id}`),
};

export const designationAPI = {
  getAll: (params) => api.get('/designations', { params }),
  getById: (id) => api.get(`/designations/${id}`),
  create: (data) => api.post('/designations', data),
  update: (id, data) => api.put(`/designations/${id}`, data),
  delete: (id) => api.delete(`/designations/${id}`),
};

// Claims Management API (core claim submission & lifecycle)
export const claimsAPI = {
  getAll: (params) => api.get('/pas/claims', { params }),
  getById: (id) => api.get(`/pas/claims/${id}`),
  create: (data) => api.post('/pas/claims', data),
  update: (id, data) => api.put(`/pas/claims/${id}`, data),
  submit: (id) => api.post(`/pas/claims/${id}/submit`),
  getServicesForReferralOrPACode: (params) =>
    api.get('/pas/claims/services/for-referral-or-pacode', { params }),
  getClaims: (params) => api.get('/pas/claims', { params }),
  approveClaim: (id, data) => api.post(`/pas/claims/${id}/approve`, data),
  rejectClaim: (id, data) => api.post(`/pas/claims/${id}/reject`, data),
  // Review and batch operations
  reviewClaim: (id, data) => api.post(`/claims-automation/claims/${id}/review`, data),
  batchApprove: (data) => api.post('/claims-automation/claims/batch-approve', data),
  batchReject: (data) => api.post('/claims-automation/claims/batch-reject', data),
  downloadSlip: (id) => api.get(`/claims-automation/claims/${id}/slip`, { responseType: 'blob' }),
};

// Payment Batch API
export const paymentBatchAPI = {
  getAll: (params) => api.get('/claims-automation/payment-batches', { params }),
  getById: (id) => api.get(`/claims-automation/payment-batches/${id}`),
  create: (data) => api.post('/claims-automation/payment-batches', data),
  getApprovedClaims: (params) => api.get('/claims-automation/payment-batches/approved-claims', { params }),
  process: (id, data) => api.post(`/claims-automation/payment-batches/${id}/process`, data),
  markPaid: (id, data) => api.post(`/claims-automation/payment-batches/${id}/mark-paid`, data),
  downloadReceipt: (id) => api.get(`/claims-automation/payment-batches/${id}/receipt`, { responseType: 'blob' }),
};

// Claims Automation API - Hybrid Bundle/FFS Payment Model
export const claimsAutomationAPI = {
  // Admission Management
  createAdmission: (data) => api.post('/pas/claims/automation/admissions', data),
  dischargePatient: (admissionId, data) => api.post(`/pas/claims/automation/admissions/${admissionId}/discharge`, data),
  getAdmissionHistory: (params) => api.get('/pas/claims/automation/admissions/history', { params }),

  // Claim Processing
  processClaim: (claimId) => api.post(`/pas/claims/automation/${claimId}/process`),
  getClaimPreview: (claimId) => api.get(`/pas/claims/automation/${claimId}/preview`),
  validateClaim: (claimId) => api.post(`/pas/claims/automation/${claimId}/validate`),
  classifyTreatments: (claimId) => api.post(`/pas/claims/automation/${claimId}/classify`),
  buildSections: (claimId) => api.post(`/pas/claims/automation/${claimId}/build-sections`),

  // Diagnosis Management
  addDiagnosis: (claimId, data) => api.post(`/pas/claims/automation/${claimId}/diagnoses`, data),

  // PA Code Management
  detectMissingPAs: (claimId) => api.get(`/pas/claims/automation/${claimId}/missing-pas`),

  // Treatment Management
  convertToFFS: (treatmentId, data) => api.post(`/pas/claims/automation/treatments/${treatmentId}/convert-to-ffs`, data),

  // Compliance Alerts
  getComplianceAlerts: (claimId) => api.get(`/pas/claims/automation/${claimId}/alerts`),
  resolveAlert: (alertId, data) => api.post(`/pas/claims/automation/alerts/${alertId}/resolve`, data),
  overrideAlert: (alertId, data) => api.post(`/pas/claims/automation/alerts/${alertId}/override`, data),
};

// Bundle Management API
export const bundleAPI = {
  getAll: (params) => api.get('/bundles', { params }),
  getById: (id) => api.get(`/bundles/${id}`),
  findByDiagnosis: (diagnosisCode) => api.get('/bundles/find-by-diagnosis', { params: { diagnosis_code: diagnosisCode } }),
  create: (data) => api.post('/bundles', data),
  update: (id, data) => api.put(`/bundles/${id}`, data),
  delete: (id) => api.delete(`/bundles/${id}`),
};

// Document Requirements API
export const documentRequirementAPI = {
  getAll: (params) => api.get('/document-requirements', { params }),
  getById: (id) => api.get(`/document-requirements/${id}`),
  create: (data) => api.post('/document-requirements', data),
  update: (id, data) => api.put(`/document-requirements/${id}`, data),
  delete: (id) => api.delete(`/document-requirements/${id}`),
  forReferral: () => api.get('/document-requirements/for-referral'),
  forPACode: () => api.get('/document-requirements/for-pa-code'),
  toggleStatus: (id) => api.post(`/document-requirements/${id}/toggle-status`),
  reorder: (items) => api.post('/document-requirements/reorder', { items }),
};

export default api;
