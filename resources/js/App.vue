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
            <nav class="hidden lg:flex items-center gap-1 text-sm font-medium">
              <router-link 
                to="/" 
                exact
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Dashboard
              </router-link>
              <router-link 
                to="/auth" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Auth &amp; RBAC
              </router-link>
              <router-link 
                to="/onboarding" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Onboarding
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
                Courses (Isolation)
              </router-link>
              <router-link 
                to="/health" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Health Check
              </router-link>
            </nav>
          </div>

          <!-- Active User & Tenant Dropdowns -->
          <div class="flex items-center gap-3">
            <!-- User Status Badge -->
            <router-link 
              to="/auth"
              class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl border text-xs transition"
              :class="authStore.isAuthenticated 
                ? 'bg-slate-800/80 border-slate-700 text-slate-200 hover:border-slate-600' 
                : 'bg-indigo-600 text-white border-indigo-500 font-semibold shadow-sm'"
            >
              <span v-if="authStore.isAuthenticated" class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span>{{ authStore.isAuthenticated ? `${authStore.user?.name} (${authStore.role})` : 'Sign In' }}</span>
            </router-link>

            <!-- Tenant Selector -->
            <div class="relative hidden sm:block">
              <div class="flex items-center gap-2 bg-slate-800/80 border border-slate-700/80 rounded-xl px-3 py-1.5 shadow-sm">
                <span class="w-2 h-2 rounded-full" :class="tenantStore.hasTenant ? 'bg-indigo-400 animate-pulse' : 'bg-amber-400'"></span>
                <span class="text-xs text-slate-400">Tenant:</span>
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
          Spatie Tenant-Scoped Roles &bull; Sanctum Auth &bull; Global Eloquent Scope
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useTenantStore } from './stores/tenant';
import { useAuthStore } from './stores/auth';

const tenantStore = useTenantStore();
const authStore = useAuthStore();

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
  authStore.fetchCurrentUser();
});
</script>
