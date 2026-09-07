<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>API Health &amp; Context Inspector</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
            Acceptance Criteria
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Verify that the health check endpoint returns 200 with the active tenant context safely resolved.
        </p>
      </div>
    </div>

    <!-- Interactive trigger cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <button 
        @click="checkHealth(true)"
        :disabled="loading"
        class="p-5 text-left rounded-xl bg-slate-900/70 border border-indigo-500/30 hover:border-indigo-500 hover:bg-slate-900 transition flex flex-col justify-between group"
      >
        <div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono text-indigo-400 font-semibold">GET /api/health</span>
            <span class="text-xs px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300">With Tenant</span>
          </div>
          <h3 class="text-sm font-bold text-white mt-3 group-hover:text-indigo-300">
            Health with Active Tenant
          </h3>
          <p class="text-xs text-slate-400 mt-1">
            Sends <code class="text-slate-300">X-School-Id: {{ tenantStore.activeSchoolId || 'greenwood' }}</code>.
          </p>
        </div>
        <span class="text-xs text-indigo-400 font-medium mt-4">Run Request →</span>
      </button>

      <button 
        @click="checkHealth(false)"
        :disabled="loading"
        class="p-5 text-left rounded-xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 hover:bg-slate-900 transition flex flex-col justify-between group"
      >
        <div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono text-slate-400 font-semibold">GET /api/health</span>
            <span class="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-300">No Tenant</span>
          </div>
          <h3 class="text-sm font-bold text-white mt-3 group-hover:text-slate-200">
            Platform System Health
          </h3>
          <p class="text-xs text-slate-400 mt-1">
            Omits tenant headers to verify platform-level health.
          </p>
        </div>
        <span class="text-xs text-slate-400 font-medium mt-4">Run Request →</span>
      </button>

      <button 
        @click="checkStrictTenantHealth"
        :disabled="loading"
        class="p-5 text-left rounded-xl bg-slate-900/70 border border-purple-500/30 hover:border-purple-500 hover:bg-slate-900 transition flex flex-col justify-between group"
      >
        <div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono text-purple-400 font-semibold">GET /api/tenant/health</span>
            <span class="text-xs px-2 py-0.5 rounded bg-purple-500/20 text-purple-300">Strict Middleware</span>
          </div>
          <h3 class="text-sm font-bold text-white mt-3 group-hover:text-purple-300">
            Strict Tenant Enforced
          </h3>
          <p class="text-xs text-slate-400 mt-1">
            Guarded by <code class="text-purple-300">tenant.require</code> middleware.
          </p>
        </div>
        <span class="text-xs text-purple-400 font-medium mt-4">Run Request →</span>
      </button>
    </div>

    <!-- Response Visualizer -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
      <div class="px-6 py-4 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400">Response Inspector</span>
          <span 
            v-if="status" 
            class="text-xs px-2 py-0.5 rounded font-mono font-bold"
            :class="status >= 200 && status < 300 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'"
          >
            HTTP {{ status }}
          </span>
          <span v-if="lastEndpoint" class="text-xs text-slate-400 font-mono">
            {{ lastEndpoint }}
          </span>
        </div>
        <span v-if="responseTime" class="text-xs text-slate-500 font-mono">{{ responseTime }}ms</span>
      </div>

      <div class="p-6">
        <div v-if="loading" class="text-center py-8 text-slate-400 text-sm">
          <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500 mb-2"></div>
          <div>Querying health endpoint...</div>
        </div>

        <pre v-else-if="responseJson" class="p-4 bg-slate-950 rounded-lg text-emerald-400 text-xs font-mono overflow-x-auto leading-relaxed border border-slate-800/80">{{ responseJson }}</pre>

        <div v-else class="text-center py-8 text-slate-500 text-xs">
          Click any of the buttons above to invoke the health check API endpoint.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useTenantStore } from '../stores/tenant';

const tenantStore = useTenantStore();

const loading = ref(false);
const status = ref(null);
const responseTime = ref(null);
const lastEndpoint = ref('');
const responseJson = ref(null);

async function checkHealth(includeTenant = true) {
  loading.value = true;
  lastEndpoint.value = includeTenant 
    ? `GET /api/health [X-School-Id: ${tenantStore.activeSchoolId || 1}]` 
    : 'GET /api/health [No Tenant Header]';
    
  const start = performance.now();
  try {
    const headers = {};
    if (includeTenant && tenantStore.activeSchoolId) {
      headers['X-School-Id'] = tenantStore.activeSchoolId;
    }

    const res = await axios.get('/health', {
      headers: includeTenant ? headers : { 'X-School-Id': '' },
    });
    status.value = res.status;
    responseJson.value = JSON.stringify(res.data, null, 2);
  } catch (err) {
    status.value = err.response?.status || 500;
    responseJson.value = JSON.stringify(err.response?.data || { error: err.message }, null, 2);
  } finally {
    responseTime.value = Math.round(performance.now() - start);
    loading.value = false;
  }
}

async function checkStrictTenantHealth() {
  loading.value = true;
  lastEndpoint.value = `GET /api/tenant/health`;
  const start = performance.now();
  try {
    const res = await axios.get('/tenant/health');
    status.value = res.status;
    responseJson.value = JSON.stringify(res.data, null, 2);
  } catch (err) {
    status.value = err.response?.status || 500;
    responseJson.value = JSON.stringify(err.response?.data || { error: err.message }, null, 2);
  } finally {
    responseTime.value = Math.round(performance.now() - start);
    loading.value = false;
  }
}

onMounted(() => {
  checkHealth(true);
});
</script>
