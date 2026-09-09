import { defineStore } from 'pinia';
import axios from 'axios';
import { useAuthStore } from './auth';

export const useTenantStore = defineStore('tenant', {
    state: () => ({
        schools: [],
        currentSchool: null,
        loading: false,
        error: null,
    }),

    getters: {
        hasTenant: (state) => !!state.currentSchool,
        activeSchoolId: (state) => state.currentSchool?.id || null,
        activeSchoolName: (state) => state.currentSchool?.name || 'No Tenant Selected',
        activeSubdomain: (state) => state.currentSchool?.subdomain || null,
    },

    actions: {
        async fetchSchools() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/schools');
                this.schools = response.data.data;

                const authStore = useAuthStore();
                // If user is authenticated and not super admin, ALWAYS lock to their own school
                if (authStore.isAuthenticated && !authStore.isSuperAdmin) {
                    if (authStore.schoolContext) {
                        this.selectSchool(authStore.schoolContext);
                    }
                    return;
                }

                // If super admin, allow restoring selected school
                if (authStore.isAuthenticated && authStore.isSuperAdmin) {
                    const savedId = localStorage.getItem('active_school_id');
                    if (savedId && !this.currentSchool) {
                        const match = this.schools.find((s) => s.id === parseInt(savedId, 10));
                        if (match) {
                            this.selectSchool(match);
                        }
                    }
                }
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch schools.';
            } finally {
                this.loading = false;
            }
        },

        selectSchool(school) {
            const authStore = useAuthStore();

            // Non-super-admins cannot switch to a school they do not belong to
            if (authStore.isAuthenticated && !authStore.isSuperAdmin && authStore.schoolContext) {
                if (school && school.id !== authStore.schoolContext.id) {
                    // Refuse cross-tenant switch and enforce assigned school
                    this.currentSchool = authStore.schoolContext;
                    localStorage.setItem('active_school_id', authStore.schoolContext.id.toString());
                    localStorage.setItem('active_school_subdomain', authStore.schoolContext.subdomain);
                    return;
                }
            }

            this.currentSchool = school;
            if (school) {
                localStorage.setItem('active_school_id', school.id.toString());
                localStorage.setItem('active_school_subdomain', school.subdomain);
            } else {
                localStorage.removeItem('active_school_id');
                localStorage.removeItem('active_school_subdomain');
            }
        },

        clearTenant() {
            this.currentSchool = null;
            localStorage.removeItem('active_school_id');
            localStorage.removeItem('active_school_subdomain');
        },
    },
});
