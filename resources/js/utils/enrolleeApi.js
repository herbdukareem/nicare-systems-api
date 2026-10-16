import axios from 'axios';
import { finishGlobalRequest, startGlobalRequest } from './globalRequestLoader';

const getApiBaseUrl = () => {
  if (import.meta.env.DEV) return import.meta.env.VITE_API_URL;
  return '/api';
};

const enrolleeHttp = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 30000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

enrolleeHttp.interceptors.request.use((config) => {
  const token = localStorage.getItem('enrollee_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return startGlobalRequest(config);
});

enrolleeHttp.interceptors.response.use(
  (response) => {
    finishGlobalRequest(response.config);
    return response;
  },
  (error) => {
    finishGlobalRequest(error.config);

    if (error?.response?.status === 401) {
      window.dispatchEvent(new Event('enrollee:unauthorized'));
    }
    return Promise.reject(error);
  }
);

export const enrolleePortalAPI = {
  login:          (data) => enrolleeHttp.post('/enroll/login', data),
  logout:         () => enrolleeHttp.post('/enroll/logout'),
  me:             () => enrolleeHttp.get('/enroll/me'),
  changePassword: (data) => enrolleeHttp.post('/enroll/change-password', data),
  plans:          () => enrolleeHttp.get('/enroll/plans'),
  renewPlan:      (data) => enrolleeHttp.post('/enroll/renewals', data),
  verifyRenewal:  (reference) => enrolleeHttp.get(`/enroll/renewals/${encodeURIComponent(reference)}/verify`),
};

export const publicEnrollmentAPI = {
  metadata: (params = {}) => enrolleeHttp.get('/public/enrollment/metadata', { params }),
  createApplication: (data) => enrolleeHttp.post('/public/enrollment/applications', data, {
    headers: data instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : undefined,
  }),
  verifyPayment: (reference, token) => enrolleeHttp.get(`/public/enrollment/payments/${encodeURIComponent(reference)}/verify`, {
    params: { token },
  }),
  purchasePins: (data) => enrolleeHttp.post('/public/enrollment/pin-purchases', data),
  verifyPinPurchase: (reference, token) => enrolleeHttp.get(`/public/enrollment/pin-purchases/${encodeURIComponent(reference)}/verify`, {
    params: { token },
  }),
  downloadPinDocket: (reference, token) => enrolleeHttp.get(`/public/enrollment/pin-purchases/${encodeURIComponent(reference)}/docket`, {
    params: { token },
    responseType: 'blob',
  }),
};
