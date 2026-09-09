<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Academic Hierarchy</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            Grade &amp; Homeroom Centric
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Model academic years, terms, ordered grade levels, and sections with assigned homeroom teachers.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button 
          @click="academicStore.fetchAll()"
          class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition"
        >
          Refresh Data
        </button>
      </div>
    </div>

    <!-- Active Academic Year Banner -->
    <div 
      class="rounded-xl p-5 border flex flex-col md:flex-row md:items-center justify-between gap-4"
      :class="academicStore.activeYear ? 'bg-indigo-950/40 border-indigo-500/30' : 'bg-slate-900 border-slate-800'"
    >
      <div>
        <div class="text-xs font-mono uppercase text-indigo-400 font-semibold tracking-wider">Current Academic Session</div>
        <div v-if="academicStore.activeYear" class="text-xl font-bold text-white mt-1 flex items-center gap-2">
          <span>📅 {{ academicStore.activeYear.name }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-normal">Active</span>
        </div>
        <div v-else class="text-lg font-bold text-slate-400 mt-1">No Active Academic Year</div>
        <div v-if="academicStore.activeYear" class="text-xs text-slate-400 mt-1">
          {{ academicStore.activeYear.start_date }} &rarr; {{ academicStore.activeYear.end_date }} &bull;
          {{ academicStore.activeYear.terms_count || 0 }} Terms &bull;
          {{ academicStore.activeYear.sections_count || 0 }} Sections
        </div>
      </div>

      <div v-if="academicStore.activeYear && authStore.isSchoolAdmin" class="flex items-center gap-2">
        <button 
          @click="handleCloseYear(academicStore.activeYear.id)"
          class="px-3.5 py-2 bg-amber-950/60 hover:bg-amber-900/80 border border-amber-700/80 text-amber-200 text-xs font-semibold rounded-lg transition"
        >
          🔒 Close Academic Year (Lock as Read-Only)
        </button>
      </div>
    </div>

    <!-- Academic Structure Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- 1. Academic Years List (1 col) -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-bold text-white">Academic Years</h2>
          <span class="text-xs font-mono text-slate-400">{{ academicStore.academicYears.length }} Total</span>
        </div>

        <!-- Add Year Form (School Admin Only) -->
        <div v-if="authStore.isSchoolAdmin" class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-lg space-y-2">
          <div class="text-xs font-bold text-slate-300">Add Academic Year</div>
          <div class="space-y-2 text-xs">
            <input 
              v-model="newYear.name"
              type="text" 
              placeholder="Year Name (e.g. 2026/2027)" 
              class="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white placeholder-slate-500"
            />
            <div class="grid grid-cols-2 gap-2">
              <input v-model="newYear.start_date" type="date" class="bg-slate-800 border border-slate-700 rounded px-2 py-1 text-white" />
              <input v-model="newYear.end_date" type="date" class="bg-slate-800 border border-slate-700 rounded px-2 py-1 text-white" />
            </div>
            <button 
              @click="handleCreateYear"
              class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-medium transition"
            >
              Save Year
            </button>
          </div>
        </div>

        <!-- Years List -->
        <div class="space-y-2.5">
          <div 
            v-for="year in academicStore.academicYears" 
            :key="year.id"
            class="p-3.5 rounded-lg border flex items-center justify-between transition"
            :class="year.is_closed ? 'bg-slate-950/40 border-slate-800 text-slate-400' : 'bg-slate-800/60 border-slate-700 text-slate-200'"
          >
            <div>
              <div class="font-bold text-sm text-white flex items-center gap-2">
                <span>{{ year.name }}</span>
                <span 
                  v-if="year.is_closed" 
                  class="text-[10px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-400 border border-slate-700 font-mono"
                >
                  🔒 Closed / Historical
                </span>
                <span 
                  v-else-if="year.is_active" 
                  class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono"
                >
                  Active
                </span>
              </div>
              <div class="text-[11px] text-slate-400 mt-0.5">
                {{ year.terms_count }} terms &bull; {{ year.sections_count }} sections
              </div>
            </div>

            <button 
              v-if="!year.is_closed && !year.is_active && authStore.isSchoolAdmin"
              @click="handleActivateYear(year.id)"
              class="px-2 py-1 text-[11px] bg-slate-700 hover:bg-indigo-600 text-white rounded transition"
            >
              Activate
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Grade Levels (1 col) -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-bold text-white">Grade Levels</h2>
          <span class="text-xs font-mono text-slate-400">{{ academicStore.gradeLevels.length }} Configured</span>
        </div>

        <!-- Add Grade Level Form (School Admin Only) -->
        <div v-if="authStore.isSchoolAdmin" class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-lg space-y-2">
          <div class="text-xs font-bold text-slate-300">Add Grade Level</div>
          <div class="space-y-2 text-xs">
            <input 
              v-model="newGrade.name"
              type="text" 
              placeholder="Grade Name (e.g. Kindergarten 2)" 
              class="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white placeholder-slate-500"
            />
            <div class="grid grid-cols-2 gap-2">
              <input v-model="newGrade.code" type="text" placeholder="Code (KG2)" class="bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white placeholder-slate-500" />
              <input v-model.number="newGrade.sequence" type="number" placeholder="Order" class="bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white placeholder-slate-500" />
            </div>
            <button 
              @click="handleCreateGrade"
              class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-medium transition"
            >
              Save Grade Level
            </button>
          </div>
        </div>

        <!-- Grade Levels List -->
        <div class="space-y-2">
          <div 
            v-for="grade in academicStore.gradeLevels" 
            :key="grade.id"
            class="p-3 bg-slate-800/60 border border-slate-700/80 rounded-lg flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 rounded bg-slate-700 text-xs font-bold text-indigo-300 flex items-center justify-center font-mono">
                {{ grade.sequence }}
              </span>
              <div>
                <div class="text-xs font-bold text-white">{{ grade.name }}</div>
                <div class="text-[11px] text-slate-400 font-mono">Code: {{ grade.code }}</div>
              </div>
            </div>
            <span class="text-[11px] px-2 py-0.5 rounded bg-slate-700/60 text-slate-300">
              {{ grade.sections_count || 0 }} sections
            </span>
          </div>
        </div>
      </div>

      <!-- 3. Sections & Homerooms (1 col) -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-bold text-white">Sections &amp; Homerooms</h2>
          <span class="text-xs font-mono text-slate-400">{{ academicStore.sections.length }} Total</span>
        </div>

        <!-- Add Section Form (School Admin Only) -->
        <div v-if="authStore.isSchoolAdmin" class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-lg space-y-2">
          <div class="text-xs font-bold text-slate-300">Create Section</div>
          <div class="space-y-2 text-xs">
            <select v-model="newSection.academic_year_id" class="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white">
              <option :value="''">-- Select Academic Year --</option>
              <option v-for="y in academicStore.academicYears" :key="y.id" :value="y.id">
                {{ y.name }} {{ y.is_closed ? '(Closed)' : '' }}
              </option>
            </select>
            <select v-model="newSection.grade_level_id" class="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white">
              <option :value="''">-- Select Grade Level --</option>
              <option v-for="g in academicStore.gradeLevels" :key="g.id" :value="g.id">
                {{ g.name }} ({{ g.code }})
              </option>
            </select>
            <input 
              v-model="newSection.name"
              type="text" 
              placeholder="Section Name (e.g. Section A)" 
              class="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white placeholder-slate-500"
            />
            <input 
              v-model.number="newSection.capacity" 
              type="number" 
              placeholder="Capacity (e.g. 30)" 
              class="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1.5 text-white placeholder-slate-500"
            />
            <button 
              @click="handleCreateSection"
              class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-medium transition"
            >
              Save Section
            </button>
          </div>
        </div>

        <!-- Sections List -->
        <div class="space-y-2.5">
          <div 
            v-for="sec in academicStore.sections" 
            :key="sec.id"
            class="p-3.5 bg-slate-800/60 border border-slate-700/80 rounded-lg space-y-1.5"
          >
            <div class="flex items-center justify-between">
              <div class="text-xs font-bold text-white">{{ sec.name }}</div>
              <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300">
                {{ sec.grade_level?.code }} &bull; {{ sec.academic_year?.name }}
              </span>
            </div>

            <div class="text-[11px] text-slate-300 flex items-center justify-between">
              <span>Teacher:</span>
              <span class="font-semibold text-slate-200">
                {{ sec.homeroom_teacher ? sec.homeroom_teacher.name : 'Unassigned' }}
              </span>
            </div>

            <div class="text-[11px] text-slate-400 flex items-center justify-between">
              <span>Enrollment:</span>
              <span>{{ sec.student_assignments_count || 0 }} / {{ sec.capacity }} capacity</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAcademicStore } from '../stores/academic';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';

