import { defineStore } from 'pinia';
import axios from 'axios';

export const useStudentsStore = defineStore('students', {
    state: () => ({
        students: [],
        pagination: {
            current_page: 1,
            per_page: 15,
            total: 0,
            last_page: 1,
        },
        filters: {
            search: '',
            section_id: '',
            grade_level_id: '',
            status: '',
        },
        selectedStudent: null,
        loading: false,
        error: null,
        importResult: null,
        promotionResult: null,
    }),

    actions: {
        async fetchStudents(page = 1) {
            this.loading = true;
            this.error = null;
            try {
                const params = {
                    page,
                    per_page: this.pagination.per_page,
                };
                if (this.filters.search) params.search = this.filters.search;
                if (this.filters.section_id) params.section_id = this.filters.section_id;
                if (this.filters.grade_level_id) params.grade_level_id = this.filters.grade_level_id;
                if (this.filters.status) params.status = this.filters.status;

                const res = await axios.get('/students', { params });
                this.students = res.data.data;
                if (res.data.meta?.pagination) {
                    this.pagination = res.data.meta.pagination;
                }
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load students.';
            } finally {
                this.loading = false;
            }
        },

        async fetchStudent(id) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/students/${id}`);
                this.selectedStudent = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch student details.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async createStudent(payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.post('/students', payload);
                await this.fetchStudents(this.pagination.current_page);
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to create student.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updateStudent(id, payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.put(`/students/${id}`, payload);
                await this.fetchStudents(this.pagination.current_page);
                if (this.selectedStudent?.id === id) {
                    this.selectedStudent = { ...this.selectedStudent, ...res.data.data };
                }
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to update student.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async deleteStudent(id) {
            this.loading = true;
            this.error = null;
            try {
                await axios.delete(`/students/${id}`);
                await this.fetchStudents(this.pagination.current_page);
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete student.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async importCsv(formData) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.post('/students/import', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                this.importResult = res.data.data;
                await this.fetchStudents(1);
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to import CSV.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async promoteRoster(payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.post('/students/promote-roster', payload);
                this.promotionResult = res.data.data;
                await this.fetchStudents(1);
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to process promotion.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        setFilter(key, value) {
            this.filters[key] = value;
            this.fetchStudents(1);
        },

        resetFilters() {
            this.filters = {
                search: '',
                section_id: '',
                grade_level_id: '',
                status: '',
            };
            this.fetchStudents(1);
        },
    },
});
