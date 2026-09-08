<template>
  <div class="space-y-6">
    <!-- Top Greeting & Role Switcher / Context Bar -->
    <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900 to-indigo-950/40 p-6 border border-slate-800 shadow-sm">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-2 text-xs text-slate-400">
            <span class="font-mono text-[11px] text-indigo-400 font-semibold bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20">
              {{ liveDate }}
            </span>
            <span>&bull;</span>
            <span 
              class="text-xs px-2.5 py-0.5 rounded-full font-mono font-semibold border"
              :class="roleBadgeClasses"
            >
              {{ roleLabel }}
            </span>
            <span v-if="dashboardStore.data?.school" class="text-xs text-slate-300">
              &bull; {{ dashboardStore.data.school.name }}
            </span>
            <span v-if="dashboardStore.activeRoleView" class="text-xs text-amber-400 font-medium bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
              (Role Preview Mode)
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
            {{ greetingText }}
          </h1>
          <p class="text-sm text-slate-400 mt-1">
            {{ subtitleText }}
          </p>
        </div>

        <!-- Role Preview Pills & Refresh (For Admins or Interactive Demo) -->
        <div class="flex flex-wrap items-center gap-2">
          <div v-if="canSwitchRoles" class="flex items-center gap-1 bg-slate-950/80 border border-slate-800 p-1 rounded-xl text-xs">
            <span class="text-[11px] text-slate-500 px-2 font-mono">View as:</span>
            <button
              v-if="authStore.isSuperAdmin"
              @click="switchRole('super_admin')"
              class="px-2.5 py-1 rounded-lg transition font-medium"
              :class="currentActiveRole === 'super_admin' ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white'"
            >
              Super-Admin
            </button>
            <button
              @click="switchRole('school_admin')"
              class="px-2.5 py-1 rounded-lg transition font-medium"
              :class="currentActiveRole === 'school_admin' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'"
            >
              Admin
            </button>
            <button
              @click="switchRole('teacher')"
              class="px-2.5 py-1 rounded-lg transition font-medium"
              :class="currentActiveRole === 'teacher' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'"
            >
              Teacher
            </button>
            <button
              @click="switchRole('student')"
              class="px-2.5 py-1 rounded-lg transition font-medium"
              :class="currentActiveRole === 'student' ? 'bg-sky-600 text-white' : 'text-slate-400 hover:text-white'"
            >
              Student
            </button>
            <button
              @click="switchRole('parent')"
              class="px-2.5 py-1 rounded-lg transition font-medium"
              :class="currentActiveRole === 'parent' ? 'bg-amber-600 text-white' : 'text-slate-400 hover:text-white'"
            >
              Parent
            </button>
            <button
              v-if="dashboardStore.activeRoleView"
              @click="resetRole"
              class="px-2 py-1 text-slate-400 hover:text-rose-400 text-xs transition"
              title="Reset to default role"
            >
              ✕
            </button>
          </div>

          <button
            @click="refreshDashboard"
            :disabled="dashboardStore.loading"
            class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 border border-slate-700/80 transition"
            title="Refresh dashboard metrics"
          >
            <span :class="dashboardStore.loading ? 'animate-spin inline-block' : ''">🔄</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Quick Action Launchpad (When Authenticated) -->
    <div v-if="authStore.isAuthenticated" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <router-link
        to="/attendance"
        class="p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 hover:bg-slate-800/60 transition group flex flex-col justify-between shadow-sm"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center group-hover:scale-105 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <polyline points="16 11 18 13 22 9" />
            </svg>
          </div>
          <span class="text-[10px] text-slate-500 group-hover:text-indigo-400 transition">&rarr;</span>
        </div>
        <div>
          <div class="text-xs font-bold text-white group-hover:text-indigo-300 transition">Attendance</div>
          <div class="text-[10px] text-slate-400 truncate">Daily &amp; Period</div>
        </div>
      </router-link>

      <router-link
        to="/grading"
        class="p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/40 hover:bg-slate-800/60 transition group flex flex-col justify-between shadow-sm"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:scale-105 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 11l3 3L22 4" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 012-2h11" />
            </svg>
          </div>
          <span class="text-[10px] text-slate-500 group-hover:text-emerald-400 transition">&rarr;</span>
        </div>
        <div>
          <div class="text-xs font-bold text-white group-hover:text-emerald-300 transition">Grading</div>
          <div class="text-[10px] text-slate-400 truncate">Report Cards &amp; GPA</div>
        </div>
      </router-link>

      <router-link
        to="/timetable"
        class="p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-sky-500/40 hover:bg-slate-850 transition group flex flex-col justify-between shadow-sm"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center group-hover:scale-105 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </div>
          <span class="text-[10px] text-slate-500 group-hover:text-sky-400 transition">&rarr;</span>
        </div>
        <div>
          <div class="text-xs font-bold text-white group-hover:text-sky-300 transition">Timetable</div>
          <div class="text-[10px] text-slate-400 truncate">Weekly Schedule</div>
        </div>
      </router-link>

      <router-link
        to="/communications"
        class="p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-rose-500/40 hover:bg-slate-850 transition group flex flex-col justify-between shadow-sm"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center group-hover:scale-105 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
            </svg>
          </div>
          <span class="text-[10px] text-slate-500 group-hover:text-rose-400 transition">&rarr;</span>
        </div>
        <div>
          <div class="text-xs font-bold text-white group-hover:text-rose-300 transition">Communications</div>
          <div class="text-[10px] text-slate-400 truncate">Notices &amp; Messages</div>
        </div>
      </router-link>

      <router-link
        to="/students"
        class="p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-purple-500/40 hover:bg-slate-850 transition group flex flex-col justify-between shadow-sm"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-105 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 00-3-3.87" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 010 7.75" />
            </svg>
          </div>
          <span class="text-[10px] text-slate-500 group-hover:text-purple-400 transition">&rarr;</span>
        </div>
        <div>
          <div class="text-xs font-bold text-white group-hover:text-purple-300 transition">Students</div>
          <div class="text-[10px] text-slate-400 truncate">Roster &amp; Profiles</div>
        </div>
      </router-link>

      <router-link
        to="/library"
        class="p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-amber-500/40 hover:bg-slate-850 transition group flex flex-col justify-between shadow-sm"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center group-hover:scale-105 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5v-15A2.5 2.5 0 016.5 2H20v20H6.5a2.5 2.5 0 01-2.5-2.5Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h10M6 10h10" />
            </svg>
          </div>
          <span class="text-[10px] text-slate-500 group-hover:text-amber-400 transition">&rarr;</span>
        </div>
        <div>
          <div class="text-xs font-bold text-white group-hover:text-amber-300 transition">Library</div>
          <div class="text-[10px] text-slate-400 truncate">Catalog &amp; Loans</div>
        </div>
      </router-link>
    </div>

    <!-- Error Banner -->
    <div v-if="dashboardStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>⚠️</span>
        <span>{{ dashboardStore.error }}</span>
      </div>
      <button @click="dashboardStore.error = null" class="text-rose-400 hover:text-rose-200">✕</button>
    </div>

    <!-- Loading State -->
    <div v-if="dashboardStore.loading && !dashboardStore.data" class="py-16 text-center text-slate-500">
      <div class="text-3xl mb-3 animate-pulse">⚡</div>
      <p class="text-sm">Loading role-tailored dashboard metrics...</p>
    </div>

    <!-- ================================================================= -->
    <!-- 1. GUEST / DEMO SIGN-IN STATE (If not authenticated)             -->
    <!-- ================================================================= -->
    <div v-else-if="!authStore.isAuthenticated" class="space-y-6">
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-8 text-center max-w-2xl mx-auto shadow-sm">
        <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center font-bold text-white text-xl mx-auto mb-4 shadow-lg shadow-indigo-600/30">
          B
        </div>
        <h2 class="text-xl font-bold text-white">Experience Role-Based Dashboards</h2>
        <p class="text-xs text-slate-400 mt-2 max-w-md mx-auto leading-relaxed">
          Bina Schools delivers customized landing dashboards for every school role. Sign in or choose a demo persona below to explore each role's view.
        </p>

        <!-- Quick 1-Click Persona Sign-In Buttons -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-6 text-left">
          <button
            @click="demoLogin('admin@greenwood.edu')"
            class="p-3 rounded-xl border border-indigo-500/30 bg-indigo-950/20 hover:bg-indigo-900/30 transition group"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-white group-hover:text-indigo-300">🏢 School Admin</span>
              <span class="text-[10px] text-indigo-400 font-mono">1-Click</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Enrollment stats, attendance trends, pending actions</p>
          </button>

          <button
            @click="demoLogin('teacher@greenwood.edu')"
            class="p-3 rounded-xl border border-emerald-500/30 bg-emerald-950/20 hover:bg-emerald-900/30 transition group"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-white group-hover:text-emerald-300">👨‍🏫 Teacher</span>
              <span class="text-[10px] text-emerald-400 font-mono">1-Click</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Homeroom attendance, personal timetable, grades</p>
          </button>

          <button
            @click="demoLogin('parent@greenwood.edu')"
            class="p-3 rounded-xl border border-amber-500/30 bg-amber-950/20 hover:bg-amber-900/30 transition group"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-white group-hover:text-amber-300">👨‍👧‍👦 Parent</span>
              <span class="text-[10px] text-amber-400 font-mono">1-Click</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Multi-child switcher, transport, grades &amp; messages</p>
          </button>

          <button
            @click="demoLogin('bart.simpson@greenwood.edu')"
            class="p-3 rounded-xl border border-sky-500/30 bg-sky-950/20 hover:bg-sky-900/30 transition group"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-white group-hover:text-sky-300">🎓 Student</span>
              <span class="text-[10px] text-sky-400 font-mono">1-Click</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Today's classes, recent marks, library loans</p>
          </button>

          <button
            @click="demoLogin('superadmin@bina.test')"
            class="p-3 rounded-xl border border-purple-500/30 bg-purple-950/20 hover:bg-purple-900/30 transition group sm:col-span-2 lg:col-span-2"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-white group-hover:text-purple-300">⚡ Platform Super-Admin</span>
              <span class="text-[10px] text-purple-400 font-mono">1-Click</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Cross-tenant overview, system metrics, all school databases</p>
          </button>
        </div>

        <div class="mt-6 pt-6 border-t border-slate-800/80 flex flex-wrap items-center justify-center gap-3">
          <router-link
            to="/login"
            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white text-xs font-semibold shadow-sm transition flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
            <span>Sign In to Portal</span>
          </router-link>

          <router-link
            to="/register"
            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700/80 transition flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span>Register New School</span>
          </router-link>
        </div>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- 2. SUPER-ADMIN DASHBOARD                                         -->
    <!-- ================================================================= -->
    <div v-else-if="currentActiveRole === 'super_admin'" class="space-y-6">
      <!-- Cross-School Metrics Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Total Tenant Schools</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1">
            {{ dashboardStore.data.summary_metrics?.total_schools || 0 }}
          </div>
          <span class="text-[11px] text-emerald-400 font-mono mt-1 inline-block">
            {{ dashboardStore.data.summary_metrics?.active_schools || 0 }} Active subscriptions
          </span>
        </div>

        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Platform Students</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
            {{ dashboardStore.data.summary_metrics?.total_students || 0 }}
          </div>
          <span class="text-[11px] text-slate-500 mt-1 inline-block">Across all schools</span>
        </div>

        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Total Staff &amp; Teachers</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
            {{ dashboardStore.data.summary_metrics?.total_staff || 0 }}
          </div>
          <span class="text-[11px] text-slate-500 mt-1 inline-block">Educators &amp; Admins</span>
        </div>

        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Active Sections</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
            {{ dashboardStore.data.summary_metrics?.total_sections || 0 }}
          </div>
          <span class="text-[11px] text-indigo-400 mt-1 inline-block">Homeroom classes</span>
        </div>
      </div>

      <!-- Schools Directory Table -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>Registered School Tenants</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
              {{ dashboardStore.data.schools?.length || 0 }}
            </span>
          </h3>
          <router-link to="/schools" class="text-xs text-indigo-400 hover:text-indigo-300 transition">
            Manage Tenants &rarr;
          </router-link>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="text-slate-400 border-b border-slate-800 font-medium">
              <tr>
                <th class="py-2.5 px-3">School Name</th>
                <th class="py-2.5 px-3">Subdomain</th>
                <th class="py-2.5 px-3 text-center">Students</th>
                <th class="py-2.5 px-3 text-center">Staff</th>
                <th class="py-2.5 px-3 text-center">Sections</th>
                <th class="py-2.5 px-3 text-center">Telegram Bot</th>
                <th class="py-2.5 px-3 text-center">Status</th>
                <th class="py-2.5 px-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 text-slate-300">
              <tr v-for="s in dashboardStore.data.schools" :key="s.id" class="hover:bg-slate-800/30 transition">
                <td class="py-3 px-3 font-semibold text-white">
                  {{ s.name }}
                </td>
                <td class="py-3 px-3 font-mono text-slate-400">
                  {{ s.subdomain }}.bina.test
                </td>
                <td class="py-3 px-3 text-center font-mono font-semibold text-white">
                  {{ s.students_count }}
                </td>
                <td class="py-3 px-3 text-center font-mono">
                  {{ s.staff_count }}
                </td>
                <td class="py-3 px-3 text-center font-mono">
                  {{ s.sections_count }}
                </td>
                <td class="py-3 px-3 text-center">
                  <span v-if="s.telegram_configured" class="text-emerald-400 text-xs" title="Telegram bot active">✓ Connected</span>
                  <span v-else class="text-slate-500 text-xs">Not configured</span>
                </td>
                <td class="py-3 px-3 text-center">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase" :class="s.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'">
                    {{ s.subscription_status }}
                  </span>
                </td>
                <td class="py-3 px-3 text-right">
                  <button
                    @click="switchTenant(s)"
                    class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition text-[11px]"
                  >
                    Select Tenant
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- System Architecture & Health -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2">Multi-Tenancy Isolation</h4>
          <p class="text-xs text-slate-400 leading-relaxed">
            Strict single-database tenant isolation enforced at the Eloquent query layer via <code class="font-mono text-indigo-300">TenantScoped</code> trait. Dual-channel resolution routes HTTP requests via subdomain hostname or <code class="font-mono text-indigo-300">X-School-Id</code> header.
          </p>
        </div>
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2">Platform Health Status</h4>
          <div class="space-y-1.5 text-xs">
            <div class="flex items-center justify-between text-slate-300">
              <span>Database Connection:</span>
              <span class="text-emerald-400 font-medium">✓ SQLite/MySQL Online</span>
            </div>
            <div class="flex items-center justify-between text-slate-300">
              <span>Tenant Resolver:</span>
              <span class="text-indigo-400 font-mono">Subdomain &amp; Header</span>
            </div>
            <div class="flex items-center justify-between text-slate-300">
              <span>Cache &amp; Queue:</span>
              <span class="text-slate-400 font-mono">{{ dashboardStore.data.system_health?.cache_driver }} / {{ dashboardStore.data.system_health?.queue_connection }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- 3. SCHOOL-ADMIN DASHBOARD                                        -->
    <!-- ================================================================= -->
    <div v-else-if="currentActiveRole === 'school_admin'" class="space-y-6">
      <!-- Enrollment Stats & Capacity Utilization -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Active Enrolled Students</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
            {{ dashboardStore.data.enrollment_stats?.total_students || 0 }}
          </div>
          <span class="text-[11px] text-emerald-400 font-mono mt-1 inline-block">
            Capacity: {{ dashboardStore.data.enrollment_stats?.capacity_utilized_percent || 0 }}% utilized
          </span>
        </div>

        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Active Teaching &amp; Staff</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
            {{ dashboardStore.data.enrollment_stats?.total_staff || 0 }}
          </div>
          <span class="text-[11px] text-slate-500 mt-1 inline-block">Assigned educators</span>
        </div>

        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Total Class Sections</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
            {{ dashboardStore.data.enrollment_stats?.total_sections || 0 }}
          </div>
          <span class="text-[11px] text-indigo-400 mt-1 inline-block">Total capacity: {{ dashboardStore.data.enrollment_stats?.total_capacity || 0 }}</span>
        </div>

        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="text-xs text-slate-400 font-medium">Today's Attendance Rate</div>
          <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
            {{ dashboardStore.data.attendance_trends?.today_rate || 0 }}%
          </div>
          <span class="text-[11px] text-slate-400 mt-1 inline-block">
            {{ dashboardStore.data.attendance_trends?.sections_marked_count || 0 }} of {{ dashboardStore.data.attendance_trends?.sections_total_count || 0 }} sections marked
          </span>
        </div>
      </div>

      <!-- Action Items & Approvals Bar -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <router-link
          to="/students"
          class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition flex items-center justify-between"
        >
          <div>
            <div class="text-[11px] text-slate-400">Unassigned Students</div>
            <div class="text-lg font-bold text-white mt-0.5">
              {{ dashboardStore.data.pending_actions?.unassigned_students_count || 0 }}
            </div>
          </div>
          <span class="text-xs text-indigo-400 font-medium">Review &rarr;</span>
        </router-link>

        <router-link
          to="/library"
          class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition flex items-center justify-between"
        >
          <div>
            <div class="text-[11px] text-slate-400">Overdue Library Loans</div>
            <div class="text-lg font-bold text-white mt-0.5" :class="dashboardStore.data.pending_actions?.overdue_loans_count > 0 ? 'text-amber-400' : 'text-white'">
              {{ dashboardStore.data.pending_actions?.overdue_loans_count || 0 }}
            </div>
          </div>
          <span class="text-xs text-amber-400 font-medium">Inspect &rarr;</span>
        </router-link>

        <router-link
          to="/onboarding"
          class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition flex items-center justify-between"
        >
          <div>
            <div class="text-[11px] text-slate-400">Pending Invitations</div>
            <div class="text-lg font-bold text-white mt-0.5">
              {{ dashboardStore.data.pending_actions?.pending_invitations_count || 0 }}
            </div>
          </div>
          <span class="text-xs text-slate-400 font-medium">Invites &rarr;</span>
        </router-link>

        <router-link
          to="/communications"
          class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition flex items-center justify-between"
        >
          <div>
            <div class="text-[11px] text-slate-400">Draft Announcements</div>
            <div class="text-lg font-bold text-white mt-0.5">
              {{ dashboardStore.data.pending_actions?.draft_announcements_count || 0 }}
            </div>
          </div>
          <span class="text-xs text-indigo-400 font-medium">Publish &rarr;</span>
        </router-link>
      </div>

      <!-- Attendance Trends & Grade Breakdown -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Attendance Breakdown -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
              <span>Daily Attendance Overview</span>
              <span class="text-xs text-slate-500 font-mono">{{ dashboardStore.data.attendance_trends?.today }}</span>
            </h3>
            <router-link to="/attendance" class="text-xs text-emerald-400 hover:underline">
              Full Attendance &rarr;
            </router-link>
          </div>

          <div class="grid grid-cols-4 gap-2 text-center mb-4">
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80">
              <div class="text-lg font-bold text-emerald-400">{{ dashboardStore.data.attendance_trends?.breakdown?.present || 0 }}</div>
              <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Present</div>
            </div>
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80">
              <div class="text-lg font-bold text-amber-400">{{ dashboardStore.data.attendance_trends?.breakdown?.late || 0 }}</div>
              <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Late</div>
            </div>
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80">
              <div class="text-lg font-bold text-rose-400">{{ dashboardStore.data.attendance_trends?.breakdown?.absent || 0 }}</div>
              <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Absent</div>
            </div>
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80">
              <div class="text-lg font-bold text-slate-300">{{ dashboardStore.data.attendance_trends?.breakdown?.excused || 0 }}</div>
              <div class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Excused</div>
            </div>
          </div>

          <!-- Section Completion Progress Bar -->
          <div>
            <div class="flex items-center justify-between text-xs mb-1">
              <span class="text-slate-400">Section Attendance Completion</span>
              <span class="text-slate-200 font-mono font-semibold">
                {{ dashboardStore.data.attendance_trends?.sections_marked_count || 0 }} / {{ dashboardStore.data.attendance_trends?.sections_total_count || 0 }} Sections
              </span>
            </div>
            <div class="w-full bg-slate-950 rounded-full h-2 overflow-hidden border border-slate-800">
              <div
                class="bg-emerald-500 h-2 rounded-full transition-all"
                :style="{ width: `${dashboardStore.data.attendance_trends?.sections_total_count > 0 ? (dashboardStore.data.attendance_trends?.sections_marked_count / dashboardStore.data.attendance_trends?.sections_total_count) * 100 : 0}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Grade Level Capacity Breakdown -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white">Grade Level Enrollment</h3>
            <router-link to="/academic" class="text-xs text-indigo-400 hover:underline">
              Academic Structure &rarr;
            </router-link>
          </div>

          <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
            <div
              v-for="gl in dashboardStore.data.enrollment_stats?.grade_breakdown"
              :key="gl.id"
              class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
            >
              <div>
                <span class="text-xs font-semibold text-white">{{ gl.name }}</span>
                <span class="text-[11px] text-slate-500 ml-2">({{ gl.sections_count }} Sections)</span>
              </div>
              <div class="text-right">
                <span class="text-xs font-mono font-semibold text-emerald-400">{{ gl.students_count }} Students</span>
                <span class="text-[10px] text-slate-500 ml-1">/ {{ gl.capacity }} cap</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Announcements & Composer Bar -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-bold text-white">School Announcements</h3>
            <span class="text-xs text-slate-500">Targeted bulletins</span>
          </div>
          <router-link
            to="/communications"
            class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition"
          >
            📢 Compose Bulletin
          </router-link>
        </div>

        <div v-if="dashboardStore.data.recent_announcements?.length" class="divide-y divide-slate-800/60">
          <div
            v-for="a in dashboardStore.data.recent_announcements"
            :key="a.id"
            class="py-3 flex items-start justify-between gap-4"
          >
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-white">{{ a.title }}</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded uppercase font-mono bg-slate-800 text-slate-300">
                  {{ a.audience_type }}
                </span>
              </div>
              <span class="text-[11px] text-slate-400 mt-0.5 inline-block">
                By {{ a.author_name }} &bull; {{ a.published_at || 'Draft' }}
              </span>
            </div>
            <router-link to="/communications" class="text-xs text-indigo-400 hover:underline">
              View
            </router-link>
          </div>
        </div>
        <div v-else class="py-6 text-center text-xs text-slate-500">
          No announcements published yet.
        </div>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- 4. TEACHER DASHBOARD                                             -->
    <!-- ================================================================= -->
    <div v-else-if="currentActiveRole === 'teacher'" class="space-y-6">
      <!-- Attendance Action: Today's Homeroom Sections -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
              <span>Today's Daily Homeroom Attendance</span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 font-mono">
                Homeroom Assigned
              </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Take daily attendance in under 5 clicks</p>
          </div>
          <router-link to="/attendance" class="text-xs text-emerald-400 hover:underline">
            All Attendance &rarr;
          </router-link>
        </div>

        <div v-if="dashboardStore.data.attendance_sections?.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div
            v-for="sec in dashboardStore.data.attendance_sections"
            :key="sec.section_id"
            class="p-4 rounded-xl border flex items-center justify-between"
            :class="sec.is_marked_today ? 'bg-slate-950/70 border-slate-800' : 'bg-amber-950/20 border-amber-500/40'"
          >
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-white">{{ sec.section_name }}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-300">{{ sec.grade_level }}</span>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">
                {{ sec.student_count }} Students &bull; 
                <span v-if="sec.is_marked_today" class="text-emerald-400 font-semibold">
                  ✓ Marked ({{ sec.present_count }} present, {{ sec.absent_count }} absent)
                </span>
                <span v-else class="text-amber-400 font-semibold">
                  ⚠️ Attendance Pending Today
                </span>
              </p>
            </div>

            <router-link
              :to="`/attendance?section_id=${sec.section_id}`"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
              :class="sec.is_marked_today 
                ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700' 
                : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm'"
            >
              {{ sec.is_marked_today ? 'Review' : 'Mark Attendance' }}
            </router-link>
          </div>
        </div>
        <div v-else class="py-6 text-center text-xs text-slate-500">
          No homeroom sections currently assigned to your teacher profile.
        </div>
      </div>

      <!-- Two-Column: Personal Timetable Today & Pending Grade Entry -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Today's Timetable -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
              <span>Today's Teaching Schedule</span>
              <span class="text-xs font-mono capitalize text-indigo-400">
                ({{ dashboardStore.data.today_timetable?.day_of_week }})
              </span>
            </h3>
            <router-link to="/timetable" class="text-xs text-indigo-400 hover:underline">
              Weekly Grid &rarr;
            </router-link>
          </div>

          <div v-if="dashboardStore.data.today_timetable?.slots?.length" class="space-y-2">
            <div
              v-for="slot in dashboardStore.data.today_timetable.slots"
              :key="slot.id"
              class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
            >
              <div>
                <div class="flex items-center gap-2">
                  <span class="w-6 h-6 rounded-lg bg-indigo-600/20 text-indigo-300 font-mono text-[11px] font-bold flex items-center justify-center">
                    P{{ slot.period_number }}
                  </span>
                  <span class="text-xs font-bold text-white">{{ slot.subject_name }}</span>
                </div>
                <div class="text-[11px] text-slate-400 mt-1 ml-8">
                  {{ slot.section_name }} ({{ slot.grade_level }}) &bull; {{ slot.room }}
                </div>
              </div>
              <div class="text-right font-mono text-[11px] text-slate-400">
                {{ slot.start_time?.slice(0, 5) }} - {{ slot.end_time?.slice(0, 5) }}
              </div>
            </div>
          </div>
          <div v-else class="py-8 text-center text-xs text-slate-500">
            No scheduled classes for this day.
          </div>
        </div>

        <!-- Pending Grade Entry -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white">Pending Grade Entry</h3>
            <router-link to="/grading" class="text-xs text-emerald-400 hover:underline">
              Grading Portal &rarr;
            </router-link>
          </div>

          <div v-if="dashboardStore.data.pending_grade_entry?.length" class="space-y-2.5">
            <div
              v-for="item in dashboardStore.data.pending_grade_entry"
              :key="`${item.exam_id}-${item.section_id}-${item.subject_id}`"
              class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
            >
              <div>
                <div class="text-xs font-bold text-white">{{ item.exam_name }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  {{ item.subject_name }} &bull; {{ item.section_name }}
                </div>
                <div class="text-[10px] text-amber-400 mt-1">
                  {{ item.pending_count }} student marks remaining ({{ item.entered_count }}/{{ item.total_students }})
                </div>
              </div>
              <router-link
                to="/grading"
                class="px-2.5 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 text-xs font-semibold transition"
              >
                Enter Grades
              </router-link>
            </div>
          </div>
          <div v-else class="py-8 text-center text-xs text-slate-500">
            ✓ All student marks are up-to-date.
          </div>
        </div>
      </div>

      <!-- Recent Messages from Parents -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>Recent Messages from Parents</span>
            <span class="text-xs text-slate-500 font-mono">Scoped per student</span>
          </h3>
          <router-link to="/communications?tab=messages" class="text-xs text-indigo-400 hover:underline">
            All Messages &rarr;
          </router-link>
        </div>

        <div v-if="dashboardStore.data.recent_messages?.length" class="divide-y divide-slate-800/60">
          <div
            v-for="t in dashboardStore.data.recent_messages"
            :key="t.id"
            class="py-3 flex items-start justify-between gap-4"
          >
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-white">{{ t.subject }}</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded bg-indigo-500/10 text-indigo-400 font-medium">
                  Student: {{ t.student_name }}
                </span>
              </div>
              <p class="text-[11px] text-slate-400 mt-1 line-clamp-1">
                <strong class="text-slate-300">{{ t.last_sender }}:</strong> {{ t.last_message }}
              </p>
            </div>
            <router-link
              to="/communications?tab=messages"
              class="text-xs text-indigo-400 hover:underline flex-shrink-0"
            >
              Reply &rarr;
            </router-link>
          </div>
        </div>
        <div v-else class="py-6 text-center text-xs text-slate-500">
          No new messages.
        </div>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- 5. STUDENT DASHBOARD                                             -->
    <!-- ================================================================= -->
    <div v-else-if="currentActiveRole === 'student'" class="space-y-6">
      <!-- Student Banner -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <span class="text-xs text-slate-400 font-mono">Admission #: {{ dashboardStore.data.student_profile?.admission_number }}</span>
            <h2 class="text-xl font-bold text-white mt-0.5">{{ dashboardStore.data.student_profile?.name }}</h2>
            <div class="flex items-center gap-2 mt-1 text-xs text-slate-300">
              <span class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-400 font-semibold border border-sky-500/20">
                {{ dashboardStore.data.student_profile?.grade_level }} &bull; {{ dashboardStore.data.student_profile?.section_name }}
              </span>
              <span>Homeroom: <strong class="text-white">{{ dashboardStore.data.student_profile?.homeroom_teacher }}</strong></span>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-center min-w-[100px]">
              <div class="text-xl font-bold font-mono text-emerald-400">
                {{ dashboardStore.data.attendance_summary?.rate_percent || 0 }}%
              </div>
              <div class="text-[10px] text-slate-400 uppercase tracking-wider">Attendance</div>
            </div>
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-center min-w-[100px]">
              <div class="text-xl font-bold font-mono uppercase" :class="studentTodayStatusClasses">
                {{ dashboardStore.data.attendance_summary?.today_status || 'Pending' }}
              </div>
              <div class="text-[10px] text-slate-400 uppercase tracking-wider">Today</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Two-Column: Today's Schedule & Recent Grades -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Class Timetable Today -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
              <span>Today's Class Schedule</span>
              <span class="text-xs font-mono text-sky-400 capitalize">({{ dashboardStore.data.today_timetable?.day_of_week }})</span>
            </h3>
            <router-link to="/timetable" class="text-xs text-sky-400 hover:underline">Full Schedule &rarr;</router-link>
          </div>

          <div v-if="dashboardStore.data.today_timetable?.slots?.length" class="space-y-2">
            <div
              v-for="slot in dashboardStore.data.today_timetable.slots"
              :key="slot.id"
              class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
            >
              <div>
                <div class="flex items-center gap-2">
                  <span class="w-6 h-6 rounded bg-sky-500/20 text-sky-300 font-mono text-xs font-bold flex items-center justify-center">
                    {{ slot.period_number }}
                  </span>
                  <span class="text-xs font-bold text-white">{{ slot.subject_name }}</span>
                </div>
                <div class="text-[11px] text-slate-400 mt-1 ml-8">
                  {{ slot.teacher_name }} &bull; {{ slot.room }}
                </div>
              </div>
              <div class="font-mono text-xs text-slate-400">
                {{ slot.start_time?.slice(0, 5) }} - {{ slot.end_time?.slice(0, 5) }}
              </div>
            </div>
          </div>
          <div v-else class="py-8 text-center text-xs text-slate-500">
            No classes scheduled for today.
          </div>
        </div>

        <!-- Recent Exam Grades -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white">Recent Assessment Marks</h3>
            <router-link to="/grading" class="text-xs text-emerald-400 hover:underline">All Grades &rarr;</router-link>
          </div>

          <div v-if="dashboardStore.data.recent_grades?.length" class="space-y-2">
            <div
              v-for="g in dashboardStore.data.recent_grades"
              :key="g.id"
              class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
            >
              <div>
                <div class="text-xs font-bold text-white">{{ g.subject_name }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">{{ g.exam_name }} ({{ g.exam_type }})</div>
              </div>
              <div class="text-right">
                <span class="text-sm font-bold font-mono text-white">{{ g.marks_obtained }} / {{ g.max_marks }}</span>
                <span class="ml-2 text-xs font-mono font-semibold text-emerald-400">({{ g.percentage }}%)</span>
              </div>
            </div>
          </div>
          <div v-else class="py-8 text-center text-xs text-slate-500">
            No grades published yet.
          </div>
        </div>
      </div>

      <!-- Two-Column: Library Loans & Announcements -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Library Loans -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
              <span>Borrowed Library Books</span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
                {{ dashboardStore.data.library_loans?.length || 0 }}
              </span>
            </h3>
            <router-link to="/library" class="text-xs text-indigo-400 hover:underline">Library &rarr;</router-link>
          </div>

          <div v-if="dashboardStore.data.library_loans?.length" class="space-y-2">
            <div
              v-for="loan in dashboardStore.data.library_loans"
              :key="loan.id"
              class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
            >
              <div>
                <div class="text-xs font-bold text-white">{{ loan.title }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">{{ loan.author }}</div>
              </div>
              <div class="text-right">
                <span class="text-xs font-mono text-slate-300">Due: {{ loan.due_at }}</span>
                <div v-if="loan.is_overdue" class="text-[10px] text-rose-400 font-bold">⚠️ OVERDUE</div>
              </div>
            </div>
          </div>
          <div v-else class="py-6 text-center text-xs text-slate-500">
            No active borrowed books.
          </div>
        </div>

        <!-- Targeted Announcements -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white">School Announcements</h3>
            <router-link to="/communications" class="text-xs text-indigo-400 hover:underline">All Notices &rarr;</router-link>
          </div>

          <div v-if="dashboardStore.data.announcements?.length" class="space-y-2.5">
            <div
              v-for="a in dashboardStore.data.announcements"
              :key="a.id"
              class="p-3 bg-slate-950 rounded-xl border border-slate-800/80"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-white">{{ a.title }}</span>
                <span class="text-[10px] text-slate-500 font-mono">{{ a.published_at }}</span>
              </div>
              <p class="text-[11px] text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                {{ a.body }}
              </p>
            </div>
          </div>
          <div v-else class="py-6 text-center text-xs text-slate-500">
            No announcements right now.
          </div>
        </div>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- 6. PARENT DASHBOARD                                              -->
    <!-- ================================================================= -->
    <div v-else-if="currentActiveRole === 'parent'" class="space-y-6">
      <!-- Child Switcher Bar -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Linked Children</span>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
              {{ dashboardStore.data.children?.length || 0 }} Enrolled
            </span>
          </div>
          <span class="text-xs text-slate-500 hidden sm:inline">
            1-Click switch between children's dashboards
          </span>
        </div>

        <!-- Switcher Tabs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <button
            v-for="c in dashboardStore.data.children"
            :key="c.id"
            @click="dashboardStore.selectChild(c.id)"
            class="flex items-center gap-3 p-3 rounded-xl border text-left transition relative"
            :class="dashboardStore.data.active_child_id === c.id
              ? 'bg-amber-950/30 border-amber-500/60 ring-1 ring-amber-500/30'
              : 'bg-slate-950/60 border-slate-800 hover:border-slate-700'"
          >
            <div
              class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm text-white flex-shrink-0"
              :class="dashboardStore.data.active_child_id === c.id ? 'bg-amber-600' : 'bg-slate-800'"
            >
              {{ getInitials(c.name) }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-white truncate">{{ c.name }}</span>
                <span v-if="c.is_primary" class="text-[10px] text-amber-400 font-semibold">★ Primary</span>
              </div>
              <div class="text-[11px] font-mono text-slate-400 truncate">{{ c.admission_number }}</div>
              <div class="text-[10px] text-emerald-400 truncate">{{ c.grade_level }} &bull; {{ c.section_name }}</div>
            </div>
          </button>
        </div>
      </div>

      <!-- Selected Child Dashboard Section -->
      <div v-if="dashboardStore.data.child_dashboard" class="space-y-6">
        <!-- Quick Metrics Row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs text-slate-400 font-medium">Child Attendance Rate</div>
            <div class="text-2xl sm:text-3xl font-black text-white mt-1 font-mono">
              {{ dashboardStore.data.child_dashboard.attendance?.rate_percent || 0 }}%
            </div>
            <span class="text-[11px] text-emerald-400 mt-1 inline-block">
              {{ dashboardStore.data.child_dashboard.attendance?.present_days || 0 }} days attended
            </span>
          </div>

          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs text-slate-400 font-medium">Today's Attendance Status</div>
            <div class="text-2xl sm:text-3xl font-black font-mono uppercase mt-1" :class="parentTodayStatusClasses">
              {{ dashboardStore.data.child_dashboard.attendance?.today_status || 'Pending' }}
            </div>
            <span class="text-[11px] text-slate-500 mt-1 inline-block">Daily homeroom log</span>
          </div>

          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs text-slate-400 font-medium">Class Section &amp; Homeroom</div>
            <div class="text-base font-bold text-white mt-1 truncate">
              {{ dashboardStore.data.child_dashboard.student?.section_name }}
            </div>
            <span class="text-[11px] text-slate-400 mt-1 inline-block truncate">
              Teacher: {{ dashboardStore.data.child_dashboard.student?.homeroom_teacher }}
            </span>
          </div>

          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-xs text-slate-400 font-medium">School Bus Transport</div>
            <div class="text-base font-bold text-white mt-1 truncate">
              {{ dashboardStore.data.child_dashboard.transport?.has_transport ? dashboardStore.data.child_dashboard.transport.route_name : 'No Bus Assigned' }}
            </div>
            <span class="text-[11px] text-amber-400 mt-1 inline-block truncate">
              {{ dashboardStore.data.child_dashboard.transport?.has_transport ? `Stop: ${dashboardStore.data.child_dashboard.transport.stop_name}` : 'Self transport' }}
            </span>
          </div>
        </div>

        <!-- Two-Column: Child's Schedule & Latest Grades -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Today's Schedule -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span>Today's Class Schedule</span>
                <span class="text-xs font-mono text-amber-400 capitalize">
                  ({{ dashboardStore.data.child_dashboard.today_timetable?.day_of_week }})
                </span>
              </h3>
              <router-link to="/timetable" class="text-xs text-amber-400 hover:underline">Timetable &rarr;</router-link>
            </div>

            <div v-if="dashboardStore.data.child_dashboard.today_timetable?.slots?.length" class="space-y-2">
              <div
                v-for="slot in dashboardStore.data.child_dashboard.today_timetable.slots"
                :key="slot.id"
                class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
              >
                <div>
                  <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded bg-amber-500/20 text-amber-300 font-mono text-xs font-bold flex items-center justify-center">
                      {{ slot.period_number }}
                    </span>
                    <span class="text-xs font-bold text-white">{{ slot.subject_name }}</span>
                  </div>
                  <div class="text-[11px] text-slate-400 mt-1 ml-8">
                    {{ slot.teacher_name }} &bull; {{ slot.room }}
                  </div>
                </div>
                <div class="font-mono text-xs text-slate-400">
                  {{ slot.start_time?.slice(0, 5) }} - {{ slot.end_time?.slice(0, 5) }}
                </div>
              </div>
            </div>
            <div v-else class="py-8 text-center text-xs text-slate-500">
              No classes scheduled for today.
            </div>
          </div>

          <!-- Latest Grades -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-bold text-white">Latest Assessment Scores</h3>
              <router-link to="/grading" class="text-xs text-emerald-400 hover:underline">Report Cards &rarr;</router-link>
            </div>

            <div v-if="dashboardStore.data.child_dashboard.recent_grades?.length" class="space-y-2">
              <div
                v-for="g in dashboardStore.data.child_dashboard.recent_grades"
                :key="g.id"
                class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between"
              >
                <div>
                  <div class="text-xs font-bold text-white">{{ g.subject_name }}</div>
                  <div class="text-[11px] text-slate-400 mt-0.5">{{ g.exam_name }}</div>
                </div>
                <div class="text-right">
                  <span class="text-sm font-bold font-mono text-white">{{ g.marks_obtained }} / {{ g.max_marks }}</span>
                  <span class="ml-2 text-xs font-mono font-semibold text-emerald-400">({{ g.percentage }}%)</span>
                </div>
              </div>
            </div>
            <div v-else class="py-8 text-center text-xs text-slate-500">
              No exam marks available.
            </div>
          </div>
        </div>

        <!-- Two-Column: Transport Details & Direct Teacher Messages -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Transport Details -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span>School Bus &amp; Route</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 font-mono">
                  Transport
                </span>
              </h3>
              <router-link to="/transport" class="text-xs text-amber-400 hover:underline">All Routes &rarr;</router-link>
            </div>

            <div v-if="dashboardStore.data.child_dashboard.transport?.has_transport" class="space-y-3">
              <div class="p-3.5 bg-slate-950 rounded-xl border border-slate-800/80">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-white">{{ dashboardStore.data.child_dashboard.transport.route_name }}</span>
                  <span class="text-[11px] text-slate-400 font-mono">{{ dashboardStore.data.child_dashboard.transport.vehicle_info }}</span>
                </div>
                <div class="mt-2 text-xs text-slate-300">
                  <span>Driver: <strong class="text-white">{{ dashboardStore.data.child_dashboard.transport.driver_name }}</strong></span>
                  <span class="ml-2 text-slate-400">({{ dashboardStore.data.child_dashboard.transport.driver_phone }})</span>
                </div>
              </div>

              <div class="p-3.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between text-xs">
                <div>
                  <span class="text-slate-400">Assigned Bus Stop:</span>
                  <div class="font-semibold text-white mt-0.5">{{ dashboardStore.data.child_dashboard.transport.stop_name }}</div>
                </div>
                <div class="text-right font-mono text-slate-300">
                  <div>Pickup: <strong class="text-emerald-400">{{ dashboardStore.data.child_dashboard.transport.pickup_time?.slice(0, 5) }}</strong></div>
                  <div>Dropoff: <strong class="text-amber-400">{{ dashboardStore.data.child_dashboard.transport.dropoff_time?.slice(0, 5) }}</strong></div>
                </div>
              </div>
            </div>
            <div v-else class="py-8 text-center text-xs text-slate-500">
              No school bus route assigned for this student.
            </div>
          </div>

          <!-- Messages with Teachers -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-bold text-white">Direct Teacher Chat</h3>
              <router-link
                :to="`/communications?tab=messages&student_id=${dashboardStore.data.child_dashboard.student?.id}`"
                class="px-2.5 py-1 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-semibold transition"
              >
                💬 New Message
              </router-link>
            </div>

            <div v-if="dashboardStore.data.child_dashboard.direct_messages?.length" class="space-y-2">
              <div
                v-for="msg in dashboardStore.data.child_dashboard.direct_messages"
                :key="msg.id"
                class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 flex items-start justify-between gap-3"
              >
                <div>
                  <div class="text-xs font-bold text-white">{{ msg.subject }}</div>
                  <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-1">
                    <strong class="text-slate-300">{{ msg.last_sender }}:</strong> {{ msg.last_message }}
                  </p>
                </div>
                <span class="text-[10px] text-slate-500 font-mono flex-shrink-0">{{ msg.updated_at }}</span>
              </div>
            </div>
            <div v-else class="py-8 text-center text-xs text-slate-500">
              No message threads started yet for this student.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useTenantStore } from '../stores/tenant';
import { useDashboardStore } from '../stores/dashboard';

const authStore = useAuthStore();
const tenantStore = useTenantStore();
const dashboardStore = useDashboardStore();

const liveDate = computed(() => {
  const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
  return new Date().toLocaleDateString('en-US', options);
});

const currentActiveRole = computed(() => {
  return dashboardStore.activeRoleView || dashboardStore.data?.role || authStore.role || 'guest';
});

const canSwitchRoles = computed(() => {
  return authStore.isSuperAdmin || authStore.isSchoolAdmin;
});

const roleLabel = computed(() => {
  switch (currentActiveRole.value) {
    case 'super_admin': return 'Super Administrator';
    case 'school_admin': return 'School Administrator';
    case 'teacher': return 'Teacher Workspace';
    case 'student': return 'Student Portal';
    case 'parent': return 'Parent Portal';
    default: return 'Role Dashboard';
  }
});

const roleBadgeClasses = computed(() => {
  switch (currentActiveRole.value) {
    case 'super_admin': return 'bg-purple-500/10 text-purple-400 border-purple-500/20';
    case 'school_admin': return 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20';
    case 'teacher': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
    case 'student': return 'bg-sky-500/10 text-sky-400 border-sky-500/20';
    case 'parent': return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
    default: return 'bg-slate-800 text-slate-300 border-slate-700';
  }
});

const greetingText = computed(() => {
  if (!authStore.isAuthenticated) return 'Bina Schools Multi-Tenant Platform';
  const name = authStore.user?.name || 'User';
  switch (currentActiveRole.value) {
    case 'super_admin': return `Platform Command Center`;
    case 'school_admin': return `Welcome back, ${name}`;
    case 'teacher': return `Classroom Hub: ${name}`;
    case 'student': return `Hello, ${name}!`;
    case 'parent': return `Family Portal: ${name}`;
    default: return `Welcome, ${name}`;
  }
});

const subtitleText = computed(() => {
  switch (currentActiveRole.value) {
    case 'super_admin': return 'Cross-school telemetry, tenant subscription status, and platform architecture.';
    case 'school_admin': return 'Live enrollment, today\'s attendance trends, pending action items, and bulletins.';
    case 'teacher': return 'Today\'s homeroom attendance, personal teaching timetable, and pending grading.';
    case 'student': return 'Your classes for today, recent assessment grades, and library borrowings.';
    case 'parent': return 'Switch between your children to view attendance, timetable, bus route, and teacher messages.';
    default: return 'Single database multi-tenancy with dedicated role landing dashboards.';
  }
});

const studentTodayStatusClasses = computed(() => {
  const status = dashboardStore.data?.attendance_summary?.today_status;
  if (status === 'present') return 'text-emerald-400';
  if (status === 'late') return 'text-amber-400';
  if (status === 'absent') return 'text-rose-400';
  return 'text-slate-400';
});

const parentTodayStatusClasses = computed(() => {
  const status = dashboardStore.data?.child_dashboard?.attendance?.today_status;
  if (status === 'present') return 'text-emerald-400';
  if (status === 'late') return 'text-amber-400';
  if (status === 'absent') return 'text-rose-400';
  return 'text-slate-400';
});

function getInitials(name) {
  if (!name) return 'S';
  return name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();
}

async function refreshDashboard() {
  await dashboardStore.fetchDashboard();
}

async function switchRole(role) {
  await dashboardStore.previewAsRole(role);
}

async function resetRole() {
  await dashboardStore.resetRolePreview();
}

function switchTenant(school) {
  tenantStore.selectSchool(school);
  dashboardStore.fetchDashboard();
}

async function demoLogin(email) {
  await authStore.login(email, 'password123');
  await dashboardStore.fetchDashboard();
}

onMounted(async () => {
  if (authStore.isAuthenticated) {
    await dashboardStore.fetchDashboard();
  }
});
</script>