const academicStore = useAcademicStore();
const authStore = useAuthStore();
const modalStore = useModalStore();

const newYear = reactive({ name: '', start_date: '', end_date: '' });
const newGrade = reactive({ name: '', code: '', sequence: 1 });
const newSection = reactive({ academic_year_id: '', grade_level_id: '', name: '', capacity: 30 });

async function handleCreateYear() {
  try {
    await academicStore.createAcademicYear({ ...newYear });
    newYear.name = '';
    newYear.start_date = '';
    newYear.end_date = '';
    modalStore.toast('Academic year created successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to create academic year.', { type: 'error' });
  }
}

async function handleCloseYear(yearId) {
  const confirmed = await modalStore.confirm({
    title: 'Close Academic Year',
    message: 'Are you sure you want to close this academic year? All terms, sections, and assignments will become strictly read-only historical records.',
    confirmText: 'Yes, Close Year',
    destructive: true,
  });
  if (!confirmed) return;
  try {
    await academicStore.closeAcademicYear(yearId);
    modalStore.toast('Academic year closed successfully.', 'info');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to close academic year.', { type: 'error' });
  }
}

async function handleActivateYear(yearId) {
  try {
    await academicStore.activateAcademicYear(yearId);
    modalStore.toast('Academic year activated successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to activate academic year.', { type: 'error' });
  }
}

async function handleCreateGrade() {
  try {
    await academicStore.createGradeLevel({ ...newGrade });
    newGrade.name = '';
    newGrade.code = '';
    newGrade.sequence++;
    modalStore.toast('Grade level created successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to create grade level.', { type: 'error' });
  }
}

async function handleCreateSection() {
  try {
    await academicStore.createSection({ ...newSection });
    newSection.name = '';
    modalStore.toast('Section created successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to create section.', { type: 'error' });
  }
}

onMounted(() => {
  academicStore.fetchAll();
});
</script>
