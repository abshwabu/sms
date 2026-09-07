import axios from 'axios';

window.axios = axios;

axios.defaults.baseURL = '/api';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Interceptor to attach active tenant context header (X-School-Id)
axios.interceptors.request.use((config) => {
    const activeTenant = localStorage.getItem('active_school_id') || localStorage.getItem('active_school_subdomain');
    if (activeTenant) {
        config.headers['X-School-Id'] = activeTenant;
    }
    return config;
}, (error) => {
    return Promise.reject(error);
});
