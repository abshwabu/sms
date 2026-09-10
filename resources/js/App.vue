<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
    <!-- Desktop Fixed Sidebar (ONLY for Authenticated Users) -->
    <aside v-if="authStore.isAuthenticated" class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 lg:z-40">
      <SidebarNav />
    </aside>

    <!-- Mobile Slide-out Drawer (ONLY for Authenticated Users) -->
    <div v-if="authStore.isAuthenticated" v-show="sidebarOpen" class="fixed inset-0 z-50 lg:hidden">
      <!-- Backdrop Overlay -->
      <transition
        enter-active-class="transition-opacity ease-linear duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity ease-linear duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-show="sidebarOpen"
          @click="sidebarOpen = false" 
          class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm"
        ></div>
      </transition>

      <!-- Off-Canvas Sidebar Panel -->
      <transition
        enter-active-class="transition ease-[cubic-bezier(0.4,0,0.2,1)] duration-300 transform"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition ease-[cubic-bezier(0.4,0,0.2,1)] duration-300 transform"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
      >
        <div 
          v-show="sidebarOpen" 
          class="relative flex flex-col w-72 max-w-[85vw] h-full shadow-2xl z-50"
        >
          <SidebarNav 
            :show-close-button="true"
            @close="sidebarOpen = false"
            @navigate="sidebarOpen = false" 
          />
        </div>
      </transition>
    </div>

    <!-- Main Content Layout (Pushed on Desktop for fixed sidebar ONLY when authenticated) -->
    <div 
      class="flex flex-col flex-1 min-h-screen transition-all duration-200"
      :class="authStore.isAuthenticated ? 'lg:pl-64' : ''"
    >
      <!-- Top Responsive Header Bar -->
      <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur border-b border-slate-800 px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between flex-shrink-0">
        <!-- Left Section: Mobile Hamburger (if authenticated) or Desktop Breadcrumbs/Brand -->
        <div class="flex items-center gap-3">
          <!-- Mobile Hamburger Toggle (ONLY when authenticated) -->
          <button
            v-if="authStore.isAuthenticated"
            @click="sidebarOpen = true"
            type="button"
            class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 border border-slate-700/60 transition"
            aria-label="Open sidebar"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <!-- Brand for guests or mobile when sidebar is hidden -->
          <router-link to="/" class="flex items-center gap-2 group">
            <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-sm shadow-sm group-hover:scale-105 transition-transform duration-200">
              B
            </div>
            <span class="font-bold text-sm text-white tracking-tight">Bina Schools</span>
            <span 
              v-if="!authStore.isAuthenticated" 
              class="hidden sm:inline-block text-[10px] font-mono px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"
            >
              Multi-Tenant
            </span>
          </router-link>

          <!-- Desktop Route Breadcrumb (ONLY when authenticated) -->
          <div v-if="authStore.isAuthenticated" class="hidden lg:flex items-center gap-2 text-xs ml-2">
            <span class="text-slate-500">/</span>
            <span class="text-slate-400 font-medium">{{ currentCategory }}</span>
            <span class="text-slate-600">/</span>
            <span class="text-slate-200 font-semibold">{{ currentTitle }}</span>
          </div>
        </div>

        <!-- Right Section: Authenticated User Controls OR Guest Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
          <!-- Authenticated Controls -->
          <template v-if="authStore.isAuthenticated">
            <!-- Active School Pill (Desktop & Tablet) -->
            <div class="hidden sm:flex items-center gap-2 bg-slate-800/80 border border-slate-700/80 rounded-xl px-3 py-1.5 text-xs shadow-sm">
              <span 
                class="w-2 h-2 rounded-full" 
                :class="tenantStore.hasTenant ? 'bg-emerald-400' : 'bg-amber-400'"
              ></span>
              <span class="text-slate-400">School:</span>
              <span class="font-semibold text-slate-200 truncate max-w-[170px]">
                {{ activeSchoolName }}
              </span>
            </div>

          <!-- In-App Notification Center Bell & Dropdown -->
          <div class="relative" ref="notificationRef">
            <button
              @click="toggleNotifications"
              type="button"
              class="relative p-2 rounded-xl bg-slate-800/80 border border-slate-700/80 text-slate-300 hover:text-white hover:border-slate-600 transition flex items-center justify-center shadow-sm"
              title="Notifications"
              aria-label="Notifications"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span
                v-if="commStore.unreadCount > 0"
                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-rose-500 text-white font-bold text-[10px] rounded-full flex items-center justify-center shadow"
              >
                {{ commStore.unreadCount > 99 ? '99+' : commStore.unreadCount }}
              </span>
            </button>

            <!-- Notifications Dropdown Popover -->
            <div
              v-if="showNotifications"
              class="absolute right-0 mt-2 w-[calc(100vw-2rem)] sm:w-96 max-w-sm bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden z-50"
            >
              <!-- Popover Header -->
              <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-white">Notifications</span>
                  <span
                    v-if="commStore.unreadCount > 0"
                    class="text-[10px] px-1.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 font-mono font-semibold"
                  >
                    {{ commStore.unreadCount }} new
                  </span>
                </div>
                <button
                  v-if="commStore.unreadCount > 0"
                  @click="commStore.markAllNotificationsRead()"
                  type="button"
                  class="text-[11px] font-medium text-indigo-400 hover:text-indigo-300 transition"
                >
                  Mark all as read
                </button>
              </div>

              <!-- Notifications List -->
              <div class="max-h-80 overflow-y-auto divide-y divide-slate-800/60">
                <div
                  v-if="commStore.notifications.length === 0"
                  class="py-8 text-center text-xs text-slate-500 px-4"
                >
                  <div class="text-2xl mb-1">🔔</div>
                  No notifications yet.
                </div>

                <div
                  v-for="item in commStore.notifications.slice(0, 10)"
                  :key="item.id"
                  @click="handleNotificationClick(item)"
                  class="p-3.5 hover:bg-slate-800/60 cursor-pointer transition flex items-start gap-3 relative group"
                  :class="!item.read_at ? 'bg-indigo-950/20' : ''"
                >
                  <span
                    v-if="!item.read_at"
                    class="w-2 h-2 rounded-full bg-indigo-400 mt-1.5 flex-shrink-0"
                  ></span>
                  <span
                    v-else
                    class="w-2 h-2 rounded-full bg-transparent mt-1.5 flex-shrink-0"
                  ></span>

                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-1">
                      <span class="text-xs font-semibold text-white truncate group-hover:text-indigo-300 transition">
                        {{ item.title }}
                      </span>
                      <span class="text-[10px] text-slate-500 font-mono flex-shrink-0">
                        {{ formatRelativeTime(item.created_at) }}
                      </span>
                    </div>
                    <p class="text-[11px] text-slate-400 line-clamp-2 mt-0.5 leading-relaxed">
                      {{ item.body }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Popover Footer -->
              <div class="p-2 border-t border-slate-800 bg-slate-950/60 text-center">
                <router-link
                  to="/communications"
                  @click="showNotifications = false"
                  class="block py-1.5 text-xs font-semibold text-indigo-400 hover:text-indigo-300 hover:underline transition"
                >
                  Open Communications Center &rarr;
                </router-link>
              </div>
            </div>
          </div>

          <!-- User Status Badge -->
            <router-link 
              to="/auth"
              class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl border text-xs bg-slate-800/80 border-slate-700 text-slate-200 hover:border-slate-600 transition"
            >
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="truncate max-w-[120px] sm:max-w-none">
                {{ authStore.user?.name }}
              </span>
            </router-link>

            <!-- Sign Out Button -->
            <button
              @click="handleLogout"
              type="button"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 border border-slate-700/60 transition"
              title="Sign Out"
              aria-label="Sign Out"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
              </svg>
            </button>
          </template>

          <!-- Guest Action Buttons (When NOT authenticated) -->
          <template v-else>
            <router-link
              to="/login"
              class="px-3.5 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700/80 transition"
            >
              Sign In
            </router-link>
            <router-link
              to="/register"
              class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white text-xs font-semibold shadow-sm transition"
            >
              Register School
            </router-link>
          </template>
        </div>
      </header>

      <!-- Main Page Content -->
      <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <router-view />
      </main>

      <!-- Responsive Footer -->
      <footer class="border-t border-slate-800/60 bg-slate-950/80 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
          <div>Bina Schools &bull; Multi-Tenancy School Management Architecture</div>
          <div class="font-mono text-slate-400 text-[11px]">
            Grade &amp; Section Hierarchy &bull; Granular RBAC &bull; Audit Trail
          </div>
        </div>
      </footer>
    </div>

    <!-- Global Custom Modal & Toast Notifications -->
    <GlobalModal />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import SidebarNav from './components/SidebarNav.vue';
import GlobalModal from './components/GlobalModal.vue';
import { useTenantStore } from './stores/tenant';
import { useAuthStore } from './stores/auth';
import { useCommunicationsStore } from './stores/communications';

const router = useRouter();
const route = useRoute();
const tenantStore = useTenantStore();
const authStore = useAuthStore();
const commStore = useCommunicationsStore();

const sidebarOpen = ref(false);
const showNotifications = ref(false);
const notificationRef = ref(null);

const routeMap = {
  '/': { title: 'Executive Dashboard', category: 'Overview' },
  '/login': { title: 'Sign In to Portal', category: 'Authentication' },
  '/register': { title: 'Register Account / School', category: 'Authentication' },
  '/health': { title: 'System Diagnostics & Isolation', category: 'Overview' },
  '/academic': { title: 'Academic Structure & Terms', category: 'Academics' },
  '/courses': { title: 'Courses & Subject Isolation', category: 'Academics' },
  '/timetable': { title: 'Timetable & Elective Schedules', category: 'Academics' },
  '/grading': { title: 'Grading & Branching Report Cards', category: 'Academics' },
  '/attendance': { title: 'Daily & Period Attendance', category: 'Academics' },
  '/students': { title: 'Students & Enrollment', category: 'People & Community' },
  '/staff': { title: 'Staff, Teachers & Departments', category: 'People & Community' },
  '/parents': { title: 'Parent Portal & Multi-Child', category: 'People & Community' },
  '/communications': { title: 'Communications & Announcements', category: 'Operations' },
  '/library': { title: 'Library Catalog & Loans', category: 'Operations' },
  '/transport': { title: 'Transportation & Bus Routes', category: 'Operations' },
  '/schools': { title: 'School Entities & Multi-Tenancy', category: 'Administration' },
  '/onboarding': { title: 'School Onboarding Wizard', category: 'Administration' },
  '/auth': { title: 'Authentication & RBAC Matrix', category: 'Administration' },
};

const currentCategory = computed(() => {
  if (route.path === '/auth') {
    return authStore.isSuperAdmin ? 'Administration' : 'Account';
  }
  return routeMap[route.path]?.category || 'Management';
});

const currentTitle = computed(() => {
  if (route.path === '/auth') {
    return authStore.isSuperAdmin ? 'Platform Security & RBAC Inspector' : 'My Profile & Account';
  }
  return routeMap[route.path]?.title || 'Overview';
});

const activeSchoolName = computed(() => {
  if (!tenantStore.hasTenant) return 'Bypassed (All)';
  const school = tenantStore.schools.find((s) => s.id === tenantStore.activeSchoolId);
  return school ? school.name : `School #${tenantStore.activeSchoolId}`;
});

function toggleNotifications() {
  showNotifications.value = !showNotifications.value;
  if (showNotifications.value) {
    commStore.fetchNotifications();
  }
}

function handleOutsideClick(event) {
  if (notificationRef.value && !notificationRef.value.contains(event.target)) {
    showNotifications.value = false;
  }
}

async function handleNotificationClick(item) {
  if (!item.read_at) {
    await commStore.markNotificationRead(item.id);
  }
  showNotifications.value = false;
  if (item.data?.action_url) {
    router.push(item.data.action_url);
  } else if (item.type === 'message_received' || item.type === 'new_thread') {
    router.push('/communications?tab=messages');
  } else {
    router.push('/communications?tab=announcements');
  }
}

function formatRelativeTime(isoStr) {
  if (!isoStr) return '';
  const date = new Date(isoStr);
  const diff = Math.floor((Date.now() - date.getTime()) / 1000);
  if (diff < 60) return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

// Automatically close mobile sidebar and notifications popover on route change
watch(
  () => route.path,
  () => {
    sidebarOpen.value = false;
    showNotifications.value = false;
  }
);

async function handleLogout() {
  await authStore.logout();
  router.push('/login');
}

onMounted(() => {
  tenantStore.fetchSchools();
  authStore.fetchCurrentUser();
  commStore.fetchNotifications();
  document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick);
});
</script>
