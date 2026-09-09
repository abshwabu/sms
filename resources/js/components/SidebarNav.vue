<template>
  <div class="flex flex-col h-full bg-slate-900 border-r border-slate-800 text-slate-300 select-none">
    <!-- Brand Header -->
    <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800 flex-shrink-0">
      <router-link 
        to="/" 
        @click="$emit('navigate')"
        class="flex items-center gap-3 group"
      >
        <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-base shadow-sm group-hover:scale-105 transition-transform duration-200">
          B
        </div>
        <div>
          <div class="flex items-center gap-1.5">
            <span class="font-bold text-sm text-white tracking-tight">Bina Schools</span>
            <span class="text-[10px] font-mono px-1 py-0.2 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
              v1.0
            </span>
          </div>
          <span class="text-[11px] text-slate-400 block font-normal leading-none mt-0.5">
            Multi-Tenant SMS
          </span>
        </div>
      </router-link>

      <!-- Optional Close Button for Mobile Drawer -->
      <button
        v-if="showCloseButton"
        @click="$emit('close')"
        type="button"
        class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 border border-slate-700/60 transition"
        aria-label="Close sidebar"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Active Tenant Display / Switcher -->
    <div class="p-3 border-b border-slate-800/80 bg-slate-950/40 flex-shrink-0">
      <!-- Super-Admin Context Switcher -->
      <div v-if="authStore.isSuperAdmin" class="rounded-xl border border-purple-500/30 bg-slate-900/90 p-2.5">
        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400 mb-1.5 px-0.5">
          <span class="uppercase tracking-wider text-purple-400">Platform Tenant</span>
          <span 
            class="flex items-center gap-1 font-mono text-[10px]"
            :class="tenantStore.hasTenant ? 'text-emerald-400' : 'text-amber-400'"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="tenantStore.hasTenant ? 'bg-emerald-400' : 'bg-amber-400'"></span>
            {{ tenantStore.hasTenant ? 'Isolated' : 'All Tenants' }}
          </span>
        </div>

        <select 
          :value="tenantStore.activeSchoolId"
          @change="handleTenantChange($event.target.value)"
          class="w-full bg-slate-800/90 text-xs font-medium text-slate-100 rounded-lg px-2.5 py-1.5 border border-slate-700/70 focus:outline-none focus:border-purple-500 transition cursor-pointer"
        >
          <option :value="''" class="bg-slate-900 text-amber-400">All Schools (Cross-Tenant)</option>
          <option 
            v-for="s in tenantStore.schools" 
            :key="s.id" 
            :value="s.id"
            class="bg-slate-900 text-white"
          >
            {{ s.name }}
          </option>
        </select>
      </div>

      <!-- Institutional Locked Tenant (For Regular School Users) -->
      <div v-else class="rounded-xl border border-slate-800 bg-slate-900/90 p-2.5">
        <div class="flex items-center justify-between text-[10px] font-semibold text-slate-400 mb-1 px-0.5">
          <span class="uppercase tracking-wider font-mono">Assigned School</span>
          <span class="flex items-center gap-1 font-mono text-[10px] text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            Isolated
          </span>
        </div>
        <div class="text-xs font-bold text-white truncate px-0.5">
          {{ currentSchoolName }}
        </div>
        <div v-if="authStore.schoolContext?.subdomain" class="text-[10px] font-mono text-slate-400 px-0.5 mt-0.5">
          {{ authStore.schoolContext.subdomain }}.bina.edu
        </div>
      </div>
    </div>

    <!-- Navigation Scroll Area -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5 custom-scrollbar">
      <div v-for="section in navSections" :key="section.title" class="space-y-1">
        <!-- Section Header -->
        <div class="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
          {{ section.title }}
        </div>

        <!-- Section Items -->
        <router-link
          v-for="item in section.items"
          :key="item.path"
          :to="item.path"
          :exact="item.exact"
          custom
          v-slot="{ href, navigate, isActive, isExactActive }"
        >
          <a
            :href="href"
            @click="handleClick(navigate, $event)"
            :class="[
              (item.exact ? isExactActive : isActive)
                ? 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30 font-semibold shadow-inner'
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 border-transparent font-medium',
              'group flex items-center justify-between px-3 py-2 rounded-xl text-xs border transition-all duration-150 ease-[cubic-bezier(0.4,0,0.2,1)]'
            ]"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <!-- Icon -->
              <span 
                class="flex-shrink-0 transition-colors duration-150"
                :class="(item.exact ? isExactActive : isActive) ? 'text-indigo-400' : 'text-slate-400 group-hover:text-slate-200'"
              >
                <!-- SVG Icons based on item.icon -->
                <svg v-if="item.icon === 'dashboard'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <rect x="3" y="3" width="7" height="7" rx="1.5" />
                  <rect x="14" y="3" width="7" height="7" rx="1.5" />
                  <rect x="14" y="14" width="7" height="7" rx="1.5" />
                  <rect x="3" y="14" width="7" height="7" rx="1.5" />
                </svg>

                <svg v-else-if="item.icon === 'health'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                </svg>

                <svg v-else-if="item.icon === 'academic'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                  <path d="M6 12v5c3 3 9 3 12 0v-5" />
                </svg>

                <svg v-else-if="item.icon === 'courses'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                  <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
                </svg>

                <svg v-else-if="item.icon === 'timetable'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>

                <svg v-else-if="item.icon === 'grading'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M9 11l3 3L22 4" />
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                </svg>

                <svg v-else-if="item.icon === 'attendance'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <polyline points="16 11 18 13 22 9" />
                </svg>

                <svg v-else-if="item.icon === 'students'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                  <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>

                <svg v-else-if="item.icon === 'staff'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <rect x="2" y="7" width="20" height="14" rx="2" />
                  <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                </svg>

                <svg v-else-if="item.icon === 'parents'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <circle cx="9" cy="7" r="3" />
                  <path d="M3 19v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1" />
                  <circle cx="17" cy="11" r="2.5" />
                  <path d="M17 14.5a3 3 0 0 1 3 2.5v2" />
                </svg>

                <svg v-else-if="item.icon === 'communications'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>

                <svg v-else-if="item.icon === 'library'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" />
                  <path d="M6 6h10M6 10h10" />
                </svg>

                <svg v-else-if="item.icon === 'transport'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <rect x="1" y="5" width="22" height="13" rx="2" />
                  <circle cx="6" cy="18" r="2" />
                  <circle cx="18" cy="18" r="2" />
                  <line x1="9" y1="18" x2="15" y2="18" />
                  <line x1="1" y1="11" x2="23" y2="11" />
                </svg>

                <svg v-else-if="item.icon === 'schools'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <path d="M3 21h18M3 7l9-4 9 4M4 7v14M20 7v14M9 21v-5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v5M8 11h2M14 11h2M8 15h2M14 15h2" />
                </svg>

                <svg v-else-if="item.icon === 'onboarding'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                </svg>

                <svg v-else-if="item.icon === 'auth'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </span>

              <span class="truncate">{{ item.name }}</span>
            </div>

            <!-- Unread badge for communications -->
            <span 
              v-if="item.badge === 'comm' && commStore.unreadCount > 0" 
              class="px-1.5 py-0.2 rounded-full bg-rose-500 text-white font-mono text-[10px] font-bold shadow-sm flex-shrink-0"
            >
              {{ commStore.unreadCount > 99 ? '99+' : commStore.unreadCount }}
            </span>
          </a>
        </router-link>
      </div>
    </nav>

    <!-- Sidebar Footer / User Profile Card -->
    <div class="p-3 border-t border-slate-800/80 bg-slate-950/40 flex-shrink-0">
      <div 
        v-if="authStore.isAuthenticated"
        class="flex items-center justify-between p-2 rounded-xl bg-slate-800/50 border border-slate-800 hover:border-slate-700/80 transition"
      >
        <router-link 
          to="/auth"
          @click="$emit('navigate')"
          class="flex items-center gap-2.5 min-w-0 flex-1 group"
        >
          <div class="w-8 h-8 rounded-lg bg-indigo-600/30 border border-indigo-500/40 flex items-center justify-center text-xs font-bold text-indigo-300 flex-shrink-0">
            {{ userInitial }}
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-xs font-semibold text-white truncate group-hover:text-indigo-300 transition">
              {{ authStore.user?.name || 'User' }}
            </div>
            <div class="text-[10px] font-mono text-slate-400 capitalize truncate">
              {{ formatRole(authStore.role) }}
            </div>
          </div>
        </router-link>

        <button
          @click="handleLogout"
          type="button"
          class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition"
          title="Sign out"
          aria-label="Sign out"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
        </button>
      </div>

      <div v-else class="space-y-2">
        <router-link
          to="/login"
          @click="$emit('navigate')"
          class="flex items-center justify-center gap-2 w-full py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white text-xs font-semibold shadow-sm transition"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            <polyline points="10 17 15 12 10 7" />
            <line x1="15" y1="12" x2="3" y2="12" />
          </svg>
          <span>Sign In</span>
        </router-link>

        <router-link
          to="/register"
          @click="$emit('navigate')"
          class="flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-xl bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white text-[11px] font-medium border border-slate-700/80 transition"
        >
          <span>Register School</span>
          <span class="text-indigo-400">&rarr;</span>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useTenantStore } from '../stores/tenant';
