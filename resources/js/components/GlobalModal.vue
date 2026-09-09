<template>
  <div>
    <!-- ================================================================= -->
    <!-- 1. CUSTOM MODAL DIALOG (Alert / Confirm / Prompt)                 -->
    <!-- ================================================================= -->
    <teleport to="body">
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="modalStore.isOpen"
          class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
          @click.self="modalStore.handleCancel"
          @keydown.esc="modalStore.handleCancel"
        >
          <!-- Modal Card Panel -->
          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-2"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2"
          >
            <div
              v-if="modalStore.isOpen"
              class="relative w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-5 select-none"
              role="dialog"
              aria-modal="true"
            >
              <!-- Modal Header with Icon -->
              <div class="flex items-start gap-3.5">
                <!-- Status Icon Badge -->
                <div
                  class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                  :class="iconBadgeClasses"
                >
                  <!-- Danger / Error Icon -->
                  <svg
                    v-if="modalStore.type === 'danger' || modalStore.type === 'error'"
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                  >
                    <circle cx="12" cy="12" r="9" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                  </svg>

                  <!-- Warning Icon -->
                  <svg
                    v-else-if="modalStore.type === 'warning'"
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>

                  <!-- Success Icon -->
                  <svg
                    v-else-if="modalStore.type === 'success'"
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>

                  <!-- Info / Default Icon -->
                  <svg
                    v-else
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                  >
                    <circle cx="12" cy="12" r="9" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                  </svg>
                </div>

                <!-- Text Header -->
                <div class="flex-1 min-w-0 pt-0.5">
                  <h3 class="text-sm sm:text-base font-bold text-white tracking-tight leading-snug">
                    {{ modalStore.title }}
                  </h3>
                  <p class="text-xs text-slate-300 mt-1 leading-relaxed whitespace-pre-line">
                    {{ modalStore.message }}
                  </p>
                </div>
              </div>

              <!-- Prompt Input Field (If in prompt mode) -->
              <div v-if="modalStore.mode === 'prompt'" class="pt-1">
                <input
                  ref="promptInputRef"
                  v-model="modalStore.inputValue"
                  type="text"
                  :placeholder="modalStore.inputPlaceholder"
                  class="w-full bg-slate-950/90 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition"
                  @keydown.enter.prevent="submitPrompt"
                />
              </div>

              <!-- Modal Action Buttons -->
              <div class="flex items-center justify-end gap-2.5 pt-2">
                <!-- Cancel Button (Only for confirm & prompt) -->
                <button
                  v-if="modalStore.mode === 'confirm' || modalStore.mode === 'prompt'"
                  type="button"
                  @click="modalStore.handleCancel"
                  class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 border border-slate-700/70 transition"
                >
                  {{ modalStore.cancelText }}
                </button>

                <!-- Confirm / OK Button -->
                <button
                  ref="confirmButtonRef"
                  type="button"
                  @click="submitConfirm"
                  class="px-4 py-2 rounded-xl text-xs font-semibold shadow-sm transition active:scale-[0.98]"
                  :class="confirmButtonClasses"
                >
                  {{ modalStore.confirmText }}
                </button>
              </div>
            </div>
          </transition>
        </div>
      </transition>
    </teleport>

    <!-- ================================================================= -->
    <!-- 2. FLOATING TOAST NOTIFICATIONS CONTAINER                         -->
    <!-- ================================================================= -->
    <teleport to="body">
      <div class="fixed bottom-5 right-5 z-[110] space-y-2.5 pointer-events-none max-w-sm w-full px-4 sm:px-0">
        <transition-group
          enter-active-class="transition duration-200 ease-out transform"
          enter-from-class="opacity-0 translate-y-3 scale-95"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          leave-active-class="transition duration-150 ease-in transform"
          leave-from-class="opacity-100 translate-y-0 scale-100"
          leave-to-class="opacity-0 translate-x-4 scale-95"
        >
          <div
            v-for="toast in modalStore.toasts"
            :key="toast.id"
            class="pointer-events-auto p-3.5 rounded-2xl bg-slate-900/95 border shadow-xl flex items-start gap-3 backdrop-blur"
            :class="toastBorderClasses(toast.type)"
          >
            <!-- Toast Icon -->
            <div
              class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0 text-xs font-bold"
              :class="toastIconClasses(toast.type)"
            >
              <span v-if="toast.type === 'success'">✓</span>
              <span v-else-if="toast.type === 'error' || toast.type === 'danger'">✕</span>
              <span v-else-if="toast.type === 'warning'">!</span>
              <span v-else>ℹ</span>
            </div>

            <!-- Toast Content -->
            <div class="flex-1 min-w-0 pt-0.5">
              <p class="text-xs text-slate-200 leading-relaxed font-medium">
                {{ toast.message }}
              </p>
            </div>

            <!-- Toast Dismiss Button -->
            <button
              @click="modalStore.removeToast(toast.id)"
              type="button"
              class="text-slate-500 hover:text-slate-300 text-xs p-1 transition"
              aria-label="Dismiss toast"
            >
              ✕
            </button>
          </div>
        </transition-group>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue';
import { useModalStore } from '../stores/modal';

const modalStore = useModalStore();
const promptInputRef = ref(null);
const confirmButtonRef = ref(null);

watch(
  () => modalStore.isOpen,
  async (isOpen) => {
    if (isOpen) {
      await nextTick();
      if (modalStore.mode === 'prompt' && promptInputRef.value) {
        promptInputRef.value.focus();
      } else if (confirmButtonRef.value) {
        confirmButtonRef.value.focus();
      }
    }
  }
);

function submitPrompt() {
  if (modalStore.inputRequired && !modalStore.inputValue.trim()) {
    return;
  }
  modalStore.handleConfirm();
}

function submitConfirm() {
  if (modalStore.mode === 'prompt') {
    submitPrompt();
  } else {
    modalStore.handleConfirm();
  }
}

const iconBadgeClasses = computed(() => {
  switch (modalStore.type) {
    case 'danger':
    case 'error':
      return 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
    case 'warning':
      return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
    case 'success':
      return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
    default:
      return 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20';
  }
});

const confirmButtonClasses = computed(() => {
  if (modalStore.destructive || modalStore.type === 'danger' || modalStore.type === 'error') {
    return 'bg-rose-600 hover:bg-rose-500 text-white';
  }
  if (modalStore.type === 'success') {
    return 'bg-emerald-600 hover:bg-emerald-500 text-white';
  }
  return 'bg-indigo-600 hover:bg-indigo-500 text-white';
});

function toastBorderClasses(type) {
  switch (type) {
    case 'success':
      return 'border-emerald-500/30';
    case 'error':
    case 'danger':
      return 'border-rose-500/30';
    case 'warning':
      return 'border-amber-500/30';
    default:
      return 'border-slate-700/80';
  }
}

function toastIconClasses(type) {
  switch (type) {
    case 'success':
      return 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30';
    case 'error':
    case 'danger':
      return 'bg-rose-500/15 text-rose-400 border border-rose-500/30';
    case 'warning':
      return 'bg-amber-500/15 text-amber-400 border border-amber-500/30';
    default:
      return 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/30';
  }
}
</script>
