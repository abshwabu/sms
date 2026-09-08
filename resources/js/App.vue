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
                to="/timetable" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Timetable
              </router-link>
              <router-link 
                to="/library" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Library
              </router-link>
              <router-link 
                to="/communications" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition flex items-center gap-1.5"
              >
                <span>Communications</span>
                <span 
                  v-if="commStore.unreadCount > 0" 
                  class="px-1.5 py-0.2 rounded-full bg-rose-500 text-white font-mono text-[10px] font-bold shadow-sm"
                >
                  {{ commStore.unreadCount > 99 ? '99+' : commStore.unreadCount }}
                </span>
              </router-link>
              <router-link 
                to="/transport" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Transport
              </router-link>
              <router-link 
                to="/grading" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Grading &amp; Reports
              </router-link>
              <router-link 
                to="/attendance" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Attendance
              </router-link>
              <router-link 
                to="/students" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Students &amp; Enrollment
              </router-link>
              <router-link 
                to="/parents" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Parent Portal
              </router-link>
              <router-link 
                to="/staff" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Staff &amp; Teachers
              </router-link>
              <router-link 
                to="/academic" 
                class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition"
              >
                Academic Structure
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
            <!-- In-App Notification Center Bell & Dropdown -->
            <div class="relative" ref="notificationRef">
              <button
                @click="toggleNotifications"
                type="button"
                class="relative p-2 rounded-xl bg-slate-800/80 border border-slate-700/80 text-slate-300 hover:text-white hover:border-slate-600 transition flex items-center justify-center shadow-sm"
                title="Notifications"
                aria-label="Notifications"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
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
                class="absolute right-0 mt-2 w-80 sm:w-96 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden z-50"
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
          Academic Hierarchy &bull; Grade &amp; Homeroom Centric &bull; Immutable History
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useTenantStore } from './stores/tenant';
import { useAuthStore } from './stores/auth';
import { useCommunicationsStore } from './stores/communications';

const router = useRouter();
const tenantStore = useTenantStore();
const authStore = useAuthStore();
const commStore = useCommunicationsStore();

const showNotifications = ref(false);
const notificationRef = ref(null);

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

function handleTenantChange(val) {
  if (!val) {
    tenantStore.clearTenant();
  } else {
    const match = tenantStore.schools.find((s) => s.id === parseInt(val, 10));
    tenantStore.selectSchool(match);
  }
  commStore.fetchNotifications();
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
