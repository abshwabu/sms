import { defineStore } from 'pinia';
import axios from 'axios';

export const useLibraryStore = defineStore('library', {
    state: () => ({
        books: [],
        booksPagination: null,
        loans: [],
        loansPagination: null,
        fines: [],
        finesPagination: null,
        summary: null,
        myBorrowedBooks: null,
        childBorrowedBooks: {},
        loading: false,
        actionLoading: false,
        error: null,
        successMessage: null,
    }),

    actions: {
        async fetchSummary() {
            try {
                const res = await axios.get('/library/summary');
                this.summary = res.data.data;
                return res.data.data;
            } catch (err) {
                // Silently handle if user lacks permissions
                return null;
            }
        },

        async fetchBooks(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/library/books', { params });
                if (res.data.meta && res.data.meta.pagination) {
                    this.books = res.data.data || [];
                    this.booksPagination = res.data.meta.pagination;
                } else {
                    this.books = res.data.data || [];
                }
                return this.books;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load library catalog.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async createBook(bookData) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                const res = await axios.post('/library/books', bookData);
                this.successMessage = 'Book added to catalog successfully!';
                await this.fetchBooks();
                await this.fetchSummary();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to add book.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async updateBook(bookId, bookData) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                const res = await axios.put(`/library/books/${bookId}`, bookData);
                this.successMessage = 'Book updated successfully!';
                await this.fetchBooks();
                await this.fetchSummary();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to update book.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async deleteBook(bookId) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                await axios.delete(`/library/books/${bookId}`);
                this.successMessage = 'Book removed from catalog.';
                await this.fetchBooks();
                await this.fetchSummary();
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete book.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async fetchLoans(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/library/loans', { params });
                this.loans = res.data.data || [];
                this.loansPagination = res.data.meta?.pagination || null;
                return this.loans;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load loans.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async checkoutBook(checkoutData) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                const res = await axios.post('/library/loans/checkout', checkoutData);
                this.successMessage = 'Book checked out successfully!';
                await this.fetchBooks();
                await this.fetchSummary();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to checkout book.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async checkinBook(loanId, returnData = {}) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                const res = await axios.post(`/library/loans/${loanId}/checkin`, returnData);
                const fine = res.data.data?.fine_amount;
                if (fine && parseFloat(fine) > 0) {
                    this.successMessage = `Book returned. Overdue fine of $${parseFloat(fine).toFixed(2)} recorded in ledger.`;
                } else {
                    this.successMessage = 'Book returned successfully with zero fines.';
                }
                await this.fetchBooks();
                await this.fetchSummary();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to check in book.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async fetchFines(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/library/fines', { params });
                this.fines = res.data.data || [];
                this.finesPagination = res.data.meta?.pagination || null;
                return this.fines;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load fines.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async payFine(fineId, paymentData = {}) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                const res = await axios.post(`/library/fines/${fineId}/pay`, paymentData);
                this.successMessage = 'Fine payment recorded successfully!';
                await this.fetchFines();
                await this.fetchSummary();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to record fine payment.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async waiveFine(fineId, reason) {
            this.actionLoading = true;
            this.error = null;
            this.successMessage = null;
            try {
                const res = await axios.post(`/library/fines/${fineId}/waive`, { reason });
                this.successMessage = 'Library fine has been waived.';
                await this.fetchFines();
                await this.fetchSummary();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to waive fine.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async fetchMyBorrowedBooks() {
            this.loading = true;
            try {
                const res = await axios.get('/student/borrowed-books');
                this.myBorrowedBooks = res.data.data;
                return res.data.data;
            } catch (err) {
                this.myBorrowedBooks = null;
                return null;
            } finally {
                this.loading = false;
            }
        },

        async fetchChildBorrowedBooks(studentId) {
            try {
                const res = await axios.get(`/parent/children/${studentId}/borrowed-books`);
                this.childBorrowedBooks[studentId] = res.data.data;
                return res.data.data;
            } catch (err) {
                this.childBorrowedBooks[studentId] = null;
                return null;
            }
        },

        clearMessages() {
            this.error = null;
            this.successMessage = null;
        },
    },
});
