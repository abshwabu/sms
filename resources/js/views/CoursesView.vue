<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Courses &amp; Curriculum Catalog</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            {{ coursesStore.courses.length }} Courses
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Manage academic courses, curriculum subjects, and offerings available for timetable scheduling and grading.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <router-link
          to="/timetable"
          class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition flex items-center gap-1.5"
        >
          <span>📅 Timetables</span>
        </router-link>

        <router-link
          to="/staff"
          class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
        >
          <span>👨‍🏫 Faculty Directory</span>
        </router-link>
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="coursesStore.error" class="p-4 rounded-xl bg-red-950/50 border border-red-800/60 text-red-200 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>⚠️</span>
        <span>{{ coursesStore.error }}</span>
      </div>
      <button @click="coursesStore.fetchCourses()" class="px-2.5 py-1 bg-red-900/50 hover:bg-red-900 text-red-100 rounded text-xs">
        Retry
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Courses List (2 cols) -->
      <div class="lg:col-span-2 space-y-4">
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div>
              <h2 class="text-base font-bold text-white">Course Catalog</h2>
              <p class="text-xs text-slate-400">All courses offered in the active academic school program.</p>
            </div>

            <!-- Search input -->
            <div class="relative w-full sm:w-64">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search courses or codes..."
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div v-if="coursesStore.loading" class="text-center py-12 text-slate-400 text-xs font-mono animate-pulse">
            Loading course catalog...
          </div>

          <div v-else-if="filteredCourses.length === 0" class="text-center py-12 space-y-3">
            <div class="text-3xl">📚</div>
            <div class="text-sm font-semibold text-white">
              {{ searchQuery ? 'No matching courses found' : 'No courses in catalog yet' }}
            </div>
            <p class="text-xs text-slate-400 max-w-sm mx-auto">
              {{ searchQuery ? 'Try adjusting your search query.' : 'Use the form on the right to create your school\'s first course.' }}
            </p>
          </div>

          <div v-else class="space-y-3">
            <div 
              v-for="course in filteredCourses" 
              :key="course.id"
              class="p-4 rounded-xl bg-slate-800/40 border border-slate-800/80 hover:border-slate-700 flex items-start justify-between gap-4 transition group"
            >
              <div class="space-y-1.5 min-w-0 flex-1">
                <div class="flex items-center gap-2.5">
                  <span class="px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 font-mono text-xs font-semibold flex-shrink-0">
                    {{ course.code }}
                  </span>
                  <h3 class="text-sm font-bold text-white truncate">{{ course.name }}</h3>
                </div>

                <p v-if="course.description" class="text-xs text-slate-300 leading-relaxed line-clamp-2">
                  {{ course.description }}
                </p>

                <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
                  <span class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span>Ready for Timetables &amp; Grading</span>
                  </span>
                </div>
              </div>

              <button 
                v-if="authStore.isSchoolAdmin"
                @click="handleDeleteCourse(course)"
                title="Delete Course"
                class="text-slate-500 hover:text-rose-400 p-1.5 rounded-lg hover:bg-rose-500/10 transition opacity-60 group-hover:opacity-100 flex-shrink-0"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Course Form (1 col) -->
      <div class="space-y-4">
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
          <div>
            <h2 class="text-base font-bold text-white">Create New Course</h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Courses automatically become scheduleable subjects in Timetables and gradebooks.
            </p>
          </div>

          <form @submit.prevent="handleCreateCourse" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Course Code *</label>
              <input 
                v-model="newCourse.code"
                type="text" 
                placeholder="e.g. BIO-101, MTH-201"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 uppercase font-mono"
              />
              <span v-if="coursesStore.validationErrors?.code" class="text-rose-400 text-[11px] mt-1 block">
                {{ coursesStore.validationErrors.code[0] }}
              </span>
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Course Name *</label>
              <input 
                v-model="newCourse.name"
                type="text" 
                placeholder="e.g. Advanced Biology &amp; Genetics"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
              />
              <span v-if="coursesStore.validationErrors?.name" class="text-rose-400 text-[11px] mt-1 block">
                {{ coursesStore.validationErrors.name[0] }}
              </span>
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Description</label>
              <textarea 
                v-model="newCourse.description"
                rows="3"
                placeholder="Course syllabus, learning objectives, and scope..."
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
              ></textarea>
            </div>

            <button 
              type="submit"
              :disabled="coursesStore.loading || !newCourse.name || !newCourse.code"
              class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold rounded-lg transition shadow-md shadow-indigo-600/20"
            >
              {{ coursesStore.loading ? 'Saving Course...' : '+ Add to Catalog' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useCoursesStore } from '../stores/courses';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';

const coursesStore = useCoursesStore();
const authStore = useAuthStore();
const modalStore = useModalStore();

const searchQuery = ref('');

const newCourse = reactive({
  code: '',
  name: '',
  description: '',
});

const filteredCourses = computed(() => {
  if (!searchQuery.value.trim()) return coursesStore.courses;
  const q = searchQuery.value.toLowerCase();
  return coursesStore.courses.filter(
    (c) => c.name?.toLowerCase().includes(q) || c.code?.toLowerCase().includes(q)
  );
});

async function handleCreateCourse() {
  if (!newCourse.code || !newCourse.name) return;

  const result = await coursesStore.createCourse({
    code: newCourse.code.toUpperCase().trim(),
    name: newCourse.name.trim(),
    description: newCourse.description.trim(),
  });

  if (result.success) {
    modalStore.toast(`Course ${newCourse.code} added successfully!`, 'success');
    newCourse.code = '';
    newCourse.name = '';
    newCourse.description = '';
  } else {
    modalStore.alert(result.error || 'Failed to create course.', { type: 'error' });
  }
}

async function handleDeleteCourse(course) {
  const confirmed = await modalStore.confirm({
    title: 'Delete Course',
    message: `Are you sure you want to remove "${course.name}" (${course.code}) from the catalog? This will remove its associated timetable subject mappings.`,
    confirmText: 'Yes, Delete Course',
    destructive: true,
  });

  if (confirmed) {
    const success = await coursesStore.deleteCourse(course.id);
    if (success) {
      modalStore.toast('Course removed from catalog.', 'info');
    } else {
      modalStore.alert(coursesStore.error || 'Failed to delete course.', { type: 'error' });
    }
  }
}

onMounted(() => {
  coursesStore.fetchCourses();
});
</script>