import { useAuthStore } from '../stores/auth';
import { useCommunicationsStore } from '../stores/communications';

defineProps({
  showCloseButton: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['navigate', 'close']);

const tenantStore = useTenantStore();
const authStore = useAuthStore();
const commStore = useCommunicationsStore();

const currentSchoolName = computed(() => {
  return authStore.schoolContext?.name || tenantStore.activeSchoolName || 'My School';
});

const navSections = computed(() => {
  const role = authStore.role;

  // 1. Platform Super-Admin: full cross-tenant platform management
  if (role === 'super_admin') {
    return [
      {
        title: 'Platform Overview',
        items: [
          { name: 'Platform Dashboard', path: '/', exact: true, icon: 'dashboard' },
          { name: 'Health Diagnostics', path: '/health', icon: 'health' },
        ],
      },
      {
        title: 'Academics & Structure',
        items: [
          { name: 'Academic Years & Terms', path: '/academic', icon: 'academic' },
          { name: 'Courses & Catalog', path: '/courses', icon: 'courses' },
          { name: 'Timetables', path: '/timetable', icon: 'timetable' },
          { name: 'Grading & Reports', path: '/grading', icon: 'grading' },
          { name: 'Attendance', path: '/attendance', icon: 'attendance' },
        ],
      },
      {
        title: 'People & Directory',
        items: [
          { name: 'Students', path: '/students', icon: 'students' },
          { name: 'Staff & Teachers', path: '/staff', icon: 'staff' },
          { name: 'Parents', path: '/parents', icon: 'parents' },
        ],
      },
      {
        title: 'Operations',
        items: [
          { name: 'Communications', path: '/communications', icon: 'communications', badge: 'comm' },
          { name: 'Library', path: '/library', icon: 'library' },
          { name: 'Transport', path: '/transport', icon: 'transport' },
        ],
      },
      {
        title: 'System & Multi-Tenancy',
        items: [
          { name: 'Schools Directory', path: '/schools', icon: 'schools' },
          { name: 'Onboarding Wizard', path: '/onboarding', icon: 'onboarding' },
          { name: 'Auth & RBAC Matrix', path: '/auth', icon: 'auth' },
        ],
      },
    ];
  }

  // 2. School Administrator: managing their single assigned school tenant
  if (role === 'school_admin') {
    return [
      {
        title: 'School Overview',
        items: [
          { name: 'Executive Dashboard', path: '/', exact: true, icon: 'dashboard' },
        ],
      },
      {
        title: 'Academic Management',
        items: [
          { name: 'Academic Years & Terms', path: '/academic', icon: 'academic' },
          { name: 'Courses & Electives', path: '/courses', icon: 'courses' },
          { name: 'Timetable Scheduling', path: '/timetable', icon: 'timetable' },
          { name: 'Grading & Report Cards', path: '/grading', icon: 'grading' },
          { name: 'Attendance Oversight', path: '/attendance', icon: 'attendance' },
        ],
      },
      {
        title: 'School Community',
        items: [
          { name: 'Student Intake & Roster', path: '/students', icon: 'students' },
          { name: 'Teachers & Staff', path: '/staff', icon: 'staff' },
          { name: 'Parent Management', path: '/parents', icon: 'parents' },
        ],
      },
      {
        title: 'Operations & Services',
        items: [
          { name: 'Communications Center', path: '/communications', icon: 'communications', badge: 'comm' },
          { name: 'Library System', path: '/library', icon: 'library' },
          { name: 'Transport & Routes', path: '/transport', icon: 'transport' },
        ],
      },
      {
        title: 'School Administration',
        items: [
          { name: 'Onboarding & Invites', path: '/onboarding', icon: 'onboarding' },
          { name: 'Account & Security', path: '/auth', icon: 'auth' },
        ],
      },
    ];
  }

  // 3. Teacher: classroom teaching, attendance, personal timetable, grades
  if (role === 'teacher') {
    return [
      {
        title: 'Teacher Workspace',
        items: [
          { name: 'My Dashboard', path: '/', exact: true, icon: 'dashboard' },
        ],
      },
      {
        title: 'Teaching & Classroom',
        items: [
          { name: 'Teaching Timetable', path: '/timetable', icon: 'timetable' },
          { name: 'Homeroom Roll-Call', path: '/attendance', icon: 'attendance' },
          { name: 'Enter Marks & Grades', path: '/grading', icon: 'grading' },
          { name: 'Class Roster & Students', path: '/students', icon: 'students' },
          { name: 'My Subject Courses', path: '/courses', icon: 'courses' },
        ],
      },
      {
        title: 'Campus Services',
        items: [
          { name: 'Messages & Notices', path: '/communications', icon: 'communications', badge: 'comm' },
          { name: 'Library Catalog', path: '/library', icon: 'library' },
          { name: 'My Profile', path: '/auth', icon: 'auth' },
        ],
      },
    ];
  }

  // 4. Student: classes, grades, loans, communications
  if (role === 'student') {
    return [
      {
        title: 'Student Portal',
        items: [
          { name: 'My Dashboard', path: '/', exact: true, icon: 'dashboard' },
        ],
      },
      {
        title: 'My Academics',
        items: [
          { name: 'Class Timetable', path: '/timetable', icon: 'timetable' },
          { name: 'My Report Card & Marks', path: '/grading', icon: 'grading' },
        ],
      },
      {
        title: 'Campus Life',
        items: [
          { name: 'Library Loans', path: '/library', icon: 'library' },
          { name: 'My Bus Route', path: '/transport', icon: 'transport' },
          { name: 'Announcements', path: '/communications', icon: 'communications', badge: 'comm' },
          { name: 'My Account', path: '/auth', icon: 'auth' },
        ],
      },
    ];
  }

  // 5. Parent: child overview, bus, report cards, fees
  if (role === 'parent') {
    return [
      {
        title: 'Parent Portal',
        items: [
          { name: 'Family Dashboard', path: '/', exact: true, icon: 'dashboard' },
        ],
      },
      {
        title: 'Child Progress',
        items: [
          { name: 'Linked Children Portal', path: '/parents', icon: 'parents' },
          { name: 'Daily Attendance', path: '/attendance', icon: 'attendance' },
          { name: 'Report Cards & Grades', path: '/grading', icon: 'grading' },
          { name: 'Bus Transport Tracking', path: '/transport', icon: 'transport' },
        ],
      },
      {
        title: 'School Connect',
        items: [
          { name: 'School Notices & Chat', path: '/communications', icon: 'communications', badge: 'comm' },
          { name: 'Parent Account', path: '/auth', icon: 'auth' },
        ],
      },
    ];
  }

  // Fallback for guest
  return [
    {
      title: 'Overview',
      items: [
        { name: 'Dashboard', path: '/', exact: true, icon: 'dashboard' },
      ],
    },
  ];
});

const userInitial = computed(() => {
  if (!authStore.user?.name) return 'U';
  return authStore.user.name.charAt(0).toUpperCase();
});

function formatRole(role) {
  if (!role) return 'Guest';
  return role.replace(/_/g, ' ');
}

function handleClick(navigate, event) {
  navigate(event);
  emit('navigate');
}

function handleTenantChange(val) {
  if (!authStore.isSuperAdmin) return;
  if (!val) {
    tenantStore.clearTenant();
  } else {
    const match = tenantStore.schools.find((s) => s.id === parseInt(val, 10));
    tenantStore.selectSchool(match);
  }
  commStore.fetchNotifications();
}

async function handleLogout() {
  await authStore.logout();
  emit('navigate');
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #475569;
}
</style>
