import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF Token setup for Laravel Sanctum
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.warn('CSRF token not found. This is expected when running as SPA with separate frontend.');
}

// Set up axios defaults for SPA authentication
window.axios.defaults.withCredentials = true;
window.axios.defaults.baseURL = import.meta.env.VITE_API_URL ;
