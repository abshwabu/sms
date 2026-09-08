import { defineStore } from 'pinia';
import axios from 'axios';

export const useAttendanceStore = defineStore('attendance', {
    state: () => ({
        sections: [],
        selectedSectionId: null,
        selectedDate: new Date().toISOString().split('T')[0],
        sectionAttendance: null,
        sectionSummary: null,
        studentSummary: null,
        calendarEvents: [],
        loading: false,
        actionLoading: false,
        error: null,
        successMessage: null,
    }),

    getters: {
        currentRoster: (state) => state.sectionAttendance?.roster || [],
        currentStats: (state) => state.sectionAttendance?.stats || null,
        isSchoolDay: (state) => state.sectionAttendance?.is_school_day ?? true,
    },

    actions: {
        async fetchSections() {
            try {
                const res = await axios.get('/sections');
                this.sections = res.data.data || [];
                if (this.sections.length > 0 && !this.selectedSectionId) {
                    this.selectedSectionId = this.sections[0].id;
                }
                return this.sections;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch sections.';
                return [];
            }
        },

        async fetchSectionDailyAttendance(sectionId = null, date = null) {
            const secId = sectionId || this.selectedSectionId;
            const targetDate = date || this.selectedDate;

            if (!secId) return null;

            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/sections/${secId}/attendance`, {
                    params: { date: targetDate }
                });
                this.sectionAttendance = res.data.data;
                this.selectedSectionId = secId;
                this.selectedDate = targetDate;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load section attendance.';
                this.sectionAttendance = null;
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async markDailyAttendance(sectionId, { date, default_status = null, records = [] }) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post(`/sections/${sectionId}/attendance`, {
                    date,
                    default_status,
                    records,
                });
                this.successMessage = 'Daily attendance saved successfully!';
                // Refresh roster data
                await this.fetchSectionDailyAttendance(sectionId, date);
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to record attendance.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async fetchSectionSummary(sectionId, startDate = null, endDate = null) {
            this.loading = true;
            this.error = null;
            try {
                const params = {};
                if (startDate) params.start_date = startDate;
                if (endDate) params.end_date = endDate;

                const res = await axios.get(`/sections/${sectionId}/attendance-summary`, { params });
                this.sectionSummary = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load section summary.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchStudentSummary(studentId, params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/students/${studentId}/attendance-summary`, { params });
                this.studentSummary = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load student attendance summary.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchParentChildAttendance(studentId, params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/parent/children/${studentId}/attendance`, { params });
                this.studentSummary = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load child attendance.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchCalendar(params = {}) {
            try {
                const res = await axios.get('/calendar', { params });
                this.calendarEvents = res.data.data || [];
                return this.calendarEvents;
            } catch (err) {
                return [];
            }
        },

        async storeCalendarDay(payload) {
            this.actionLoading = true;
            try {
                const res = await axios.post('/calendar', payload);
                this.successMessage = 'Calendar day updated successfully!';
                await this.fetchCalendar();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to save calendar day.';
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
