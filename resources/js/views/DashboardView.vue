<template>
  <div class="space-y-8">
    <!-- Hero Banner -->
    <div class="rounded-2xl bg-gradient-to-r from-indigo-900/60 via-slate-800 to-purple-900/40 p-8 border border-indigo-500/20 shadow-xl">
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 mb-3">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            Laravel 11 + Vue 3 SPA Monorepo
          </div>
          <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
            Multi-Tenant School Architecture
          </h1>
          <p class="mt-2 text-slate-300 max-w-2xl leading-relaxed text-sm sm:text-base">
            Single database multi-tenancy powered by Eloquent global scoping, dual-channel tenant resolution (Subdomain &amp; <code class="bg-slate-800 px-1.5 py-0.5 rounded text-indigo-300 font-mono text-xs">X-School-Id</code>), and JSON envelope conventions.
          </p>
        </div>
        <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-700/60 shadow-lg min-w-[260px]">
          <div class="text-xs uppercase font-medium text-slate-400 tracking-wider mb-1">Active Tenant Context</div>
          <div v-if="tenantStore.hasTenant" class="space-y-1">
            <div class="text-lg font-bold text-white flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
              {{ tenantStore.activeSchoolName }}
            </div>
            <div class="text-xs text-indigo-300 font-mono">
              ID: {{ tenantStore.activeSchoolId }} | Subdomain: {{ tenantStore.activeSubdomain }}
            </div>
          </div>
          <div v-else class="text-amber-400 text-sm font-semibold flex items-center gap-2">
            <span>⚠️</span> No Tenant Active
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 hover:border-slate-700 transition">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-400">Available Schools</span>
          <span class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400 text-sm font-bold">🏢 Tenants</span>
        </div>
        <div class="mt-4 text-3xl font-extrabold text-white">{{ tenantStore.schools.length }}</div>
        <p class="mt-1 text-xs text-slate-400">Greenwood High &amp; Oakridge Academy</p>
      </div>

      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 hover:border-slate-700 transition">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-400">Scoping Strategy</span>
          <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm font-bold">🔒 Global Scope</span>
        </div>
        <div class="mt-4 text-xl font-bold text-white">TenantScoped Trait</div>
        <p class="mt-1 text-xs text-slate-400">Throws without tenant, auto-sets school_id on write</p>
      </div>

      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 hover:border-slate-700 transition">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-400">Tenant Resolvers</span>
          <span class="p-2 rounded-lg bg-purple-500/10 text-purple-400 text-sm font-bold">⚡ Dual Mode</span>
        </div>
        <div class="mt-4 text-xl font-bold text-white">Subdomain &amp; Header</div>
        <p class="mt-1 text-xs text-slate-400">HTTP host parsing or X-School-Id header</p>
      </div>
    </div>

    <!-- Action Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <router-link to="/courses" class="group bg-slate-900/60 border border-slate-800 hover:border-indigo-500/50 p-6 rounded-xl transition shadow-sm hover:shadow-indigo-500/10 flex flex-col justify-between">
        <div>
          <div class="text-xs font-semibold text-indigo-400 uppercase tracking-wider mb-2">Interactive Verification</div>
          <h2 class="text-xl font-semibold text-white group-hover:text-indigo-300 transition">
            Test Tenant Data Isolation →
          </h2>
          <p class="mt-2 text-sm text-slate-400">
            Switch between schools and verify that Greenwood High never sees courses from Oakridge Academy, and new courses are automatically scoped.
          </p>
        </div>
        <div class="mt-6 flex items-center gap-2 text-xs font-medium text-indigo-400">
          Open Courses isolation view
        </div>
      </router-link>

      <router-link to="/health" class="group bg-slate-900/60 border border-slate-800 hover:border-emerald-500/50 p-6 rounded-xl transition shadow-sm hover:shadow-emerald-500/10 flex flex-col justify-between">
        <div>
          <div class="text-xs font-semibold text-emerald-400 uppercase tracking-wider mb-2">Acceptance Criterion</div>
          <h2 class="text-xl font-semibold text-white group-hover:text-emerald-300 transition">
            API Health &amp; Context Inspector →
          </h2>
          <p class="mt-2 text-sm text-slate-400">
            Inspect the 200 OK health check response and view how the active tenant context and JSON:API envelope are resolved.
          </p>
        </div>
        <div class="mt-6 flex items-center gap-2 text-xs font-medium text-emerald-400">
          Open Health check viewer
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { useTenantStore } from '../stores/tenant';

const tenantStore = useTenantStore();
</script>
