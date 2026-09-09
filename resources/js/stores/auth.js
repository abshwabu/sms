import { defineStore } from 'pinia';
import axios from 'axios';
import { useTenantStore } from './tenant';
import { useDashboardStore } from './dashboard';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('auth_user') || 'null'),
        token: localStorage.getItem('auth_token') || null,
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token && !!state.user,
        role: (state) => state.user?.role || null,
        isSuperAdmin: (state) => state.user?.role === 'super_admin',
        isSchoolAdmin: (state) => state.user?.role === 'school_admin' || state.user?.role === 'super_admin',
        isTeacher: (state) => state.user?.role === 'teacher',
        isStudent: (state) => state.user?.role === 'student',
        isParent: (state) => state.user?.role === 'parent',
        schoolContext: (state) => state.user?.school || null,
    },

    actions: {
        async login(email, password) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.post('/auth/login', { email, password });
                const { token, user } = res.data.data;

                this.token = token;
                this.user = user;
                localStorage.setItem('auth_token', token);
                localStorage.setItem('auth_user', JSON.stringify(user));

                // Always clear any leftover role preview mode
                const dashboardStore = useDashboardStore();
                dashboardStore.resetRolePreview();
                dashboardStore.data = null;

                // Sync tenant store with user's school if available
                const tenantStore = useTenantStore();
                if (user.school) {
                    tenantStore.selectSchool(user.school);
                } else if (!user.role === 'super_admin') {
                    tenantStore.clearTenant();
                }

                return { success: true, user };
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Login failed.';
                return { success: false, error: this.error };
            } finally {
                this.loading = false;
            }
        },

        async register(payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.post('/auth/register', payload);
                const { token, user } = res.data.data;

                this.token = token;
                this.user = user;
                localStorage.setItem('auth_token', token);
                localStorage.setItem('auth_user', JSON.stringify(user));

                // Clear any leftover role preview mode
                const dashboardStore = useDashboardStore();
                dashboardStore.resetRolePreview();
                dashboardStore.data = null;

                const tenantStore = useTenantStore();
                if (user.school) {
                    tenantStore.selectSchool(user.school);
                } else {
                    tenantStore.clearTenant();
                }

                return { success: true, user };
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Registration failed.';
                return { success: false, error: this.error };
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                if (this.token) {
                    await axios.post('/auth/logout');
                }
            } catch (err) {
                // Ignore logout network errors
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('auth_token');
                localStorage.removeItem('auth_user');
                localStorage.removeItem('active_school_id');
                localStorage.removeItem('active_school_subdomain');

                const tenantStore = useTenantStore();
                tenantStore.clearTenant();

                const dashboardStore = useDashboardStore();
                dashboardStore.resetRolePreview();
                dashboardStore.data = null;
            }
        },

        async fetchCurrentUser() {
            if (!this.token) return null;
            try {
                const res = await axios.get('/auth/me');
                this.user = res.data.data;
                localStorage.setItem('auth_user', JSON.stringify(this.user));

                const tenantStore = useTenantStore();
                if (this.user.school) {
                    tenantStore.selectSchool(this.user.school);
                }

                return this.user;
            } catch (err) {
                this.logout();
                return null;
            }
        },
    },
});
