import axios from 'axios';

window.axios = axios;

axios.defaults.baseURL = '/api';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Interceptor to attach active tenant context header (X-School-Id) and Bearer token
axios.interceptors.request.use((config) => {
    const activeTenant = localStorage.getItem('active_school_id') || localStorage.getItem('active_school_subdomain');
    if (activeTenant) {
        config.headers['X-School-Id'] = activeTenant;
    }

    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers['Authorization'] = `Bearer ${token}`;
    }

    return config;
}, (error) => {
    return Promise.reject(error);
});

// Response interceptor to handle session expiry
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        }
        return Promise.reject(error);
    }
);
