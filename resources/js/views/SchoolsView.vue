<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Schools Directory</h1>
        <p class="text-sm text-slate-400">Available tenant entities in the single-database platform.</p>
      </div>
      <button 
        @click="tenantStore.fetchSchools()" 
        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg border border-slate-700 transition"
      >
        Refresh Schools
      </button>
    </div>

    <div v-if="tenantStore.loading" class="text-center py-12 text-slate-400">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500 mb-2"></div>
      <div>Loading schools...</div>
    </div>

    <div v-else-if="tenantStore.error" class="p-4 bg-red-900/30 border border-red-700 rounded-lg text-red-200 text-sm">
      {{ tenantStore.error }}
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div 
        v-for="school in tenantStore.schools" 
        :key="school.id"
        class="bg-slate-900/70 border rounded-xl p-6 transition flex flex-col justify-between"
        :class="tenantStore.activeSchoolId === school.id ? 'border-indigo-500 ring-2 ring-indigo-500/20 shadow-lg shadow-indigo-500/5' : 'border-slate-800 hover:border-slate-700'"
      >
        <div>
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center overflow-hidden">
                <img v-if="school.logo" :src="school.logo" :alt="school.name" class="w-full h-full object-cover" />
                <span v-else class="text-xl font-bold text-indigo-400">{{ school.name.charAt(0) }}</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">{{ school.name }}</h2>
                <div class="text-xs font-mono text-indigo-400">
                  {{ school.subdomain }}.localhost
                </div>
              </div>
            </div>
            <span 
              class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider"
              :class="school.subscription_status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400'"
            >
              {{ school.subscription_status }}
            </span>
          </div>

          <div class="mt-6 space-y-2 text-xs text-slate-300">
            <div class="flex items-center gap-2">
              <span class="text-slate-500 w-20">Tenant ID:</span>
              <code class="text-slate-200 font-mono">{{ school.id }}</code>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-slate-500 w-20">Timezone:</span>
              <span>{{ school.timezone }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-slate-500 w-20">Courses:</span>
              <span class="px-2 py-0.5 rounded bg-slate-800 font-semibold text-slate-200">{{ school.courses_count ?? 0 }} scoped courses</span>
            </div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between">
          <span v-if="tenantStore.activeSchoolId === school.id" class="text-xs font-semibold text-indigo-400 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
            Currently Active Tenant
          </span>
          <span v-else class="text-xs text-slate-500">Not active</span>

          <button 
            @click="selectAndSwitch(school)"
            class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition"
            :class="tenantStore.activeSchoolId === school.id ? 'bg-indigo-600/30 text-indigo-200 border border-indigo-500/30' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm shadow-indigo-600/30'"
          >
            {{ tenantStore.activeSchoolId === school.id ? 'Selected' : 'Switch Context' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useTenantStore } from '../stores/tenant';

const router = useRouter();
const tenantStore = useTenantStore();

function selectAndSwitch(school) {
  tenantStore.selectSchool(school);
}
</script>
