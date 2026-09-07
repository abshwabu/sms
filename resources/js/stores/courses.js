import { defineStore } from 'pinia';
import axios from 'axios';

export const useCoursesStore = defineStore('courses', {
    state: () => ({
        courses: [],
        loading: false,
        error: null,
        validationErrors: null,
    }),

    actions: {
        async fetchCourses() {
            this.loading = true;
            this.error = null;
            this.validationErrors = null;
            try {
                const response = await axios.get('/courses');
                this.courses = response.data.data;
            } catch (err) {
                this.courses = [];
                this.error = err.response?.data?.error?.message || 'Failed to fetch courses.';
            } finally {
                this.loading = false;
            }
        },

        async createCourse(courseData) {
            this.loading = true;
            this.error = null;
            this.validationErrors = null;
            try {
                const response = await axios.post('/courses', courseData);
                this.courses.unshift(response.data.data);
                return { success: true, course: response.data.data };
            } catch (err) {
                if (err.response?.data?.error?.code === 'VALIDATION_ERROR') {
                    this.validationErrors = err.response.data.error.details;
                }
                this.error = err.response?.data?.error?.message || 'Failed to create course.';
                return { success: false, error: this.error, details: this.validationErrors };
            } finally {
                this.loading = false;
            }
        },

        async deleteCourse(courseId) {
            this.loading = true;
            this.error = null;
            try {
                await axios.delete(`/courses/${courseId}`);
                this.courses = this.courses.filter((c) => c.id !== courseId);
                return true;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete course.';
                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});
