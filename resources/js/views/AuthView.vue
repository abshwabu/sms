<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>{{ authStore.isSuperAdmin ? 'Platform Security & RBAC Inspector' : 'My Profile & Account' }}</span>
          <span 
            class="text-xs px-2.5 py-0.5 rounded-full font-mono uppercase font-semibold"
            :class="getRoleBadgeClass(authStore.role)"
          >
            {{ formatRole(authStore.role) }}
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          {{ authStore.isSuperAdmin 
              ? 'Multi-tenant Sanctum session inspector, cross-tenant barrier enforcement, and platform permissions.' 
              : 'Manage your institutional credentials, contact details, and account security.' }}
        </p>
      </div>

      <div v-if="authStore.isAuthenticated" class="flex items-center gap-3">
        <div class="text-right hidden sm:block">
          <div class="text-xs text-slate-400 font-mono">Logged in as</div>
          <div class="text-sm font-bold text-indigo-300">{{ authStore.user?.name }}</div>
        </div>
        <button 
          @click="handleSignOut"
          class="px-3.5 py-1.5 bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800 text-rose-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span>Sign Out</span>
        </button>
      </div>
    </div>

    <!-- Main Profile & Account Information Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Column 1: Identity & School Context -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 space-y-5 shadow-sm">
        <div class="flex items-center gap-4 border-b border-slate-800/80 pb-5">
          <div class="w-14 h-14 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center font-bold text-indigo-300 text-xl shadow-inner flex-shrink-0">
            {{ userInitial }}
          </div>
          <div class="min-w-0">
            <h2 class="text-base font-bold text-white truncate">{{ authStore.user?.name || 'User' }}</h2>
            <p class="text-xs text-slate-400 font-mono truncate">{{ authStore.user?.email }}</p>
            <div class="mt-1">
              <span 
                class="inline-block text-[10px] font-mono uppercase px-2 py-0.5 rounded font-semibold"
                :class="getRoleBadgeClass(authStore.role)"
              >
                {{ formatRole(authStore.role) }}
              </span>
            </div>
          </div>
        </div>

        <div class="space-y-3.5 text-xs">
          <div>
            <span class="text-slate-400 block mb-0.5 text-[11px] font-semibold uppercase tracking-wider">Institution / School</span>
            <div class="font-medium text-white flex items-center gap-1.5">
              <span>🏛️ {{ authStore.schoolContext?.name || (authStore.isSuperAdmin ? 'Global Platform' : 'Assigned School') }}</span>
            </div>
            <div v-if="authStore.schoolContext?.subdomain" class="text-[11px] text-slate-400 font-mono mt-0.5">
              {{ authStore.schoolContext.subdomain }}.bina.edu
            </div>
          </div>

          <div>
            <span class="text-slate-400 block mb-0.5 text-[11px] font-semibold uppercase tracking-wider">Account Status</span>
            <div class="flex items-center gap-1.5 text-emerald-400 font-medium">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="capitalize">{{ authStore.user?.status || 'Active' }}</span>
              <span class="text-slate-500">&bull; Verified</span>
            </div>
          </div>

          <div v-if="authStore.user?.phone">
            <span class="text-slate-400 block mb-0.5 text-[11px] font-semibold uppercase tracking-wider">Phone Number</span>
            <div class="font-medium text-slate-200">{{ authStore.user.phone }}</div>
          </div>

          <div>
            <span class="text-slate-400 block mb-0.5 text-[11px] font-semibold uppercase tracking-wider">Last Login Session</span>
            <div class="text-slate-300 font-mono text-[11px]">
              {{ authStore.user?.last_login_at ? formatDate(authStore.user.last_login_at) : 'Current Session' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Column 2: Role Responsibilities & Permissions -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-sm flex flex-col justify-between">
        <div>
          <h3 class="text-sm font-bold text-white flex items-center gap-2 mb-2">
            <span>🛡️ Institutional Scope &amp; Access</span>
          </h3>
          <p class="text-xs text-slate-300 leading-relaxed bg-slate-950/60 p-3.5 rounded-xl border border-slate-800/80">
            {{ roleDescription }}
          </p>

          <div class="mt-4">
            <span class="text-slate-400 block mb-2 text-[11px] font-semibold uppercase tracking-wider">Granted Role Capabilities</span>
            <div v-if="authStore.user?.permissions?.length" class="flex flex-wrap gap-1.5 max-h-48 overflow-y-auto">
              <span 
                v-for="perm in authStore.user.permissions" 
                :key="perm"
                class="px-2 py-0.5 bg-slate-800/80 border border-slate-700/80 rounded text-[11px] font-mono text-slate-300"
              >
                {{ perm }}
              </span>
            </div>
            <div v-else class="text-slate-500 text-xs italic bg-slate-950/40 p-3 rounded-lg border border-slate-800/60">
              Standard role policies active. Permissions are automatically scoped to your institutional role and tenant boundary.
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-800/80 text-[11px] text-slate-400 flex items-center justify-between font-mono">
          <span>Tenant Isolation:</span>
          <span class="text-emerald-400 font-semibold">Strict Single-Database</span>
        </div>
      </div>

      <!-- Column 3: Change Password & Security Form -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-sm">
        <div>
          <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>🔐 Change Password</span>
          </h3>
          <p class="text-xs text-slate-400 mt-0.5">
            Keep your account secure with a strong password.
          </p>
        </div>

        <form @submit.prevent="handleChangePassword" class="space-y-3.5 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Current Password *</label>
            <input 
              v-model="passwordForm.current_password"
              type="password"
              required
              placeholder="••••••••"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg p-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">New Password *</label>
            <input 
              v-model="passwordForm.password"
              type="password"
              required
              minlength="6"
              placeholder="At least 6 characters"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg p-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Confirm New Password *</label>
            <input 
              v-model="passwordForm.password_confirmation"
              type="password"
              required
              minlength="6"
              placeholder="Confirm new password"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg p-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="pt-2">
            <button 
              type="submit"
              :disabled="savingPassword"
              class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold rounded-lg shadow-sm transition flex items-center justify-center gap-2"
            >
              <span>{{ savingPassword ? 'Updating Password...' : 'Update Password' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- Super Admin ONLY Diagnostic Test Bench (Hidden from regular users) -->
    <!-- ================================================================= -->
    <div v-if="authStore.isSuperAdmin" class="space-y-6 pt-6 border-t border-slate-800">
      <div class="flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-base font-bold text-white">🛠️ Platform RBAC Test Bench</h2>
            <span class="text-[10px] px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30 font-mono">
              System Admin Only
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-0.5">
            Diagnostic verification tools for cross-tenant barrier enforcement and token authentication.
          </p>
        </div>
        <button 
          @click="showDiagnostics = !showDiagnostics"
          class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-semibold border border-slate-700 transition"
        >
          {{ showDiagnostics ? 'Hide Diagnostics ▲' : 'Show Diagnostics ▼' }}
        </button>
      </div>

      <div v-if="showDiagnostics" class="space-y-6">
        <!-- 1-Click Quick Persona Logins (Admin Dev Testing Only) -->
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-5">
          <div class="flex items-center justify-between mb-3">
            <div>
              <h3 class="text-xs font-bold text-white uppercase tracking-wider">⚡ 1-Click Persona Switcher</h3>
              <p class="text-[11px] text-slate-400">Instantly switch between roles to test tenant boundaries.</p>
            </div>
            <span class="text-[10px] font-mono text-slate-500">Default Password: password123</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
            <button 
              v-for="persona in personas" 
              :key="persona.email"
              @click="quickLogin(persona.email)"
              :disabled="authStore.loading"
              class="p-3 rounded-lg border text-left transition flex flex-col justify-between group"
              :class="authStore.user?.email === persona.email 
                ? 'bg-indigo-950/50 border-indigo-500 ring-2 ring-indigo-500/20' 
                : 'bg-slate-800/60 border-slate-700/70 hover:border-slate-600 hover:bg-slate-800'"
            >
              <div>
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-white">{{ persona.name }}</span>
                  <span 
                    class="text-[9px] font-mono uppercase px-1 py-0.2 rounded font-semibold"
                    :class="getRoleBadgeClass(persona.role)"
                  >
                    {{ persona.role }}
                  </span>
                </div>
                <div class="text-[10px] text-slate-400 font-mono mt-1 truncate">{{ persona.email }}</div>
                <div class="text-[10px] text-indigo-400/90 mt-0.5">{{ persona.school }}</div>
              </div>
              <div class="mt-2 text-[10px] font-medium text-slate-400 group-hover:text-indigo-300">
                {{ authStore.user?.email === persona.email ? '✓ Active Session' : 'Switch Persona →' }}
              </div>
            </button>
          </div>
        </div>

        <!-- RBAC Acceptance Criteria Tester -->
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-5 space-y-4">
          <div>
            <h3 class="text-xs font-bold text-white uppercase tracking-wider">
              🛡️ Live Endpoint Boundary Tests
            </h3>
            <p class="text-[11px] text-slate-400 mt-0.5">
              Execute live requests to verify boundary enforcement directly against the API:
            </p>
          </div>

          <div class="space-y-2.5">
            <!-- Test 1: Hit School Admin Endpoint -->
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg flex items-center justify-between">
              <div>
                <div class="text-xs font-mono font-bold text-indigo-300">GET /api/admin/users</div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  School Admin Only: Should succeed (200) for school_admin, and return 403 Forbidden for teacher/student.
                </div>
              </div>
              <button 
                @click="testAdminEndpoint"
                :disabled="testLoading || !authStore.isAuthenticated"
                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-xs font-semibold rounded-lg transition"
              >
                Test Route
              </button>
            </div>

            <!-- Test 2: Cross Tenant Attempt -->
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg flex items-center justify-between">
              <div>
                <div class="text-xs font-mono font-bold text-purple-300">Cross-Tenant Probe (School B)</div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  Sends current token with foreign X-School-Id header. Should return 403 Forbidden.
                </div>
              </div>
              <button 
                @click="testCrossTenant"
                :disabled="testLoading || !authStore.isAuthenticated"
                class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-40 text-white text-xs font-semibold rounded-lg transition"
              >
                Test Probe
              </button>
            </div>
          </div>

          <!-- Test Output Area -->
          <div v-if="testResult" class="mt-3 p-3 rounded-lg text-xs font-mono border" :class="testResult.status === 200 ? 'bg-emerald-950/40 border-emerald-700 text-emerald-300' : 'bg-rose-950/40 border-rose-700 text-rose-300'">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold uppercase tracking-wider">Result: HTTP {{ testResult.status }}</span>
              <span>{{ testResult.endpoint }}</span>
            </div>
            <pre class="overflow-x-auto text-[11px] leading-relaxed">{{ testResult.data }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';

const authStore = useAuthStore();
const modalStore = useModalStore();

const showDiagnostics = ref(false);
const testLoading = ref(false);
const testResult = ref(null);

const savingPassword = ref(false);
const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const userInitial = computed(() => {
  if (!authStore.user?.name) return 'U';
  return authStore.user.name.charAt(0).toUpperCase();
});

const roleDescription = computed(() => {
  const role = authStore.role;
  switch (role) {
    case 'super_admin':
      return 'Platform Super Administrator with complete cross-tenant system authority, tenant provisioning, system health diagnostics, and audit capabilities across all schools.';
    case 'school_admin':
      return 'School Administrator with complete managerial oversight of academic structures, student enrollment rosters, teacher appointments, timetables, and report cards for this school tenant.';
    case 'teacher':
      return 'Faculty Educator with classroom access: daily roll-call attendance, gradebook entries, teaching timetable schedule, and enrolled student rosters.';
    case 'student':
      return 'Enrolled Student with personal portal access: weekly class timetable, term report cards & grades, library book borrowings, and transportation assignments.';
    case 'parent':
      return 'Guardian with family portal access: attendance oversight, academic report cards, and daily bus route tracking for linked children.';
    default:
      return 'Standard authenticated user account.';
  }
});

function formatRole(role) {
  if (!role) return 'Guest';
  return role.replace(/_/g, ' ');
}

function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  try {
    return new Date(dateStr).toLocaleString();
  } catch {
    return dateStr;
  }
}

function getRoleBadgeClass(role) {
  switch (role) {
    case 'super_admin': return 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
    case 'school_admin': return 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30';
    case 'teacher': return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
    case 'student': return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
    case 'parent': return 'bg-sky-500/20 text-sky-300 border border-sky-500/30';
    default: return 'bg-slate-700 text-slate-300';
  }
}

async function handleChangePassword() {
  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    modalStore.alert('New password and confirmation do not match.', { type: 'warning' });
    return;
  }

  savingPassword.value = true;
  try {
    await axios.post('/auth/change-password', passwordForm.value);
    modalStore.toast('Your password has been changed successfully.', 'success');
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    };
  } catch (err) {
    const msg = err.response?.data?.error?.message 
      || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join('\n') : null)
      || 'Failed to change password. Please verify your current password.';
    modalStore.alert(msg, { type: 'error', title: 'Password Update Failed' });
  } finally {
    savingPassword.value = false;
  }
}

async function handleSignOut() {
  const confirmed = await modalStore.confirm({
    title: 'Sign Out',
    message: 'Are you sure you want to sign out of your session?',
    confirmText: 'Sign Out',
    cancelText: 'Stay',
    destructive: true,
  });
  if (confirmed) {
    await authStore.logout();
    window.location.href = '/login';
  }
}

// Super Admin Diagnostic Tools
const personas = [
  { name: 'Super Admin', email: 'superadmin@bina.test', role: 'super_admin', school: 'Platform Wide' },
  { name: 'Principal Skinner', email: 'admin@greenwood.edu', role: 'school_admin', school: 'Greenwood High' },
  { name: 'Edna Krabappel', email: 'teacher@greenwood.edu', role: 'teacher', school: 'Greenwood High' },
  { name: 'Bart Simpson', email: 'student@greenwood.edu', role: 'student', school: 'Greenwood High' },
  { name: 'Dean Thomas', email: 'admin@oakridge.edu', role: 'school_admin', school: 'Oakridge Academy' },
  { name: 'Minerva McGonagall', email: 'teacher@oakridge.edu', role: 'teacher', school: 'Oakridge Academy' },
  { name: 'Harry Potter', email: 'student@oakridge.edu', role: 'student', school: 'Oakridge Academy' },
  { name: 'James Potter', email: 'parent@oakridge.edu', role: 'parent', school: 'Oakridge Academy' },
];

async function quickLogin(email) {
  testResult.value = null;
  await authStore.login(email, 'password123');
}

async function testAdminEndpoint() {
  testLoading.value = true;
  testResult.value = null;
  try {
    const res = await axios.get('/admin/users');
    testResult.value = {
      status: res.status,
      endpoint: 'GET /api/admin/users',
      data: JSON.stringify(res.data, null, 2),
    };
  } catch (err) {
    testResult.value = {
      status: err.response?.status || 500,
      endpoint: 'GET /api/admin/users',
      data: JSON.stringify(err.response?.data || { error: err.message }, null, 2),
    };
  } finally {
    testLoading.value = false;
  }
}

async function testCrossTenant() {
  testLoading.value = true;
  testResult.value = null;
  const currentSchoolId = authStore.user?.school?.id || 1;
  const targetSchoolId = currentSchoolId === 1 ? 2 : 1;

  try {
    const res = await axios.get('/courses', {
      headers: { 'X-School-Id': targetSchoolId.toString() },
    });
    testResult.value = {
      status: res.status,
      endpoint: `GET /api/courses [Probe School ${targetSchoolId}]`,
      data: JSON.stringify(res.data, null, 2),
    };
  } catch (err) {
    testResult.value = {
      status: err.response?.status || 500,
      endpoint: `GET /api/courses [Probe School ${targetSchoolId}]`,
      data: JSON.stringify(err.response?.data || { error: err.message }, null, 2),
    };
  } finally {
    testLoading.value = false;
  }
}
</script>
