<template>
  <div class="min-h-[85vh] flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-xl space-y-6">
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
        <h1 class="text-2xl font-bold text-white tracking-tight pt-2">Create your account</h1>
        <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed">
          Launch a new school tenant or join your existing school's community as a parent, teacher, or student.
        </p>
      </div>

      <!-- Main Registration Card -->
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

        <!-- Registration Mode Switcher Tabs -->
        <div class="grid grid-cols-2 p-1 bg-slate-950/80 border border-slate-800 rounded-xl mb-6">
          <button
            type="button"
            @click="registrationMode = 'new_school'"
            class="py-2 px-3 text-xs font-semibold rounded-lg transition-all duration-150 flex items-center justify-center gap-2"
            :class="registrationMode === 'new_school' 
              ? 'bg-indigo-600 text-white shadow-sm' 
              : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span>Register New School</span>
          </button>

          <button
            type="button"
            @click="registrationMode = 'join_school'"
            class="py-2 px-3 text-xs font-semibold rounded-lg transition-all duration-150 flex items-center justify-center gap-2"
            :class="registrationMode === 'join_school' 
              ? 'bg-indigo-600 text-white shadow-sm' 
              : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>Join Existing School</span>
          </button>
        </div>

        <form @submit.prevent="handleRegister" class="space-y-4">
          <!-- MODE 1: New School Tenant Fields -->
          <div v-if="registrationMode === 'new_school'" class="space-y-4 p-4 rounded-xl bg-indigo-950/20 border border-indigo-500/20">
            <div class="flex items-center gap-2 text-indigo-300 text-xs font-semibold">
              <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
              <span>School Institution Details</span>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                School / Institution Name <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.newSchoolName"
                type="text"
                required
                placeholder="e.g. Horizon International Academy"
                class="w-full bg-slate-950/90 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition"
              />
            </div>

            <!-- Subdomain Slug Preview -->
            <div v-if="subdomainPreview" class="text-[11px] text-slate-400 font-mono flex items-center gap-1.5">
              <span>Platform Subdomain:</span>
              <span class="text-indigo-400 font-semibold px-2 py-0.5 rounded bg-slate-900 border border-slate-800">
                {{ subdomainPreview }}.bina.edu
              </span>
            </div>
          </div>

          <!-- MODE 2: Join Existing School Fields -->
          <div v-else class="space-y-4 p-4 rounded-xl bg-slate-950/60 border border-slate-800">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                Select Your School <span class="text-rose-400">*</span>
              </label>
              <select
                v-model="form.schoolId"
                required
                class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition cursor-pointer"
              >
                <option value="" disabled>Choose a school tenant...</option>
                <option 
                  v-for="s in tenantStore.schools" 
                  :key="s.id" 
                  :value="s.id"
                  class="bg-slate-900 text-white"
                >
                  {{ s.name }} ({{ s.subdomain }})
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                Your Role <span class="text-rose-400">*</span>
              </label>
              <div class="grid grid-cols-3 gap-2">
                <button
                  type="button"
                  @click="form.role = 'parent'"
                  class="py-2 px-2.5 rounded-lg border text-xs font-medium transition text-center"
                  :class="form.role === 'parent' 
                    ? 'bg-amber-500/10 text-amber-300 border-amber-500/40' 
                    : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                >
                  👨‍👧‍👦 Parent
                </button>
                <button
                  type="button"
                  @click="form.role = 'teacher'"
                  class="py-2 px-2.5 rounded-lg border text-xs font-medium transition text-center"
                  :class="form.role === 'teacher' 
                    ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/40' 
                    : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                >
                  👨‍🏫 Teacher
                </button>
                <button
                  type="button"
                  @click="form.role = 'student'"
                  class="py-2 px-2.5 rounded-lg border text-xs font-medium transition text-center"
                  :class="form.role === 'student' 
                    ? 'bg-sky-500/10 text-sky-300 border-sky-500/40' 
                    : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                >
                  🎓 Student
                </button>
              </div>
            </div>
          </div>

          <!-- User Personal Credentials -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Full Name -->
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                Full Name <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                autocomplete="name"
                placeholder="Dr. Eleanor Vance"
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
              />
            </div>

            <!-- Phone Number -->
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                Phone Number <span class="text-slate-500 font-normal">(Optional)</span>
              </label>
              <input
                v-model="form.phone"
                type="tel"
                autocomplete="tel"
                placeholder="+1 (555) 019-2834"
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
              />
            </div>
          </div>

          <!-- Email Address -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">
              Work / Primary Email <span class="text-rose-400">*</span>
            </label>
            <input
              v-model="form.email"
              type="email"
              required
              autocomplete="email"
              placeholder="eleanor@horizon.edu"
              class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
            />
          </div>

          <!-- Password Fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                Password <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.password"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Min 8 characters"
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                Confirm Password <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.passwordConfirmation"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Repeat password"
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
              />
            </div>
          </div>

          <!-- Password Strength Indicator -->
          <div v-if="form.password" class="space-y-1">
            <div class="flex items-center justify-between text-[10px]">
              <span class="text-slate-500">Security Strength:</span>
              <span :class="passwordStrengthColor" class="font-bold font-mono">{{ passwordStrengthLabel }}</span>
            </div>
            <div class="h-1 bg-slate-800 rounded-full overflow-hidden flex">
              <div 
                class="h-full transition-all duration-300"
                :class="passwordStrengthBg"
                :style="{ width: `${passwordStrengthPercent}%` }"
              ></div>
            </div>
          </div>

          <!-- Terms Checkbox -->
          <div class="pt-1">
            <label class="flex items-start gap-2 cursor-pointer text-xs text-slate-400">
              <input
                v-model="form.agreeTerms"
                type="checkbox"
                required
                class="w-3.5 h-3.5 mt-0.5 rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:outline-none"
              />
              <span>
                I agree to the <span class="text-indigo-400 hover:underline">Terms of Service</span> and <span class="text-indigo-400 hover:underline">Privacy Policy</span>.
              </span>
            </label>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="authStore.loading || !form.agreeTerms"
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
            <span>{{ authStore.loading ? 'Creating account...' : (registrationMode === 'new_school' ? 'Create School & Admin Account' : 'Register Account') }}</span>
          </button>
        </form>

        <!-- Login CTA -->
        <div class="mt-6 pt-5 border-t border-slate-800/80 text-center">
          <p class="text-xs text-slate-400">
            Already registered?
            <router-link 
              to="/login" 
              class="font-semibold text-indigo-400 hover:text-indigo-300 hover:underline transition ml-1"
            >
              Sign in to your existing account &rarr;
            </router-link>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTenantStore } from '../stores/tenant';

