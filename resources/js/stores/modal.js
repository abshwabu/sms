import { defineStore } from 'pinia';

export const useModalStore = defineStore('modal', {
    state: () => ({
        // Modal State
        isOpen: false,
        mode: 'alert', // 'alert' | 'confirm' | 'prompt'
        title: '',
        message: '',
        type: 'info', // 'info' | 'success' | 'warning' | 'error' | 'danger'
        confirmText: 'OK',
        cancelText: 'Cancel',
        destructive: false,
        inputValue: '',
        inputPlaceholder: '',
        inputRequired: false,
        _resolve: null,

        // Toasts State
        toasts: [],
    }),

    actions: {
        /**
         * Open a custom Alert modal.
         * Resolves Promise when user clicks OK or closes.
         */
        alert(messageOrOptions, options = {}) {
            return new Promise((resolve) => {
                let opts = {};
                if (typeof messageOrOptions === 'string') {
                    opts = { message: messageOrOptions, ...options };
                } else {
                    opts = { ...messageOrOptions };
                }

                this.mode = 'alert';
                this.title = opts.title || (opts.type === 'error' || opts.type === 'danger' ? 'Notice' : opts.type === 'success' ? 'Success' : 'Information');
                this.message = opts.message || '';
                this.type = opts.type || 'info';
                this.confirmText = opts.confirmText || 'OK';
                this.destructive = Boolean(opts.destructive);
                this.inputValue = '';
                this.inputPlaceholder = '';
                this._resolve = resolve;
                this.isOpen = true;
            });
        },

        /**
         * Open a custom Confirm modal.
         * Resolves Promise<boolean> (true if confirmed, false if cancelled).
         */
        confirm(messageOrOptions, options = {}) {
            return new Promise((resolve) => {
                let opts = {};
                if (typeof messageOrOptions === 'string') {
                    opts = { message: messageOrOptions, ...options };
                } else {
                    opts = { ...messageOrOptions };
                }

                this.mode = 'confirm';
                this.title = opts.title || 'Please Confirm';
                this.message = opts.message || '';
                this.type = opts.type || (opts.destructive ? 'danger' : 'warning');
                this.confirmText = opts.confirmText || (opts.destructive ? 'Delete' : 'Confirm');
                this.cancelText = opts.cancelText || 'Cancel';
                this.destructive = Boolean(opts.destructive);
                this.inputValue = '';
                this.inputPlaceholder = '';
                this._resolve = resolve;
                this.isOpen = true;
            });
        },

        /**
         * Open a custom Prompt modal.
         * Resolves Promise<string|null> (entered value if confirmed, null if cancelled).
         */
        prompt(messageOrOptions, options = {}) {
            return new Promise((resolve) => {
                let opts = {};
                if (typeof messageOrOptions === 'string') {
                    opts = { message: messageOrOptions, ...options };
                } else {
                    opts = { ...messageOrOptions };
                }

                this.mode = 'prompt';
                this.title = opts.title || 'Input Required';
                this.message = opts.message || '';
                this.type = opts.type || 'info';
                this.confirmText = opts.confirmText || 'Submit';
                this.cancelText = opts.cancelText || 'Cancel';
                this.destructive = false;
                this.inputValue = opts.defaultValue || '';
                this.inputPlaceholder = opts.placeholder || 'Type here...';
                this.inputRequired = opts.required !== false;
                this._resolve = resolve;
                this.isOpen = true;
            });
        },

        /**
         * Handle user confirmation.
         */
        handleConfirm() {
            const resolve = this._resolve;
            const mode = this.mode;
            const value = this.inputValue;

            this.isOpen = false;
            this._resolve = null;

            if (resolve) {
                if (mode === 'confirm') {
                    resolve(true);
                } else if (mode === 'prompt') {
                    resolve(value);
                } else {
                    resolve(true);
                }
            }
        },

        /**
         * Handle user cancellation or close.
         */
        handleCancel() {
            const resolve = this._resolve;
            const mode = this.mode;

            this.isOpen = false;
            this._resolve = null;

            if (resolve) {
                if (mode === 'confirm') {
                    resolve(false);
                } else if (mode === 'prompt') {
                    resolve(null);
                } else {
                    resolve(false);
                }
            }
        },

        /**
         * Display a non-blocking toast notification.
         */
        toast(message, type = 'success', duration = 4000) {
            const id = Date.now() + Math.random().toString(36).substring(2, 6);
            const toastObj = {
                id,
                message,
                type,
            };

            this.toasts.push(toastObj);

            if (duration > 0) {
                setTimeout(() => {
                    this.removeToast(id);
                }, duration);
            }

            return id;
        },

        /**
         * Remove a specific toast.
         */
        removeToast(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    },
});
