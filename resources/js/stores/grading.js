import { defineStore } from 'pinia';
import axios from 'axios';

export const useGradingStore = defineStore('grading', {
    state: () => ({
        sections: [],
        subjects: [],
        exams: [],
        terms: [],
        academicYears: [],
        gradeLevels: [],
        gradingScales: [],
        selectedSectionId: null,
        selectedSubjectId: null,
        selectedExamId: null,
        selectedTermId: null,
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
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to fetch sections.';
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
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to fetch subjects.';
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
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to fetch exams.';
                return [];
            }
        },

        async fetchTerms() {
            try {
                const res = await axios.get('/terms');
                this.terms = res.data.data || [];
                if (this.terms.length > 0 && !this.selectedTermId) {
                    const activeTerm = this.terms.find(t => t.is_active);
                    this.selectedTermId = activeTerm ? activeTerm.id : this.terms[0].id;
                }
                return this.terms;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to fetch terms.';
                return [];
            }
        },

        async fetchAcademicYears() {
            try {
                const res = await axios.get('/academic-years');
                this.academicYears = res.data.data || [];
                return this.academicYears;
            } catch (err) {
                return [];
            }
        },

        async fetchGradeLevels() {
            try {
                const res = await axios.get('/grade-levels');
                this.gradeLevels = res.data.data || [];
                return this.gradeLevels;
            } catch (err) {
                return [];
            }
        },

        async fetchGradingScales() {
            try {
                const res = await axios.get('/grading-scales');
                this.gradingScales = res.data.data || [];
                return this.gradingScales;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to fetch grading scales.';
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
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to load grading roster.';
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
                if (payload.section_id && payload.subject_id) {
                    await this.fetchSectionSubjectGrades(payload.section_id, payload.subject_id, payload.exam_id);
                }
                return { success: true, data: res.data };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to save grades.';
                this.error = msg;
                return { success: false, error: msg };
            } finally {
                this.actionLoading = false;
            }
        },

        async createExam(payload) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post('/exams', payload);
                await this.fetchExams();
                return { success: true, data: res.data.data };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to create assessment.';
                this.error = msg;
                return { success: false, error: msg };
            } finally {
                this.actionLoading = false;
            }
        },

        async deleteExam(examId) {
            this.actionLoading = true;
            this.error = null;
            try {
                await axios.delete(`/exams/${examId}`);
                await this.fetchExams();
                return { success: true };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to delete assessment.';
                this.error = msg;
                return { success: false, error: msg };
            } finally {
                this.actionLoading = false;
            }
        },

        async createGradingScale(payload) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post('/grading-scales', payload);
                await this.fetchGradingScales();
                return { success: true, data: res.data.data };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to create grading scale.';
                this.error = msg;
                return { success: false, error: msg };
            } finally {
                this.actionLoading = false;
            }
        },

        async deleteGradingScale(scaleId) {
            this.actionLoading = true;
            this.error = null;
            try {
                await axios.delete(`/grading-scales/${scaleId}`);
                await this.fetchGradingScales();
                return { success: true };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to delete grading scale.';
                this.error = msg;
                return { success: false, error: msg };
            } finally {
                this.actionLoading = false;
            }
        },

        async createSubject(payload) {
            this.actionLoading = true;
            this.error = null;
            try {
                const res = await axios.post('/subjects', payload);
                await this.fetchSubjects();
                return { success: true, data: res.data.data };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to create subject.';
                this.error = msg;
                return { success: false, error: msg };
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
                return { success: true, data: res.data.data };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to load report cards.';
                this.error = msg;
                this.sectionReportCards = null;
                return { success: false, error: msg };
            } finally {
                this.loading = false;
            }
        },

        async fetchReportCard(reportCardId) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/report-cards/${reportCardId}`);
                this.activeReportCard = res.data.data;
                return { success: true, data: res.data.data };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to load report card.';
                this.error = msg;
                return { success: false, error: msg };
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
                return { success: true, data: res.data.data };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to publish report card.';
                this.error = msg;
                return { success: false, error: msg };
            } finally {
                this.actionLoading = false;
            }
        },

        async bulkPublishSection(sectionId, termId = null) {
            this.actionLoading = true;
            this.error = null;
            try {
                const payload = {};
                if (termId) payload.term_id = termId;
                const res = await axios.post(`/sections/${sectionId}/report-cards/publish`, payload);
                return { success: true, data: res.data.data, message: res.data.message };
            } catch (err) {
                const msg = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to bulk publish report cards.';
                this.error = msg;
                return { success: false, error: msg };
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
                return { success: true };
            } catch (err) {
                const msg = 'Failed to download report card PDF.';
                this.error = msg;
                return { success: false, error: msg };
            }
        },

        clearMessages() {
            this.error = null;
            this.successMessage = null;
        },
    },
});
