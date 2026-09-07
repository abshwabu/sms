<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Authentication &amp; RBAC Inspector</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 font-mono">
            Spatie Per-Tenant Roles
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Sanctum token authentication, cross-tenant barrier enforcement, and role-based permissions.
        </p>
      </div>

      <div v-if="authStore.isAuthenticated" class="flex items-center gap-3">
        <div class="text-right">
          <div class="text-xs text-slate-400 font-mono">Logged in as</div>
          <div class="text-sm font-bold text-indigo-300">{{ authStore.user?.name }}</div>
        </div>
        <button 
          @click="authStore.logout()"
          class="px-3.5 py-1.5 bg-red-950/40 hover:bg-red-900/60 border border-red-800 text-red-200 text-xs font-semibold rounded-lg transition"
        >
          Sign Out
        </button>
      </div>
    </div>

    <!-- 1-Click Quick Persona Logins -->
    <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-base font-bold text-white">⚡ 1-Click Persona Quick Login</h2>
          <p class="text-xs text-slate-400">Instantly switch between roles and test access boundaries.</p>
        </div>
        <span class="text-[11px] font-mono text-slate-500">Default Password: password123</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <button 
          v-for="persona in personas" 
          :key="persona.email"
          @click="quickLogin(persona.email)"
          :disabled="authStore.loading"
          class="p-3.5 rounded-lg border text-left transition flex flex-col justify-between group"
          :class="authStore.user?.email === persona.email 
            ? 'bg-indigo-950/50 border-indigo-500 ring-2 ring-indigo-500/20' 
            : 'bg-slate-800/60 border-slate-700/70 hover:border-slate-600 hover:bg-slate-800'"
        >
          <div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-white">{{ persona.name }}</span>
              <span 
                class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded font-semibold"
                :class="getRoleBadgeClass(persona.role)"
              >
                {{ persona.role }}
              </span>
            </div>
            <div class="text-[11px] text-slate-400 font-mono mt-1 truncate">{{ persona.email }}</div>
            <div class="text-[11px] text-indigo-400/90 mt-1">{{ persona.school }}</div>
          </div>
          <div class="mt-3 text-[11px] font-medium text-slate-400 group-hover:text-indigo-300">
            {{ authStore.user?.email === persona.email ? '✓ Active Session' : 'Login as Persona →' }}
          </div>
        </button>
      </div>
    </div>

    <!-- Active Session & RBAC Proof Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Session Details Panel -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-base font-bold text-white flex items-center justify-between">
          <span>Active Token &amp; Identity</span>
          <span 
            v-if="authStore.isAuthenticated"
            class="text-xs px-2 py-0.5 rounded font-mono font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
          >
            Authenticated (Sanctum)
          </span>
          <span v-else class="text-xs px-2 py-0.5 rounded font-mono font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
            Guest
          </span>
        </h2>

        <div v-if="authStore.isAuthenticated" class="space-y-3 text-xs">
          <div class="p-3 bg-slate-950 rounded-lg border border-slate-800 font-mono space-y-1">
            <div class="text-slate-500">Sanctum Token:</div>
            <div class="text-emerald-400 break-all">{{ authStore.token }}</div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
              <span class="text-slate-500 block">User Role:</span>
              <span class="font-bold text-white text-sm uppercase">{{ authStore.role }}</span>
            </div>
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
              <span class="text-slate-500 block">School Context:</span>
              <span class="font-bold text-indigo-300 text-sm">
                {{ authStore.schoolContext?.name || 'Global (Platform)' }}
              </span>
            </div>
          </div>

          <div>
            <span class="text-slate-400 block mb-1">Assigned Permissions:</span>
            <div class="flex flex-wrap gap-1.5">
              <span 
                v-for="perm in (authStore.user?.permissions || [])" 
                :key="perm"
                class="px-2 py-0.5 bg-slate-800 border border-slate-700 rounded text-[11px] font-mono text-slate-300"
              >
                {{ perm }}
              </span>
              <span v-if="!authStore.user?.permissions?.length" class="text-slate-500 italic">No explicit permissions.</span>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-12 text-slate-500 text-xs">
          Select a persona above or sign in to inspect user token, role, and active school context.
        </div>
      </div>

      <!-- RBAC Acceptance Criteria Tester -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-base font-bold text-white">
          🛡️ Acceptance Criteria Test Center
        </h2>
        <p class="text-xs text-slate-400">
          Run live requests to verify boundary enforcement directly against the API:
        </p>

        <div class="space-y-3">
          <!-- Test 1: Hit School Admin Endpoint -->
          <div class="p-4 bg-slate-950/70 border border-slate-800 rounded-lg flex items-center justify-between">
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
          <div class="p-4 bg-slate-950/70 border border-slate-800 rounded-lg flex items-center justify-between">
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
        <div v-if="testResult" class="mt-4 p-4 rounded-lg text-xs font-mono border" :class="testResult.status === 200 ? 'bg-emerald-950/40 border-emerald-700 text-emerald-300' : 'bg-red-950/40 border-red-700 text-red-300'">
          <div class="flex items-center justify-between mb-2">
            <span class="font-bold uppercase tracking-wider">Result: HTTP {{ testResult.status }}</span>
            <span>{{ testResult.endpoint }}</span>
          </div>
          <pre class="overflow-x-auto text-[11px] leading-relaxed">{{ testResult.data }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useTenantStore } from '../stores/tenant';

const authStore = useAuthStore();
const tenantStore = useTenantStore();

const testLoading = ref(false);
const testResult = ref(null);

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

function getRoleBadgeClass(role) {
  switch (role) {
    case 'super_admin': return 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
    case 'school_admin': return 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30';
    case 'teacher': return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
    case 'student': return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
    default: return 'bg-slate-700 text-slate-300';
  }
}

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
  // If currently in Greenwood (1), probe Oakridge (2)
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
