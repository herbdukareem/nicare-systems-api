import axios from 'axios';

const getApiBaseUrl = () => {
  if (import.meta.env.DEV) return import.meta.env.VITE_API_URL;
  return '/api';
};

const assistantHttp = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 45000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

assistantHttp.interceptors.request.use((config) => {
  const token = localStorage.getItem('token') || localStorage.getItem('enrollee_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export const assistantAPI = {
  chat: (data) => assistantHttp.post('/assistant/chat', data),
};
