<template>
  <div class="space-y-6">
    <!-- Header with Isolation Context -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Tenant Scoped Courses</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
            GlobalScope Scoped
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Demonstration of tenant isolation. Models automatically filter and bind to the active school.
        </p>
      </div>

      <!-- Quick Tenant Switcher -->
      <div class="flex items-center gap-2">
        <label class="text-xs text-slate-400">Tenant:</label>
        <select 
          :value="tenantStore.activeSchoolId" 
          @change="onSchoolChange($event.target.value)"
          class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
        >
          <option :value="''">-- None (Simulate Missing Tenant) --</option>
          <option v-for="s in tenantStore.schools" :key="s.id" :value="s.id">
            {{ s.name }} (ID: {{ s.id }})
          </option>
        </select>
      </div>
    </div>

    <!-- Active Tenant Banner -->
    <div 
      class="p-4 rounded-xl border flex items-center justify-between"
      :class="tenantStore.hasTenant ? 'bg-indigo-950/40 border-indigo-500/30 text-indigo-200' : 'bg-amber-950/40 border-amber-500/30 text-amber-200'"
    >
      <div class="flex items-center gap-3">
        <div class="text-2xl">{{ tenantStore.hasTenant ? '🏫' : '⚠️' }}</div>
        <div>
          <div class="font-semibold text-sm">
            {{ tenantStore.hasTenant ? `Scoped to: ${tenantStore.activeSchoolName}` : 'No Tenant Context Selected' }}
          </div>
          <div class="text-xs opacity-80 font-mono">
            {{ tenantStore.hasTenant ? `Header attached: X-School-Id: ${tenantStore.activeSchoolId}` : 'No X-School-Id header sent. Requests to tenant endpoints will throw 400.' }}
          </div>
        </div>
      </div>
      <button 
        @click="coursesStore.fetchCourses()" 
        class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-xs rounded-lg border border-slate-600 transition"
      >
        Re-fetch Data
      </button>
    </div>

    <!-- Error state (e.g. when no tenant is set) -->
    <div v-if="coursesStore.error" class="p-4 rounded-xl bg-red-950/50 border border-red-800 text-red-200 text-sm">
      <div class="font-bold flex items-center gap-2 mb-1">
        <span>🛑 API Error Response</span>
      </div>
      <p>{{ coursesStore.error }}</p>
      <p class="text-xs text-red-400 mt-2 font-mono">
        This demonstrates the server-side TenantContextRequiredException safely preventing cross-tenant data leaks.
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Courses List (2 cols) -->
      <div class="lg:col-span-2 space-y-4">
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-white">Courses ({{ coursesStore.courses.length }})</h2>
            <span v-if="coursesStore.loading" class="text-xs text-indigo-400 animate-pulse">Loading...</span>
          </div>

          <div v-if="coursesStore.courses.length === 0 && !coursesStore.loading && !coursesStore.error" class="text-center py-8 text-slate-400 text-sm">
            No courses found for this tenant. Create one below!
          </div>

          <div class="space-y-3">
            <div 
              v-for="course in coursesStore.courses" 
              :key="course.id"
              class="p-4 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-start justify-between gap-4 hover:border-slate-600 transition"
            >
              <div>
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono text-xs font-semibold">
                    {{ course.code }}
                  </span>
                  <h3 class="text-sm font-bold text-white">{{ course.name }}</h3>
                </div>
                <p v-if="course.description" class="mt-1 text-xs text-slate-300 leading-relaxed">
                  {{ course.description }}
                </p>
                <div class="mt-2 flex items-center gap-4 text-[11px] text-slate-400 font-mono">
                  <span>Course ID: {{ course.id }}</span>
                  <span>School ID: {{ course.school_id }}</span>
                </div>
              </div>

              <button 
                @click="coursesStore.deleteCourse(course.id)"
                title="Delete Course"
                class="text-slate-400 hover:text-red-400 p-1 rounded transition text-xs"
              >
                ✕
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Course Form (1 col) -->
      <div class="space-y-4">
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6">
          <h2 class="text-lg font-bold text-white mb-2">Create Course</h2>
          <p class="text-xs text-slate-400 mb-4">
            New courses are automatically assigned the active school's ID via the <code class="text-indigo-300 font-mono">TenantScoped</code> model lifecycle hook.
          </p>

          <form @submit.prevent="handleCreateCourse" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-300 font-medium mb-1">Course Code</label>
              <input 
                v-model="newCourse.code"
                type="text" 
                placeholder="e.g. BIO-101"
                required
                class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
              />
              <span v-if="coursesStore.validationErrors?.code" class="text-red-400 text-[11px] mt-1 block">
                {{ coursesStore.validationErrors.code[0] }}
              </span>
            </div>

            <div>
              <label class="block text-slate-300 font-medium mb-1">Course Name</label>
              <input 
                v-model="newCourse.name"
                type="text" 
                placeholder="e.g. Advanced Biology"
                required
                class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
              />
              <span v-if="coursesStore.validationErrors?.name" class="text-red-400 text-[11px] mt-1 block">
                {{ coursesStore.validationErrors.name[0] }}
              </span>
            </div>

            <div>
              <label class="block text-slate-300 font-medium mb-1">Description</label>
              <textarea 
                v-model="newCourse.description"
                rows="3"
                placeholder="Course syllabus or overview..."
                class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
              ></textarea>
            </div>

            <button 
              type="submit"
              :disabled="coursesStore.loading || !tenantStore.hasTenant"
              class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg transition shadow-md shadow-indigo-600/30"
            >
              {{ coursesStore.loading ? 'Saving...' : 'Add Course' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch, onMounted } from 'vue';
import { useTenantStore } from '../stores/tenant';
import { useCoursesStore } from '../stores/courses';

const tenantStore = useTenantStore();
const coursesStore = useCoursesStore();

const newCourse = reactive({
  code: '',
  name: '',
  description: '',
});

function onSchoolChange(val) {
  if (!val) {
    tenantStore.clearTenant();
  } else {
    const school = tenantStore.schools.find((s) => s.id === parseInt(val, 10));
    tenantStore.selectSchool(school);
  }
}

async function handleCreateCourse() {
  const result = await coursesStore.createCourse({ ...newCourse });
  if (result.success) {
    newCourse.code = '';
    newCourse.name = '';
    newCourse.description = '';
  }
}

watch(
  () => tenantStore.activeSchoolId,
  () => {
    coursesStore.fetchCourses();
  }
);

onMounted(() => {
  coursesStore.fetchCourses();
});
</script>
