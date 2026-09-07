import { defineStore } from 'pinia';
import axios from 'axios';

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

                // Restore previously selected school if available
                const savedId = localStorage.getItem('active_school_id');
                if (savedId && !this.currentSchool) {
                    const match = this.schools.find((s) => s.id === parseInt(savedId, 10));
                    if (match) {
                        this.currentSchool = match;
                    } else if (this.schools.length > 0) {
                        this.selectSchool(this.schools[0]);
                    }
                } else if (!this.currentSchool && this.schools.length > 0) {
                    this.selectSchool(this.schools[0]);
                }
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch schools.';
            } finally {
                this.loading = false;
            }
        },

        selectSchool(school) {
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
            this.selectSchool(null);
        },
    },
});
