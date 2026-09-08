import { defineStore } from 'pinia';
import axios from 'axios';

export const useStaffStore = defineStore('staff', {
    state: () => ({
        staffList: [],
        pagination: {
            current_page: 1,
            per_page: 15,
            total: 0,
            last_page: 1,
        },
        filters: {
            search: '',
            department: '',
            role_title: '',
            status: '',
            course_id: '',
        },
        selectedStaff: null,
        loading: false,
        error: null,
    }),

    actions: {
        async fetchStaff(page = 1) {
            this.loading = true;
            this.error = null;
            try {
                const params = {
                    page,
                    per_page: this.pagination.per_page,
                };
                if (this.filters.search) params.search = this.filters.search;
                if (this.filters.department) params.department = this.filters.department;
                if (this.filters.role_title) params.role_title = this.filters.role_title;
                if (this.filters.status) params.status = this.filters.status;
                if (this.filters.course_id) params.course_id = this.filters.course_id;

                const res = await axios.get('/staff', { params });
                this.staffList = res.data.data;
                if (res.data.meta?.pagination) {
                    this.pagination = res.data.meta.pagination;
                }
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch staff directory.';
            } finally {
                this.loading = false;
            }
        },

        async fetchStaffMember(id) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/staff/${id}`);
                this.selectedStaff = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch staff details.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async createStaffMember(payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.post('/staff', payload);
                await this.fetchStaff(this.pagination.current_page);
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to create staff member.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updateStaffMember(id, payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.put(`/staff/${id}`, payload);
                await this.fetchStaff(this.pagination.current_page);
                if (this.selectedStaff?.id === id) {
                    this.selectedStaff = { ...this.selectedStaff, ...res.data.data };
                }
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to update staff member.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async deleteStaffMember(id) {
            this.loading = true;
            this.error = null;
            try {
                await axios.delete(`/staff/${id}`);
                await this.fetchStaff(this.pagination.current_page);
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete staff member.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async assignHomeroom(sectionId, teacherId) {
            const res = await axios.post(`/sections/${sectionId}/assign-homeroom`, {
                teacher_id: teacherId,
            });
            return res.data.data;
        },

        async assignSubjectTeacher(sectionId, courseId, staffId) {
            const res = await axios.post(`/sections/${sectionId}/assign-subject-teacher`, {
                course_id: courseId,
                staff_id: staffId,
            });
            return res.data.data;
        },

        async removeSubjectTeacher(sectionId, assignmentId) {
            const res = await axios.delete(`/sections/${sectionId}/subject-teachers/${assignmentId}`);
            return res.data;
        },

        setFilter(key, value) {
            this.filters[key] = value;
            this.fetchStaff(1);
        },

        resetFilters() {
            this.filters = {
                search: '',
                department: '',
                role_title: '',
                status: '',
                course_id: '',
            };
            this.fetchStaff(1);
        },
    },
});