const router = useRouter();
const authStore = useAuthStore();
const tenantStore = useTenantStore();

const registrationMode = ref('new_school');
const errorMessage = ref('');

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  passwordConfirmation: '',
  newSchoolName: '',
  schoolId: '',
  role: 'parent',
  agreeTerms: false,
});

const subdomainPreview = computed(() => {
  if (!form.newSchoolName) return '';
  return form.newSchoolName
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
});

const passwordStrengthPercent = computed(() => {
  const p = form.password;
  if (!p) return 0;
  let score = 0;
  if (p.length >= 8) score += 30;
  if (p.length >= 12) score += 20;
  if (/[A-Z]/.test(p)) score += 20;
  if (/[0-9]/.test(p)) score += 15;
  if (/[^A-Za-z0-9]/.test(p)) score += 15;
  return Math.min(score, 100);
});

const passwordStrengthLabel = computed(() => {
  const pct = passwordStrengthPercent.value;
  if (pct === 0) return '';
  if (pct < 40) return 'Weak';
  if (pct < 75) return 'Medium';
  return 'Strong';
});

const passwordStrengthColor = computed(() => {
  const pct = passwordStrengthPercent.value;
  if (pct < 40) return 'text-rose-400';
  if (pct < 75) return 'text-amber-400';
  return 'text-emerald-400';
});

const passwordStrengthBg = computed(() => {
  const pct = passwordStrengthPercent.value;
  if (pct < 40) return 'bg-rose-500';
  if (pct < 75) return 'bg-amber-500';
  return 'bg-emerald-500';
});

async function handleRegister() {
  errorMessage.value = '';

  if (form.password !== form.passwordConfirmation) {
    errorMessage.value = 'Password confirmation does not match.';
    return;
  }

  if (registrationMode.value === 'new_school' && !form.newSchoolName) {
    errorMessage.value = 'Please provide a school name.';
    return;
  }

  if (registrationMode.value === 'join_school' && !form.schoolId) {
    errorMessage.value = 'Please select a school to join.';
    return;
  }

  const payload = {
    name: form.name,
    email: form.email,
    phone: form.phone || null,
    password: form.password,
    password_confirmation: form.passwordConfirmation,
    role: registrationMode.value === 'new_school' ? 'school_admin' : form.role,
    school_id: registrationMode.value === 'join_school' ? form.schoolId : null,
    new_school_name: registrationMode.value === 'new_school' ? form.newSchoolName : null,
  };

  const result = await authStore.register(payload);
  if (result.success) {
    router.push('/');
  } else {
    errorMessage.value = result.error || 'Registration failed. Please check your inputs.';
  }
}

onMounted(() => {
  tenantStore.fetchSchools();
  if (authStore.isAuthenticated) {
    router.push('/');
  }
});
</script>
