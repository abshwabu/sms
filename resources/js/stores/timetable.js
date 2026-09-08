import { defineStore } from 'pinia';
import axios from 'axios';

export const useTimetableStore = defineStore('timetable', {
    state: () => ({
        sections: [],
        subjects: [],
        teachers: [],
        selectedSectionId: null,
        selectedTeacherId: null,
        selectedStudentId: null,
        students: [],
        sectionTimetable: null,
        teacherTimetable: null,
        studentTimetable: null,
        loading: false,
        actionLoading: false,
        error: null,
        conflictDetails: null,
        successMessage: null,
    }),

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

        async fetchSubjects(gradeLevelId = null) {
            try {
                const params = {};
                if (gradeLevelId) params.grade_level_id = gradeLevelId;
                const res = await axios.get('/subjects', { params });
                this.subjects = res.data.data || [];
                return this.subjects;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch subjects.';
                return [];
            }
        },

        async fetchTeachers() {
            try {
                const res = await axios.get('/staff');
                const staffList = res.data.data || [];
                this.teachers = staffList
                    .filter(s => s.user)
                    .map(s => s.user);
                if (this.teachers.length > 0 && !this.selectedTeacherId) {
                    this.selectedTeacherId = this.teachers[0].id;
                }
                return this.teachers;
            } catch (err) {
                return [];
            }
        },

        async fetchSectionTimetable(sectionId, dayOfWeek = null) {
            this.loading = true;
            this.error = null;
            this.conflictDetails = null;
            try {
                const params = {};
                if (dayOfWeek) params.day_of_week = dayOfWeek;
                const res = await axios.get(`/sections/${sectionId}/timetable`, { params });
                this.sectionTimetable = res.data.data;
                this.selectedSectionId = sectionId;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load section timetable.';
                this.sectionTimetable = null;
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchTeacherTimetable(teacherId = null) {
            this.loading = true;
            this.error = null;
            this.conflictDetails = null;
            try {
                const url = teacherId ? `/teachers/${teacherId}/timetable` : '/teacher/timetable';
                const res = await axios.get(url);
                this.teacherTimetable = res.data.data;
                if (teacherId) this.selectedTeacherId = teacherId;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load teacher timetable.';
                this.teacherTimetable = null;
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchStudents() {
            try {
                const res = await axios.get('/students');
                this.students = res.data.data || [];
                if (this.students.length > 0 && !this.selectedStudentId) {
                    this.selectedStudentId = this.students[0].id;
                }
                return this.students;
            } catch (err) {
                return [];
            }
        },

        async fetchStudentTimetable(studentId = null) {
            this.loading = true;
            this.error = null;
            this.conflictDetails = null;
            try {
                const url = studentId ? `/students/${studentId}/timetable` : '/student/timetable';
                const res = await axios.get(url);
                this.studentTimetable = res.data.data;
                if (studentId) this.selectedStudentId = studentId;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load student timetable.';
                this.studentTimetable = null;
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async createSlot(sectionId, slotData) {
            this.actionLoading = true;
            this.error = null;
            this.conflictDetails = null;
            this.successMessage = null;
            try {
                const res = await axios.post(`/sections/${sectionId}/timetable`, slotData);
                this.successMessage = 'Timetable slot scheduled successfully!';
                await this.fetchSectionTimetable(sectionId);
                return res.data;
            } catch (err) {
                const errData = err.response?.data?.error;
                this.error = errData?.message || 'Failed to create timetable slot.';
                this.conflictDetails = errData?.details || null;
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async deleteSlot(slotId, sectionId) {
            this.actionLoading = true;
            this.error = null;
            this.conflictDetails = null;
            try {
                await axios.delete(`/timetable-slots/${slotId}`);
                this.successMessage = 'Timetable slot removed successfully.';
                if (sectionId) {
                    await this.fetchSectionTimetable(sectionId);
                }
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete slot.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        clearMessages() {
            this.error = null;
            this.conflictDetails = null;
            this.successMessage = null;
        },
    },
});
