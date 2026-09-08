import { defineStore } from 'pinia';
import axios from 'axios';

export const useGradingStore = defineStore('grading', {
    state: () => ({
        sections: [],
        subjects: [],
        exams: [],
        gradingScales: [],
        selectedSectionId: null,
        selectedSubjectId: null,
        selectedExamId: null,
        gradingRoster: null,
        sectionReportCards: null,
        activeReportCard: null,
        loading: false,
        actionLoading: false,
        error: null,
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
                if (this.subjects.length > 0 && !this.selectedSubjectId) {
                    this.selectedSubjectId = this.subjects[0].id;
                }
                return this.subjects;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch subjects.';
                return [];
            }
        },

        async fetchExams(termId = null, gradeLevelId = null) {
            try {
                const params = {};
                if (termId) params.term_id = termId;
                if (gradeLevelId) params.grade_level_id = gradeLevelId;
                const res = await axios.get('/exams', { params });
                this.exams = res.data.data || [];
                if (this.exams.length > 0 && !this.selectedExamId) {
                    this.selectedExamId = this.exams[0].id;
                }
                return this.exams;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch exams.';
                return [];
            }
        },

        async fetchGradingScales() {
            try {
                const res = await axios.get('/grading-scales');
                this.gradingScales = res.data.data || [];
                return this.gradingScales;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to fetch grading scales.';
                return [];
            }
        },

        async fetchSectionSubjectGrades(sectionId, subjectId, examId = null) {
            this.loading = true;
            this.error = null;
            try {
                const params = {};
                if (examId) params.exam_id = examId;
                const res = await axios.get(`/sections/${sectionId}/subjects/${subjectId}/grades`, { params });
                this.gradingRoster = res.data.data;
                this.selectedSectionId = sectionId;
                this.selectedSubjectId = subjectId;
                if (examId) this.selectedExamId = examId;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load grading roster.';
                this.gradingRoster = null;
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async recordGrades(payload) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                const res = await axios.post('/grades', payload);
                this.successMessage = res.data.message || 'Grades recorded and report cards updated successfully.';
                // Refresh grading roster if section & subject active
                if (payload.section_id && payload.subject_id) {
                    await this.fetchSectionSubjectGrades(payload.section_id, payload.subject_id, payload.exam_id);
                }
                return res.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to save grades.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async fetchSectionReportCards(sectionId, termId = null) {
            this.loading = true;
            this.error = null;
            try {
                const params = {};
                if (termId) params.term_id = termId;
                const res = await axios.get(`/sections/${sectionId}/report-cards`, { params });
                this.sectionReportCards = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load report cards.';
                this.sectionReportCards = null;
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async publishReportCard(reportCardId, remarks = null) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post(`/report-cards/${reportCardId}/publish`, {
                    principal_remarks: remarks,
                });
                this.successMessage = 'Report card published successfully!';
                return res.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to publish report card.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async bulkPublishSection(sectionId, termId = null) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post(`/sections/${sectionId}/report-cards/publish`, {
                    term_id: termId,
                });
                this.successMessage = res.data.message || 'Section report cards published successfully!';
                return res.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to bulk publish report cards.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async downloadReportCardPdf(reportCardId, filename = 'ReportCard.pdf') {
            try {
                const res = await axios.get(`/report-cards/${reportCardId}/pdf`, {
                    responseType: 'blob',
                });
                const blob = new Blob([res.data], { type: 'application/pdf' });
                const link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = filename;
                link.click();
                window.URL.revokeObjectURL(link.href);
            } catch (err) {
                this.error = 'Failed to download report card PDF.';
                throw err;
            }
        },

        async downloadParentReportCardPdf(studentId, reportCardId, filename = 'ReportCard.pdf') {
            try {
                const res = await axios.get(`/parent/children/${studentId}/report-cards/${reportCardId}/pdf`, {
                    responseType: 'blob',
                });
                const blob = new Blob([res.data], { type: 'application/pdf' });
                const link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = filename;
                link.click();
                window.URL.revokeObjectURL(link.href);
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Report card is not published or unavailable.';
                throw err;
            }
        },

        clearMessages() {
            this.error = null;
            this.successMessage = null;
        },
    },
});
