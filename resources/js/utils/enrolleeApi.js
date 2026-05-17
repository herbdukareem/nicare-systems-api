import axios from 'axios';

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
  return config;
});

enrolleeHttp.interceptors.response.use(
  (response) => response,
  (error) => {
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
};
