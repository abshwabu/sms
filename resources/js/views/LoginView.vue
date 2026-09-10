<template>
  <div class="min-h-[85vh] flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-lg space-y-6">
      <!-- Top Brand Header -->
      <div class="text-center space-y-2">
        <router-link to="/" class="inline-flex items-center gap-3 group">
          <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center font-black text-white text-xl shadow-lg shadow-indigo-600/30 group-hover:scale-105 transition-transform duration-200">
            B
          </div>
          <div class="text-left">
            <div class="flex items-center gap-1.5">
              <span class="font-black text-xl text-white tracking-tight">Bina Schools</span>
              <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                Multi-Tenant
              </span>
            </div>
            <span class="text-xs text-slate-400 block font-normal">Next-Gen School Management</span>
          </div>
        </router-link>
        <h1 class="text-2xl font-bold text-white tracking-tight pt-2">Sign in to your account</h1>
        <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
          Access your academic records, attendance, report cards, timetables, and communication hub.
        </p>
      </div>

      <!-- Main Login Card -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl relative overflow-hidden backdrop-blur">
        <!-- Error Banner -->
        <div 
          v-if="errorMessage" 
          class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between"
        >
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ errorMessage }}</span>
          </div>
          <button @click="errorMessage = ''" class="text-rose-400 hover:text-rose-200 text-sm">✕</button>
        </div>

        <!-- Success Toast for Registration Redirect -->
        <div 
          v-if="successMessage" 
          class="mb-5 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center gap-2"
        >
          <svg class="w-4 h-4 flex-shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ successMessage }}</span>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" class="space-y-4">
          <!-- School Tenant Selection (Optional Scoping) -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
              School Tenant <span class="text-slate-500 font-normal">(Optional context)</span>
            </label>
            <div class="relative">
              <select
                v-model="form.schoolId"
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition cursor-pointer appearance-none"
              >
                <option value="">Auto-resolve from Account (Default)</option>
                <option 
                  v-for="school in tenantStore.schools" 
                  :key="school.id" 
                  :value="school.id"
                  class="bg-slate-900 text-white"
                >
                  {{ school.name }} ({{ school.subdomain }})
                </option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Email Input -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
              Email Address <span class="text-rose-400">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <input
                v-model="form.email"
                type="email"
                required
                autocomplete="email"
                placeholder="name@school.edu"
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition"
              />
            </div>
          </div>

          <!-- Password Input -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-semibold text-slate-300">
                Password <span class="text-rose-400">*</span>
              </label>
              <button 
                type="button" 
                @click="showForgotPasswordModal = true"
                class="text-[11px] text-indigo-400 hover:text-indigo-300 hover:underline transition"
              >
                Forgot password?
              </button>
            </div>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7 11V7a5 5 0 0110 0v4" />
                </svg>
              </div>
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••••••"
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-10 pr-10 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition"
                aria-label="Toggle password visibility"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember Me Checkbox -->
          <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-400 hover:text-slate-300">
              <input
                v-model="form.remember"
                type="checkbox"
                class="w-3.5 h-3.5 rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:outline-none"
              />
              <span>Remember this session</span>
            </label>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="authStore.loading"
            class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white text-xs font-semibold shadow-sm transition-all duration-150 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed mt-2"
          >
            <svg 
              v-if="authStore.loading" 
              class="w-4 h-4 animate-spin text-white" 
              fill="none" 
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ authStore.loading ? 'Signing in...' : 'Sign In to Portal' }}</span>
          </button>
        </form>

        <!-- Divider -->
        <div class="relative my-6">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-slate-800"></div>
          </div>
          <div class="relative flex justify-center text-[10px] uppercase font-bold tracking-wider text-slate-500">
            <span class="bg-slate-900 px-2">Or test 1-Click Demo Persona</span>
          </div>
        </div>

        <!-- 1-Click Demo Persona Switcher -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <button
            v-for="p in demoPersonas"
            :key="p.email"
            @click="quickFillPersona(p)"
            type="button"
            class="p-2 rounded-xl border border-slate-800 bg-slate-950/60 hover:bg-slate-800 hover:border-slate-700 transition text-left group"
          >
            <div class="flex items-center justify-between mb-0.5">
              <span class="text-[11px] font-bold text-white group-hover:text-indigo-300 transition truncate">
                {{ p.label }}
              </span>
              <span class="text-[9px] font-mono px-1 rounded" :class="p.badgeClass">
                {{ p.role }}
              </span>
            </div>
            <div class="text-[10px] text-slate-500 truncate font-mono">{{ p.email }}</div>
          </button>
        </div>

        <!-- Registration CTA -->
        <div class="mt-6 pt-5 border-t border-slate-800/80 text-center">
          <p class="text-xs text-slate-400">
            Don't have an account yet?
            <router-link 
              to="/register" 
              class="font-semibold text-indigo-400 hover:text-indigo-300 hover:underline transition ml-1"
            >
              Register your school or join as a parent &rarr;
            </router-link>
          </p>
        </div>
      </div>

      <!-- Forgot Password Modal -->
      <div 
        v-if="showForgotPasswordModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
      >
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-sm w-full shadow-2xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Reset Password</h3>
            <button @click="showForgotPasswordModal = false" class="text-slate-400 hover:text-white">✕</button>
          </div>
          <p class="text-xs text-slate-400">
            Enter your registered email and we'll dispatch a secure password reset link.
          </p>
          <input
            v-model="forgotEmail"
            type="email"
            placeholder="name@school.edu"
            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
          />
          <div v-if="forgotSuccess" class="text-xs text-emerald-400">
            {{ forgotSuccess }}
          </div>
          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              @click="showForgotPasswordModal = false"
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs text-slate-400 hover:text-white"
            >
              Cancel
            </button>
            <button
              @click="handleForgotPassword"
              :disabled="forgotLoading || !forgotEmail"
              type="button"
              class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold disabled:opacity-50"
            >
              {{ forgotLoading ? 'Sending...' : 'Send Reset Link' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Set New Password Modal (from email reset link) -->
      <div 
        v-if="showResetPasswordModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
      >
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-sm w-full shadow-2xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Set New Password</h3>
            <button @click="showResetPasswordModal = false" class="text-slate-400 hover:text-white">✕</button>
          </div>
          <p class="text-xs text-slate-400">
            Enter your email and your new password to complete the password reset.
          </p>
          <div class="space-y-3">
            <div>
              <label class="block text-[11px] font-semibold text-slate-400 mb-1">Email Address</label>
              <input
                v-model="resetForm.email"
                type="email"
                placeholder="name@school.edu"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-slate-400 mb-1">New Password</label>
              <input
                v-model="resetForm.password"
                type="password"
                placeholder="Minimum 8 characters"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-slate-400 mb-1">Confirm New Password</label>
              <input
                v-model="resetForm.password_confirmation"
                type="password"
                placeholder="Repeat new password"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>
          <div v-if="resetError" class="text-xs text-rose-400">
            {{ resetError }}
          </div>
          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              @click="showResetPasswordModal = false"
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs text-slate-400 hover:text-white"
            >
              Cancel
            </button>
            <button
              @click="handleResetPassword"
              :disabled="resetLoading || !resetForm.password || resetForm.password !== resetForm.password_confirmation"
              type="button"
              class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold disabled:opacity-50"
            >
              {{ resetLoading ? 'Saving...' : 'Update Password' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTenantStore } from '../stores/tenant';
import axios from 'axios';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const tenantStore = useTenantStore();

const showPassword = ref(false);
const errorMessage = ref('');
const successMessage = ref(route.query.registered ? 'Registration completed successfully! Please sign in.' : '');
const showForgotPasswordModal = ref(false);
const forgotEmail = ref('');
const forgotLoading = ref(false);
const forgotSuccess = ref('');

const showResetPasswordModal = ref(false);
const resetLoading = ref(false);
const resetError = ref('');
const resetForm = reactive({
  token: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const form = reactive({
  email: '',
  password: '',
  schoolId: '',
  remember: false,
});

const demoPersonas = [
  {
    label: 'Super Admin',
    role: 'platform',
    email: 'superadmin@bina.test',
    password: 'password123',
    badgeClass: 'bg-purple-500/10 text-purple-400',
  },
  {
    label: 'School Admin',
    role: 'admin',
    email: 'admin@greenwood.edu',
    password: 'password123',
    badgeClass: 'bg-indigo-500/10 text-indigo-400',
  },
  {
    label: 'Teacher',
    role: 'faculty',
    email: 'teacher@greenwood.edu',
    password: 'password123',
    badgeClass: 'bg-emerald-500/10 text-emerald-400',
  },
  {
    label: 'Parent',
    role: 'family',
    email: 'parent@greenwood.edu',
    password: 'password123',
    badgeClass: 'bg-amber-500/10 text-amber-400',
  },
  {
    label: 'Student',
    role: 'student',
    email: 'bart.simpson@greenwood.edu',
    password: 'password123',
    badgeClass: 'bg-sky-500/10 text-sky-400',
  },
  {
    label: 'Oakridge Admin',
    role: 'admin',
    email: 'admin@oakridge.edu',
    password: 'password123',
    badgeClass: 'bg-indigo-500/10 text-indigo-400',
  },
];

function quickFillPersona(persona) {
  form.email = persona.email;
  form.password = persona.password;
  handleLogin();
}

async function handleLogin() {
  errorMessage.value = '';
  if (!form.email || !form.password) {
    errorMessage.value = 'Please enter both your email address and password.';
    return;
  }

  const result = await authStore.login(form.email, form.password, form.schoolId || null);
  if (result.success) {
    const redirectUrl = route.query.redirect || '/';
    router.push(redirectUrl);
  } else {
    errorMessage.value = result.error || 'Invalid email or password.';
  }
}

async function handleForgotPassword() {
  if (!forgotEmail.value) return;
  forgotLoading.value = true;
  forgotSuccess.value = '';
  try {
    const res = await axios.post('/auth/forgot-password', { email: forgotEmail.value });
    forgotSuccess.value = res.data?.message || 'Password reset link has been dispatched to your email.';
  } catch (err) {
    forgotSuccess.value = err.response?.data?.error?.message || 'If that email exists in our records, a reset link has been dispatched.';
  } finally {
    forgotLoading.value = false;
  }
}

async function handleResetPassword() {
  resetError.value = '';
  if (resetForm.password.length < 8) {
    resetError.value = 'Password must be at least 8 characters long.';
    return;
  }
  if (resetForm.password !== resetForm.password_confirmation) {
    resetError.value = 'Passwords do not match.';
    return;
  }

  resetLoading.value = true;
  try {
    const res = await axios.post('/auth/reset-password', resetForm);
    successMessage.value = res.data?.message || 'Password has been reset successfully! Please sign in with your new password.';
    form.email = resetForm.email;
    form.password = '';
    showResetPasswordModal.value = false;
    router.replace({ path: '/login' });
  } catch (err) {
    resetError.value = err.response?.data?.error?.message || err.response?.data?.message || 'Unable to reset password with provided link. It may have expired.';
  } finally {
    resetLoading.value = false;
  }
}

onMounted(() => {
  tenantStore.fetchSchools();
  if (authStore.isAuthenticated) {
    // If already authenticated, redirect to dashboard
    router.push('/');
    return;
  }

  const tokenParam = route.query.token || route.query.reset_token;
  if (tokenParam) {
    resetForm.token = String(tokenParam);
    resetForm.email = String(route.query.email || '');
    showResetPasswordModal.value = true;
  }
});
</script>
