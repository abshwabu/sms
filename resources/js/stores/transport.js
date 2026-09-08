import { defineStore } from 'pinia';
import axios from 'axios';

export const useTransportStore = defineStore('transport', {
    state: () => ({
        routes: [],
        currentRoute: null,
        assignments: [],
        myTransport: null,
        childTransport: {},
        loading: false,
        actionLoading: false,
        error: null,
        successMessage: null,
    }),

    actions: {
        clearMessages() {
            this.error = null;
            this.successMessage = null;
        },

        async fetchRoutes(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/transport/routes', { params });
                this.routes = res.data.data || [];
                return this.routes;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load transport routes.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async fetchRoute(routeId) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/transport/routes/${routeId}`);
                this.currentRoute = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load route details.';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async createRoute(routeData) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post('/transport/routes', routeData);
                this.successMessage = 'Transport route created successfully!';
                await this.fetchRoutes();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to create route.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async updateRoute(routeId, routeData) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.put(`/transport/routes/${routeId}`, routeData);
                this.successMessage = 'Transport route updated successfully!';
                await this.fetchRoutes();
                if (this.currentRoute && this.currentRoute.id === routeId) {
                    await this.fetchRoute(routeId);
                }
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to update route.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async deleteRoute(routeId) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                await axios.delete(`/transport/routes/${routeId}`);
                this.successMessage = 'Transport route removed successfully.';
                this.routes = this.routes.filter(r => r.id !== routeId);
                if (this.currentRoute && this.currentRoute.id === routeId) {
                    this.currentRoute = null;
                }
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete route.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async addStop(routeId, stopData) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post(`/transport/routes/${routeId}/stops`, stopData);
                this.successMessage = 'Bus stop added successfully!';
                await this.fetchRoute(routeId);
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to add stop.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async updateStop(stopId, stopData, routeId) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.put(`/transport/stops/${stopId}`, stopData);
                this.successMessage = 'Bus stop updated!';
                if (routeId) {
                    await this.fetchRoute(routeId);
                }
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to update stop.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async deleteStop(stopId, routeId) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                await axios.delete(`/transport/stops/${stopId}`);
                this.successMessage = 'Bus stop deleted.';
                if (routeId) {
                    await this.fetchRoute(routeId);
                }
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete stop.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async fetchAssignments(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/transport/assignments', { params });
                this.assignments = res.data.data || [];
                return this.assignments;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load assignments.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async assignStudent(payload) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post('/transport/assignments', payload);
                this.successMessage = 'Student assigned to route successfully!';
                await this.fetchAssignments();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to assign student.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async bulkAssignSection(sectionId, routeId, payload) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post(`/transport/sections/${sectionId}/routes/${routeId}/assign`, payload);
                const count = res.data.data?.assigned_count || 0;
                this.successMessage = `Successfully bulk assigned ${count} student(s) to this route & stop!`;
                await this.fetchAssignments();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to bulk assign section.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async unassignStudent(studentId) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                await axios.delete(`/transport/students/${studentId}/assignment`);
                this.successMessage = 'Student transport assignment removed.';
                this.assignments = this.assignments.filter(a => a.student_id !== studentId);
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to unassign student.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async fetchMyStudentTransport() {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/student/transport');
                this.myTransport = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load transport details.';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async fetchChildTransport(studentId) {
            this.error = null;
            try {
                const res = await axios.get(`/parent/children/${studentId}/transport`);
                this.childTransport[studentId] = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load child transport schedule.';
                return null;
            }
        },
    },
});
