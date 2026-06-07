import axios from 'axios';
import { useUiStore } from '../stores/ui';

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

const globalLoaderOptions = (config = {}) => {
  const method = (config.method || 'get').toUpperCase();

  if (config.loaderTitle || config.loaderSubtitle) {
    return {
      title: config.loaderTitle,
      subtitle: config.loaderSubtitle,
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

enrolleeHttp.interceptors.request.use((config) => {
  const token = localStorage.getItem('enrollee_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return startGlobalLoader(config);
});

enrolleeHttp.interceptors.response.use(
  (response) => {
    finishGlobalLoader(response.config);
    return response;
  },
  (error) => {
    finishGlobalLoader(error.config);

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

export const publicEnrollmentAPI = {
  metadata: (params = {}) => enrolleeHttp.get('/public/enrollment/metadata', { params }),
  createApplication: (data) => enrolleeHttp.post('/public/enrollment/applications', data),
};
