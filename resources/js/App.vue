<template>
  <div class="min-h-full flex flex-col bg-slate-950 text-slate-100">
    <!-- Navigation Bar -->
    <header class="sticky top-0 z-30 bg-slate-900/90 backdrop-blur border-b border-slate-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo & Brand -->
          <div class="flex items-center gap-6">
            <router-link to="/" class="flex items-center gap-3 group">
              <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-indigo-600/30 group-hover:scale-105 transition">
                B
              </div>
              <div>
                <span class="font-bold text-base text-white tracking-tight">Bina Schools</span>
                <span class="ml-2 text-[11px] font-mono px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                  Multi-Tenant
                </span>
              </div>
            </router-link>

            <!-- Nav Links -->
            <nav class="hidden md:flex items-center gap-1 text-sm font-medium">
              <router-link 
                to="/" 
                exact
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Dashboard
              </router-link>
              <router-link 
                to="/schools" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Schools
              </router-link>
              <router-link 
                to="/courses" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Courses (Isolation Demo)
              </router-link>
              <router-link 
                to="/health" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Health Check
              </router-link>
            </nav>
          </div>

          <!-- Active Tenant Dropdown -->
          <div class="flex items-center gap-3">
            <div class="relative">
              <div class="flex items-center gap-2 bg-slate-800/80 border border-slate-700/80 rounded-xl px-3 py-1.5 shadow-sm">
                <span class="w-2 h-2 rounded-full" :class="tenantStore.hasTenant ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span class="text-xs text-slate-400 hidden sm:inline">Tenant:</span>
                <select 
                  :value="tenantStore.activeSchoolId"
                  @change="handleTenantChange($event.target.value)"
                  class="bg-transparent text-xs font-semibold text-white focus:outline-none cursor-pointer pr-2"
                >
                  <option :value="''" class="bg-slate-900 text-amber-400">None (Bypassed)</option>
                  <option 
                    v-for="s in tenantStore.schools" 
                    :key="s.id" 
                    :value="s.id"
                    class="bg-slate-900 text-white"
                  >
                    {{ s.name }} (ID: {{ s.id }})
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/60 bg-slate-950 py-6 text-center text-xs text-slate-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div>Bina Schools &bull; Laravel 11 + Vue 3 Monorepo Multi-Tenancy Architecture</div>
        <div class="font-mono text-slate-400 text-[11px]">
          Global Eloquent Scope &bull; Subdomain &amp; X-School-Id Header
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useTenantStore } from './stores/tenant';

const tenantStore = useTenantStore();

function handleTenantChange(val) {
  if (!val) {
    tenantStore.clearTenant();
  } else {
    const match = tenantStore.schools.find((s) => s.id === parseInt(val, 10));
    tenantStore.selectSchool(match);
  }
}

onMounted(() => {
  tenantStore.fetchSchools();
});
</script>
