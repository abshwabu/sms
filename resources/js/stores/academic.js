import { defineStore } from 'pinia';
import axios from 'axios';

export const useAcademicStore = defineStore('academic', {
    state: () => ({
        academicYears: [],
        terms: [],
        gradeLevels: [],
        sections: [],
        loading: false,
        error: null,
    }),

    getters: {
        activeYear: (state) => state.academicYears.find((y) => y.is_active) || null,
        closedYears: (state) => state.academicYears.filter((y) => y.is_closed),
        activeTerm: (state) => state.terms.find((t) => t.is_active) || null,
    },

    actions: {
        async fetchAll() {
            this.loading = true;
            this.error = null;
            try {
                await Promise.all([
                    this.fetchAcademicYears(),
                    this.fetchTerms(),
                    this.fetchGradeLevels(),
                    this.fetchSections(),
                ]);
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch academic data.';
            } finally {
                this.loading = false;
            }
        },

        async fetchAcademicYears() {
            const res = await axios.get('/academic-years');
            this.academicYears = res.data.data;
        },

        async createAcademicYear(payload) {
            const res = await axios.post('/academic-years', payload);
            await this.fetchAcademicYears();
            return res.data.data;
        },

        async closeAcademicYear(yearId) {
            const res = await axios.post(`/academic-years/${yearId}/close`);
            await this.fetchAcademicYears();
            await this.fetchTerms();
            return res.data.data;
        },

        async activateAcademicYear(yearId) {
            const res = await axios.post(`/academic-years/${yearId}/activate`);
            await this.fetchAcademicYears();
            await this.fetchTerms();
            return res.data.data;
        },

        async fetchTerms(yearId = null) {
            const params = yearId ? { academic_year_id: yearId } : {};
            const res = await axios.get('/terms', { params });
            this.terms = res.data.data || [];
            return this.terms;
        },

        async createTerm(payload) {
            const res = await axios.post('/terms', payload);
            await Promise.all([
                this.fetchTerms(),
                this.fetchAcademicYears(),
            ]);
            return res.data.data;
        },

        async addTerm(yearId, payload) {
            const res = await axios.post(`/academic-years/${yearId}/terms`, payload);
            await Promise.all([
                this.fetchTerms(),
                this.fetchAcademicYears(),
            ]);
            return res.data.data;
        },

        async activateTerm(termId) {
            const res = await axios.post(`/terms/${termId}/activate`);
            await this.fetchTerms();
            return res.data.data;
        },

        async deleteTerm(termId) {
            const res = await axios.delete(`/terms/${termId}`);
            await Promise.all([
                this.fetchTerms(),
                this.fetchAcademicYears(),
            ]);
            return res.data.data;
        },

        async fetchGradeLevels() {
            const res = await axios.get('/grade-levels');
            this.gradeLevels = res.data.data;
        },

        async createGradeLevel(payload) {
            const res = await axios.post('/grade-levels', payload);
            await this.fetchGradeLevels();
            return res.data.data;
        },

        async fetchSections(yearId = null) {
            const params = yearId ? { academic_year_id: yearId } : {};
            const res = await axios.get('/sections', { params });
            this.sections = res.data.data;
        },

        async createSection(payload) {
            const res = await axios.post('/sections', payload);
            await this.fetchSections();
            return res.data.data;
        },

        async promoteStudents(payload) {
            const res = await axios.post('/sections/promote', payload);
            await this.fetchSections();
            return res.data.data;
        },
    },
});
