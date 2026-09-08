<template>
  <div class="space-y-16 pb-12 select-none">
    <!-- ================================================================= -->
    <!-- 1. HERO SECTION                                                  -->
    <!-- ================================================================= -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-b from-slate-900 via-slate-900/90 to-slate-950 border border-slate-800 p-8 sm:p-12 lg:p-16 text-center shadow-2xl">
      <!-- Subtle Background Glows -->
      <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 left-1/4 w-80 h-80 bg-purple-600/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 max-w-4xl mx-auto space-y-6">
        <!-- Top Pill Tag -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-medium">
          <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
          <span>Enterprise Multi-Tenant School Management Platform</span>
        </div>

        <!-- Main Headline -->
        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.15]">
          Empowering Modern Education with <span class="bg-gradient-to-r from-indigo-400 via-sky-300 to-indigo-200 bg-clip-text text-transparent">Precision &amp; Intelligence</span>
        </h1>

        <!-- Subtitle -->
        <p class="text-sm sm:text-base text-slate-400 max-w-2xl mx-auto leading-relaxed">
          A unified, high-performance platform for multi-school networks. Featuring strict tenant isolation, role-based workflows, conflict-free timetable scheduling, branching report cards, and automated parent billing.
        </p>

        <!-- Primary Call to Action Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
          <router-link
            to="/register"
            class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white text-xs sm:text-sm font-semibold shadow-sm transition flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span>Register New School</span>
          </router-link>

          <router-link
            to="/login"
            class="px-6 py-3 rounded-xl bg-slate-800/90 hover:bg-slate-700 text-slate-200 hover:text-white text-xs sm:text-sm font-semibold border border-slate-700/80 transition flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
            <span>Sign In to Portal</span>
          </router-link>

          <a
            href="#demo-personas"
            class="px-5 py-3 rounded-xl text-slate-400 hover:text-slate-200 text-xs sm:text-sm font-medium transition flex items-center gap-1.5"
          >
            <span>Explore 1-Click Demo</span>
            <span>&darr;</span>
          </a>
        </div>

        <!-- High-Level Metric Badges -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-6 border-t border-slate-800/80 max-w-3xl mx-auto">
          <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
            <div class="text-lg sm:text-xl font-bold text-white font-mono">100%</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Tenant Isolation</div>
          </div>
          <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
            <div class="text-lg sm:text-xl font-bold text-white font-mono">5 Specialized</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Role Dashboards</div>
          </div>
          <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
            <div class="text-lg sm:text-xl font-bold text-white font-mono">Real-Time</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Audit &amp; Notifications</div>
          </div>
          <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
            <div class="text-lg sm:text-xl font-bold text-white font-mono">End-to-End</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Billing &amp; Gateway</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================================================================= -->
    <!-- 2. INTERACTIVE 1-CLICK PERSONA SANDBOX                           -->
    <!-- ================================================================= -->
    <section id="demo-personas" class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-indigo-400 text-xs font-semibold uppercase tracking-wider mb-1">
            <span>⚡ Interactive Sandbox</span>
          </div>
          <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
            Explore Role-Tailored Portals in 1-Click
          </h2>
          <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-xl">
            Select any persona below to authenticate instantly and test specialized workflows, permissions, and security boundaries.
          </p>
        </div>

        <router-link
          to="/login"
          class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold transition self-start sm:self-auto"
        >
          View full credential inspector &rarr;
        </router-link>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- 1. School Admin Persona -->
        <button
          @click="$emit('demo-login', 'admin@greenwood.edu')"
          class="p-5 rounded-2xl bg-slate-900/90 border border-indigo-500/30 hover:border-indigo-500 hover:bg-slate-850 text-left transition duration-200 group flex flex-col justify-between shadow-sm"
        >
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center group-hover:scale-105 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-300 font-semibold border border-indigo-500/20">
                1-Click Login
              </span>
            </div>
            <h3 class="text-sm font-bold text-white group-hover:text-indigo-300 transition">
              School Administrator
            </h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Greenwood High &bull; Manage student intake, timetable conflicts, faculty staffing, and fee billing.
            </p>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between text-[11px] text-indigo-400 font-medium">
            <span>admin@greenwood.edu</span>
            <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </button>

        <!-- 2. Teacher Persona -->
        <button
          @click="$emit('demo-login', 'teacher@greenwood.edu')"
          class="p-5 rounded-2xl bg-slate-900/90 border border-emerald-500/30 hover:border-emerald-500 hover:bg-slate-850 text-left transition duration-200 group flex flex-col justify-between shadow-sm"
        >
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:scale-105 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <rect x="2" y="7" width="20" height="14" rx="2" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16" />
                </svg>
              </div>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-300 font-semibold border border-emerald-500/20">
                1-Click Login
              </span>
            </div>
            <h3 class="text-sm font-bold text-white group-hover:text-emerald-300 transition">
              Faculty / Teacher
            </h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Mark homeroom daily roll-call, review teaching timetable, and input assessment grading marks.
            </p>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between text-[11px] text-emerald-400 font-medium">
            <span>teacher@greenwood.edu</span>
            <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </button>

        <!-- 3. Parent Persona -->
        <button
          @click="$emit('demo-login', 'parent@greenwood.edu')"
          class="p-5 rounded-2xl bg-slate-900/90 border border-amber-500/30 hover:border-amber-500 hover:bg-slate-850 text-left transition duration-200 group flex flex-col justify-between shadow-sm"
        >
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center group-hover:scale-105 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <circle cx="9" cy="7" r="3" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 19v-1a4 4 0 014-4h4a4 4 0 014 4v1" />
                  <circle cx="17" cy="11" r="2.5" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 14.5a3 3 0 013 2.5v2" />
                </svg>
              </div>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-amber-500/10 text-amber-300 font-semibold border border-amber-500/20">
                1-Click Login
              </span>
            </div>
            <h3 class="text-sm font-bold text-white group-hover:text-amber-300 transition">
              Parent Portal
            </h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Multi-child switcher, real-time bus transport tracking, published report cards, and tuition fee payments.
            </p>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between text-[11px] text-amber-400 font-medium">
            <span>parent@greenwood.edu</span>
            <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </button>

        <!-- 4. Student Persona -->
        <button
          @click="$emit('demo-login', 'bart.simpson@greenwood.edu')"
          class="p-5 rounded-2xl bg-slate-900/90 border border-sky-500/30 hover:border-sky-500 hover:bg-slate-850 text-left transition duration-200 group flex flex-col justify-between shadow-sm"
        >
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center group-hover:scale-105 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                </svg>
              </div>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-sky-500/10 text-sky-300 font-semibold border border-sky-500/20">
                1-Click Login
              </span>
            </div>
            <h3 class="text-sm font-bold text-white group-hover:text-sky-300 transition">
              Student Workspace
            </h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Personal timetable with core &amp; electives, library loan circulation, and published report cards.
            </p>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between text-[11px] text-sky-400 font-medium">
            <span>bart.simpson@greenwood.edu</span>
            <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </button>

        <!-- 5. Platform Super-Admin Persona -->
        <button
          @click="$emit('demo-login', 'superadmin@bina.test')"
          class="p-5 rounded-2xl bg-slate-900/90 border border-purple-500/30 hover:border-purple-500 hover:bg-slate-850 text-left transition duration-200 group flex flex-col justify-between shadow-sm sm:col-span-2 lg:col-span-2"
        >
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-105 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
              </div>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 font-semibold border border-purple-500/20">
                1-Click Login
              </span>
            </div>
            <h3 class="text-sm font-bold text-white group-hover:text-purple-300 transition">
              Platform Super-Administrator
            </h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Full cross-tenant administrative control: provision new schools, inspect health diagnostics, monitor total student enrollments, and configure tenant isolation.
            </p>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between text-[11px] text-purple-400 font-medium">
            <span>superadmin@bina.test</span>
            <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
          </div>
        </button>
      </div>
    </section>

    <!-- ================================================================= -->
    <!-- 3. ARCHITECTURAL PILLARS (BENTO GRID)                            -->
    <!-- ================================================================= -->
    <section class="space-y-6">
      <div class="text-center max-w-2xl mx-auto space-y-2">
        <div class="text-indigo-400 text-xs font-semibold uppercase tracking-wider">
          Enterprise Features
        </div>
        <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
          Engineered for Institutional Scale &amp; Reliability
        </h2>
        <p class="text-xs sm:text-sm text-slate-400">
          Everything your institution needs to run smoothly across elementary, middle, and high school grades.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- Bento 1 -->
        <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <h3 class="text-sm font-bold text-white">Dual-Channel Multi-Tenancy</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Resolve tenant context via dedicated subdomains or HTTP headers with strict database query scoping that prevents cross-tenant data leakage.
          </p>
        </div>

        <!-- Bento 2 -->
        <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <h3 class="text-sm font-bold text-white">Granular RBAC Security</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Per-tenant role matrices govern student, teacher, and administrator actions. Homeroom teachers can only manage their assigned sections.
          </p>
        </div>

        <!-- Bento 3 -->
        <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
          <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </div>
          <h3 class="text-sm font-bold text-white">Conflict-Free Timetables</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Automated conflict engine detects double-booked educators, rooms, or class sections in real-time, supporting personal student elective schedules.
          </p>
        </div>

        <!-- Bento 4 -->
        <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 11l3 3L22 4" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
            </svg>
          </div>
          <h3 class="text-sm font-bold text-white">Branching Report Cards</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Support core + elective subjects with customizable grading scales, teacher remarks, GPA calculations, and immutable published snapshots.
          </p>
        </div>

        <!-- Bento 5 -->
        <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
          <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
          </div>
          <h3 class="text-sm font-bold text-white">Unified Notification Pipeline</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Attendance absences and published report cards automatically trigger real-time in-app badges, email dispatch, and Telegram alerts.
          </p>
        </div>

        <!-- Bento 6 -->
        <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h3 class="text-sm font-bold text-white">Invoicing &amp; Fee Payments</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Term tuition and bus transport fee structures, bulk student invoicing, online parent checkout with webhooks, and PDF payment receipts.
          </p>
        </div>
      </div>
    </section>

    <!-- ================================================================= -->
    <!-- 4. BOTTOM ACTION BANNER                                          -->
    <!-- ================================================================= -->
    <section class="rounded-3xl bg-gradient-to-r from-indigo-950/60 via-slate-900 to-slate-900 border border-slate-800 p-8 sm:p-12 flex flex-col sm:flex-row items-center justify-between gap-6 shadow-xl">
      <div class="space-y-1 text-center sm:text-left">
        <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
          Ready to Modernize Your School System?
        </h3>
        <p class="text-xs sm:text-sm text-slate-400 max-w-md">
          Provision a brand new school tenant in seconds, or sign in to your institution's portal.
        </p>
      </div>

      <div class="flex items-center gap-3 flex-shrink-0">
        <router-link
          to="/register"
          class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm transition"
        >
          Register School &rarr;
        </router-link>
        <router-link
          to="/login"
          class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700/80 transition"
        >
          Sign In
        </router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
defineEmits(['demo-login']);
</script>
