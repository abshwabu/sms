import { defineStore } from 'pinia';
import axios from 'axios';

function extractErrorMessage(err, defaultMsg) {
    const errData = err.response?.data;
    if (errData?.error?.details && typeof errData.error.details === 'object') {
        const messages = Object.values(errData.error.details).flat();
        if (messages.length) return messages.join(' ');
    }
    return errData?.error?.message || errData?.message || defaultMsg;
}

export const useParentStore = defineStore('parent', {
    state: () => ({
        children: [],
        activeChild: null,
        childDashboard: null,
        parents: [],
        pagination: {
            current_page: 1,
            per_page: 15,
            total: 0,
            last_page: 1,
        },
        searchQuery: '',
        loading: false,
        dashboardLoading: false,
        actionLoading: false,
        error: null,
        successMessage: null,
    }),

    actions: {
        async fetchChildren() {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/parent/children');
                this.children = res.data.data || [];
                if (this.children.length > 0 && !this.activeChild) {
                    await this.selectChild(this.children[0]);
                }
                return this.children;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch linked children.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async selectChild(child) {
            this.activeChild = child;
            if (child?.id) {
                await this.fetchChildDashboard(child.id);
            }
        },

        async fetchChildDashboard(studentId) {
            this.dashboardLoading = true;
            this.error = null;
            try {
                const res = await axios.get(`/parent/children/${studentId}/dashboard`);
                this.childDashboard = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load child dashboard.';
                this.childDashboard = null;
                throw err;
            } finally {
                this.dashboardLoading = false;
            }
        },

        async fetchParents(page = 1) {
            this.loading = true;
            this.error = null;
            try {
                const params = {
                    page,
                    per_page: this.pagination.per_page,
                };
                if (this.searchQuery) {
                    params.search = this.searchQuery;
                }
                const res = await axios.get('/parents', { params });
                this.parents = res.data.data || [];
                if (res.data.meta?.pagination) {
                    this.pagination = res.data.meta.pagination;
                }
                return this.parents;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch parents directory.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async linkStudent(parentId, { student_id, relationship, is_primary_contact }) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post(`/parents/${parentId}/link-student`, {
                    student_id: Number(student_id),
                    relationship: relationship || 'guardian',
                    is_primary_contact: Boolean(is_primary_contact),
                });
                this.successMessage = 'Student linked to parent successfully!';
                await this.fetchParents(this.pagination.current_page);
                return res.data.data;
            } catch (err) {
                this.error = extractErrorMessage(err, 'Failed to link student to parent.');
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async unlinkStudent(parentId, studentId) {
            this.actionLoading = true;
            this.error = null;
            try {
                await axios.delete(`/parents/${parentId}/students/${studentId}`);
                this.successMessage = 'Student unlinked successfully!';
                await this.fetchParents(this.pagination.current_page);
            } catch (err) {
                this.error = extractErrorMessage(err, 'Failed to unlink student.');
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async createParent(payload) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post('/parents', payload);
                this.successMessage = 'Parent account created successfully!';
                await this.fetchParents(1);
                return res.data.data;
            } catch (err) {
                this.error = extractErrorMessage(err, 'Failed to create parent account.');
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async inviteParent(payload) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post('/parents/invite', payload);
                this.successMessage = 'Parent invitation sent successfully!';
                await this.fetchParents(1);
                return res.data.data;
            } catch (err) {
                this.error = extractErrorMessage(err, 'Failed to send parent invitation.');
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        clearMessages() {
            this.error = null;
            this.successMessage = null;
        }
    },
});
