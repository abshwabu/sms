import { defineStore } from 'pinia';
import axios from 'axios';

export const useCommunicationsStore = defineStore('communications', {
    state: () => ({
        announcements: [],
        currentAnnouncement: null,
        threads: [],
        currentThread: null,
        notifications: [],
        unreadCount: 0,
        telegramStatus: null,
        telegramLinkData: null,
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

        // --- Announcements ---
        async fetchAnnouncements(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/announcements', { params });
                this.announcements = res.data.data || [];
                return this.announcements;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load announcements.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async createAnnouncement(data) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post('/announcements', data);
                this.successMessage = 'Announcement created successfully!';
                await this.fetchAnnouncements();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to create announcement.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async publishAnnouncement(id) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post(`/announcements/${id}/publish`);
                this.successMessage = 'Announcement published and notifications sent!';
                await this.fetchAnnouncements();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to publish announcement.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async deleteAnnouncement(id) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                await axios.delete(`/announcements/${id}`);
                this.successMessage = 'Announcement deleted.';
                this.announcements = this.announcements.filter(a => a.id !== id);
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to delete announcement.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        // --- Direct Teacher-Parent Messaging per Student ---
        async fetchThreads() {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get('/communications/threads');
                this.threads = res.data.data || [];
                return this.threads;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load message threads.';
                return [];
            } finally {
                this.loading = false;
            }
        },

        async fetchThread(id) {
            this.loading = true;
            this.error = null;
            try {
                const res = await axios.get(`/communications/threads/${id}`);
                this.currentThread = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to load thread details.';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async createThread(payload) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post('/communications/threads', payload);
                this.successMessage = 'Direct messaging thread created!';
                await this.fetchThreads();
                this.currentThread = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || err.response?.data?.message || 'Failed to start thread.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async replyThread(threadId, body) {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post(`/communications/threads/${threadId}/messages`, { body });
                if (this.currentThread && this.currentThread.id === threadId) {
                    if (!this.currentThread.messages) this.currentThread.messages = [];
                    this.currentThread.messages.push(res.data.data);
                }
                await this.fetchThreads();
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to send message.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        // --- In-App Notification Center ---
        async fetchNotifications() {
            try {
                const res = await axios.get('/notifications');
                this.notifications = res.data.data?.notifications || [];
                this.unreadCount = res.data.data?.unread_count || 0;
            } catch (err) {
                // Non-intrusive error
            }
        },

        async markNotificationRead(id) {
            try {
                await axios.post(`/notifications/${id}/read`);
                const item = this.notifications.find(n => n.id === id);
                if (item && !item.read_at) {
                    item.read_at = new Date().toISOString();
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                }
            } catch (err) {
                // Non-intrusive error
            }
        },

        async markAllNotificationsRead() {
            try {
                await axios.post('/notifications/read-all');
                this.notifications.forEach(n => { n.read_at = new Date().toISOString(); });
                this.unreadCount = 0;
            } catch (err) {
                // Non-intrusive error
            }
        },

        // --- Telegram Integration ---
        async fetchTelegramStatus() {
            try {
                const res = await axios.get('/telegram/status');
                this.telegramStatus = res.data.data;
                return this.telegramStatus;
            } catch (err) {
                return null;
            }
        },

        async generateTelegramLink() {
            this.actionLoading = true;
            this.clearMessages();
            try {
                const res = await axios.post('/telegram/link-code');
                this.telegramLinkData = res.data.data;
                return res.data.data;
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to generate Telegram link code.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },

        async unlinkTelegram() {
            this.actionLoading = true;
            this.clearMessages();
            try {
                await axios.post('/telegram/unlink');
                this.successMessage = 'Telegram disconnected.';
                await this.fetchTelegramStatus();
            } catch (err) {
                this.error = err.response?.data?.error?.message || 'Failed to unlink Telegram.';
                throw err;
            } finally {
                this.actionLoading = false;
            }
        },
    },
});
