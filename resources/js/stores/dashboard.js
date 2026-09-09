import { defineStore } from 'pinia';
import axios from 'axios';
import { useAuthStore } from './auth';

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        data: null,
        loading: false,
        error: null,
        selectedChildId: null,
        activeRoleView: null,
    }),

    getters: {
        role: (state) => state.data?.role || null,
        isSuperAdmin: (state) => state.data?.role === 'super_admin',
        isSchoolAdmin: (state) => state.data?.role === 'school_admin',
        isTeacher: (state) => state.data?.role === 'teacher',
        isStudent: (state) => state.data?.role === 'student',
        isParent: (state) => state.data?.role === 'parent',
    },

    actions: {
        async fetchDashboard(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const authStore = useAuthStore();
                // Regular authenticated users can never be in role preview mode
                if (authStore.isAuthenticated && !authStore.isSuperAdmin) {
                    this.activeRoleView = null;
                }

                const queryParams = { ...params };
                if (this.activeRoleView && !queryParams.role && (authStore.isSuperAdmin || !authStore.isAuthenticated)) {
                    queryParams.role = this.activeRoleView;
                }
                if (this.selectedChildId && !queryParams.child_id && this.role === 'parent') {
                    queryParams.child_id = this.selectedChildId;
                }

                const res = await axios.get('/dashboard', { params: queryParams });
                this.data = res.data.data || null;

                if (this.data?.active_child_id) {
                    this.selectedChildId = this.data.active_child_id;
                }

                return this.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to load dashboard.';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async selectChild(childId) {
            this.selectedChildId = childId;
            return await this.fetchDashboard({ child_id: childId });
        },

        async previewAsRole(role) {
            const authStore = useAuthStore();
            if (authStore.isAuthenticated && !authStore.isSuperAdmin) {
                this.activeRoleView = null;
                return await this.fetchDashboard();
            }
            this.activeRoleView = role;
            return await this.fetchDashboard({ role });
        },

        async resetRolePreview() {
            this.activeRoleView = null;
        },
    },
});
