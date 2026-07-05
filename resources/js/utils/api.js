import axios from 'axios';
import { useUiStore } from '../stores/ui';

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

const globalLoaderOptions = (config = {}) => {
  const method = (config.method || 'get').toUpperCase();
  const isDownload = config.responseType === 'blob';

  if (config.loaderTitle || config.loaderSubtitle) {
    return {
      title: config.loaderTitle,
      subtitle: config.loaderSubtitle,
    };
  }

  if (isDownload) {
    return {
      title: 'Preparing download',
      subtitle: 'Generating your file from the server',
    };
  }

  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    return {
      title: 'Processing request',
      subtitle: 'Saving your changes securely',
    };
  }

  return {
    title: 'Loading data',
    subtitle: 'Fetching the latest records from the server',
  };
};

const startGlobalLoader = (config) => {
  if (config.showGlobalLoader === false) return config;

  config.__globalLoader = true;
  useUiStore().startRequestLoading(globalLoaderOptions(config));
  return config;
};

const finishGlobalLoader = (config) => {
  if (config?.__globalLoader) {
    useUiStore().finishRequestLoading();
  }
};

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return startGlobalLoader(config);
  },
  (error) => Promise.reject(error)
);


api.interceptors.response.use(
  (response) => {
    finishGlobalLoader(response.config);
    return response;
  },
  (error) => {
    finishGlobalLoader(error.config);

    const status = error?.response?.status;
    const requestUrl = error?.config?.url || '';
    const authEndpoint = ['/login', '/logout', '/user'].some((endpoint) => requestUrl.endsWith(endpoint));

    if (status === 401 && !authEndpoint) {
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
  getAll: (params, config = {}) => api.get('/enrollees', { ...config, params }),
  integritySummary: (params) => api.get('/enrollees/integrity/summary', { params }),
  getDuplicateFlags: (params) => api.get('/enrollees/duplicates', { params }),
  resolveDuplicateFlag: (id, data) => api.post(`/enrollees/duplicates/${id}/resolve`, data),
  getById: (id) => api.get(`/enrollees/${id}`),
  create: (data) => api.post('/enrollees', data),
  verifyNin: (id, data = {}) => api.post(`/enrollees/${id}/verify-nin`, data),
  approve: (id, data = {}) => api.post(`/enrollees/${id}/approve`, data),
  pendingApproval: (params) => api.get('/enrollees/pending-approval', { params }),
  update: (id, data) => api.put(`/enrollees/${id}`, data),
  updateStatus: (id, data) => api.put(`/enrollees/${id}/status`, data),
  uploadPassport: (id, data) => api.post(`/enrollees/${id}/upload-passport`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  bulkUpdateStatus: (data) => api.post('/enrollees/bulk-update-status', data),
  delete: (id) => api.delete(`/enrollees/${id}`),
  idCard: (id) => api.get(`/enrollees/${id}/id-card`, { responseType: 'blob' }),
  bulkEnrollmentSlip: (params) => api.get('/enrollees/bulk-enrollment-slip', {
    params,
    responseType: 'blob',
    timeout: 120000,
  }),
  bulkIdCard: (params) => api.get('/enrollees/bulk-id-card', { params, responseType: 'blob', timeout: 120000 }),
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
  getStatistics: (id) => api.get(`/enrollees/${id}/statistics`),
};

export const dashboardAPI = {
  getOverview: () => api.get('/dashboard/overview'),
  getEnrolleeStats: () => api.get('/dashboard/enrollee-stats'),
  getFacilityStats: () => api.get('/dashboard/facility-stats'),
  getChartData: () => api.get('/dashboard/chart-data'),
  getRecentActivities: () => api.get('/dashboard/recent-activities'),
  getLgas: () => api.get('/lgas'),
  getBenefactors: () => api.get('/benefactors'),
  getEnrollmentTrend: (params) => api.get('/dashboard/enrollment-trend', { params }),
  getWardsByLga: (lgaId) => api.get('/dashboard/wards-by-lga', { params: { lga_id: lgaId } }),
  getCapitationSummary: () => api.get('/dashboard/capitation-summary'),
};

export const capitationAPI = {
  periods: (params) => api.get('/capitation/periods', { params }),
  createPeriod: (data) => api.post('/capitation/periods', data),
  showPeriod: (id) => api.get(`/capitation/periods/${id}`),
  compute: (id, payload = {}) => api.post(`/capitation/periods/${id}/compute`, payload),
  eligibleProviders: (id, params = {}) => api.get(`/capitation/periods/${id}/eligible-providers`, { params }),
  details: (id, params = {}) => api.get(`/capitation/periods/${id}/details`, { params }),
  reviewDetails: (id, payload) => api.post(`/capitation/periods/${id}/details/review`, payload),
  approveDetails: (id, payload) => api.post(`/capitation/periods/${id}/details/approve`, payload),
  payDetails: (id, payload) => api.post(`/capitation/periods/${id}/details/pay`, payload),
  breakdown: (id, params = {}) => api.get(`/capitation/periods/${id}/breakdown`, { params }),
  finalise: (id) => api.post(`/capitation/periods/${id}/finalise`),
  pay: (id, payload) => api.post(`/capitation/periods/${id}/pay`, payload),
  export: (id) => api.get(`/capitation/periods/${id}/export`, { responseType: 'blob' }),
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
  create: (data) => api.post('/lgas', data),
  update: (id, data) => api.put(`/lgas/${id}`, data),
  delete: (id) => api.delete(`/lgas/${id}`),
  wards: (id) => api.get(`/lgas/${id}/wards`),
};

export const wardAPI = {
  getAll: (params) => api.get('/wards', { params }),
  getById: (id) => api.get(`/wards/${id}`),
  create: (data) => api.post('/wards', data),
  update: (id, data) => api.put(`/wards/${id}`, data),
  delete: (id) => api.delete(`/wards/${id}`),
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
  export: (params) => api.get('/users/export', { params, responseType: 'blob' }),
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

export const fundingTypeAPI = {
  getAll: (params) => api.get('/funding-types', { params }),
  getById: (id) => api.get(`/funding-types/${id}`),
  create: (data) => api.post('/funding-types', data),
  update: (id, data) => api.put(`/funding-types/${id}`, data),
  delete: (id) => api.delete(`/funding-types/${id}`),
};

export const benefitPackageAPI = {
  getAll: (params) => api.get('/benefit-packages', { params }),
  getById: (id) => api.get(`/benefit-packages/${id}`),
  create: (data) => api.post('/benefit-packages', data),
  update: (id, data) => api.put(`/benefit-packages/${id}`, data),
  delete: (id) => api.delete(`/benefit-packages/${id}`),
};

export const premiumAPI = {
  dashboard: () => api.get('/premium/dashboard'),
  metadata: () => api.get('/premium/metadata'),
  plans: (params) => api.get('/premium/plans', { params }),
  createPlan: (data) => api.post('/premium/plans', data),
  updatePlan: (id, data) => api.put(`/premium/plans/${id}`, data),
  deletePlan: (id) => api.delete(`/premium/plans/${id}`),
  pins: (params) => api.get('/premium/pins', { params }),
  getPin: (id) => api.get(`/premium/pins/${id}`),
  generatePins: (data) => api.post('/premium/pins/generate', data),
  sellPin: (id, data) => api.post(`/premium/pins/${id}/sell`, data),
  validatePin: (data) => api.post('/premium/pins/validate', data),
  usePin: (id, data) => api.post(`/premium/pins/${id}/use`, data),
  cancelPin: (id) => api.post(`/premium/pins/${id}/cancel`),
  purchases: (params) => api.get('/premium/purchases', { params }),
  createPurchase: (data) => api.post('/premium/purchases', data),
  confirmPurchase: (id) => api.post(`/premium/purchases/${id}/confirm`),
  cancelPurchase: (id) => api.post(`/premium/purchases/${id}/cancel`),
  checkoutPurchase: (id) => api.post(`/premium/purchases/${id}/checkout`),
  verifyPurchase: (id) => api.post(`/premium/purchases/${id}/verify`),
  payrollBatches: (params) => api.get('/premium/payroll-batches', { params }),
  createPayrollBatch: (data) => api.post('/premium/payroll-batches', data),
  approvePayrollBatch: (id) => api.post(`/premium/payroll-batches/${id}/approve`),
  eligibility: (params) => api.get('/premium/eligibility', { params }),
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

export const ninProviderAPI = {
  getConfig: () => api.get('/settings/nin-provider'),
  updateConfig: (data) => api.put('/settings/nin-provider', data),
};

export const paymentGatewaySettingsAPI = {
  getConfig: () => api.get('/settings/payment-gateways'),
  updateConfig: (data) => api.put('/settings/payment-gateways', data),
};

export const organizationSettingsAPI = {
  getPublic: () => api.get('/organization-settings'),
  getConfig: () => api.get('/settings/organization'),
  updateConfig: (data) => api.put('/settings/organization', data),
  uploadLogo: (formData) => api.post('/settings/organization/logo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  removeLogo: () => api.delete('/settings/organization/logo'),
};

export const enrollmentSchemaAPI = {
  list: (params) => api.get('/enrollment-form-schemas', { params }),
  get: (id) => api.get(`/enrollment-form-schemas/${id}`),
  create: (data) => api.post('/enrollment-form-schemas', data),
  update: (id, data) => api.put(`/enrollment-form-schemas/${id}`, data),
  publish: (id) => api.post(`/enrollment-form-schemas/${id}/publish`),
  revoke: (id) => api.post(`/enrollment-form-schemas/${id}/revoke`),
  archive: (id) => api.delete(`/enrollment-form-schemas/${id}`),
};

export const officerDeviceAPI = {
  list: (params) => api.get('/officer-devices', { params }),
  revoke: (id) => api.post(`/officer-devices/${id}/revoke`),
  assignments: (params) => api.get('/officer-enrollment-assignments', { params }),
  assignEnrollment: (data) => api.post('/officer-enrollment-assignments', data),
  updateAssignment: (id, data) => api.patch(`/officer-enrollment-assignments/${id}`, data),
  removeAssignment: (id) => api.delete(`/officer-enrollment-assignments/${id}`),
  setEnrollmentStatus: (userId, enabled) => api.patch(`/users/${userId}/mobile-enrollment-status`, { enabled }),
};

export const securityAPI = {
  getDashboard: () => api.get('/pas/security/dashboard'),
  getLogs: (params) => api.get('/pas/security/logs', { params }),
  resolve: (id, data) => api.post(`/pas/security/logs/${id}/resolve`, data),
  bulkResolve: (data) => api.post('/pas/security/logs/bulk-resolve', data),
  getAuditTrail: (params) => api.get('/pas/security/audit-trail', { params }),
  getSessions: () => api.get('/pas/security/sessions'),
  revokeSessions: (data) => api.post('/pas/security/sessions/revoke', data),
};

// PAS (Pre-Authorization System) API
export const pasAPI = {
  // Referral APIs
  getReferrals: (params) => api.get('/referrals', { params }),
  createReferral: (data) => api.post('/referrals', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  getReferral: (id) => api.get(`/referrals/${id}`),
  getReferralByCode: (code) => api.get('/referrals', { params: { search: code } }),
  approveReferral: (id, data) => api.post(`/referrals/${id}/approve`, data),
  denyReferral: (id, data) => api.post(`/referrals/${id}/reject`, data),

  // PA Code APIs
  getPACodes: (params) => api.get('/pas/pa-codes', { params }),
  generatePACode: (data) => api.post('/pas/pa-codes', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  getPACode: (id) => api.get(`/pas/pa-codes/${id}`),
  getPACodeById: (id) => api.get(`/pas/pa-codes/${id}`), // Alias for consistency

  // UTN Validation APIs
  validateUTN: (data) => api.post('/do-dashboard/validate-utn', data),
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
  getFullDetails: (id) => api.get(`/pas/claims/${id}/full-details`),
  create: (data) => api.post('/claims-automation/claims', data),
  submit: (id) => api.post(`/claims-automation/claims/${id}/submit`),
  getClaims: (params) => api.get('/pas/claims', { params }),
  // Review and batch operations
  reviewClaim: (id, data) => api.post(`/pas/claims/${id}/review`, data),
  batchApprove: (data) => api.post('/pas/claims/batch-approve', data),
  batchReject: (data) => api.post('/pas/claims/batch-reject', data),
  downloadSlip: (id) => api.get(`/pas/claims/${id}/slip`, { responseType: 'blob' }),
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

// Claims Automation API - aligned to existing backend endpoints
export const claimsAutomationAPI = {
  createAdmission: (data) => api.post('/claims-automation/admissions', data),
  dischargePatient: (admissionId, data) => api.post(`/claims-automation/admissions/${admissionId}/discharge`, data),
  getAdmissionByEnrollee: (enrolleeId) => api.get(`/claims-automation/admissions/enrollee/${enrolleeId}`),
  checkAdmissionEligibility: (referralId) => api.get(`/claims-automation/admissions/check/${referralId}`),
  validateClaim: (claimId) => api.post(`/claims-automation/claims/${claimId}/validate`),
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
