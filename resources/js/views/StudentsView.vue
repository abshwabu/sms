<template>
  <div class="space-y-8">
    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Student Management &amp; Enrollment</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
            Roster &amp; Promotion Lifecycle
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Track student profiles, guardian records, and maintain historical year-by-year section enrollment.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <button 
          @click="openImportModal"
          v-if="authStore.isSchoolAdmin"
          class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
        >
          <span>📥 Bulk CSV Import</span>
        </button>

        <button 
          @click="openPromotionModal"
          v-if="authStore.isSchoolAdmin"
          class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
        >
          <span>🚀 Year-End Promotion</span>
        </button>

        <button 
          @click="openAddModal"
          v-if="authStore.isSchoolAdmin"
          class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-lg border border-slate-700 transition flex items-center gap-1.5"
        >
          <span>+ Add Student</span>
        </button>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex-1 flex flex-wrap items-center gap-3">
        <!-- Search Input -->
        <div class="relative w-full md:w-72">
          <input 
            v-model="searchQuery"
            @keyup.enter="handleSearch"
            type="text" 
            placeholder="Search by name or admission #..." 
            class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
          />
        </div>

        <!-- Grade Filter -->
        <select 
          v-model="selectedGrade"
          @change="handleGradeChange"
          class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option value="">All Grade Levels</option>
          <option v-for="g in academicStore.gradeLevels" :key="g.id" :value="g.id">
            {{ g.name }}
          </option>
        </select>

        <!-- Section Filter -->
        <select 
          v-model="selectedSection"
          @change="handleSectionChange"
          class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option value="">All Sections</option>
          <option v-for="s in academicStore.sections" :key="s.id" :value="s.id">
            {{ s.name }} ({{ s.academic_year?.name }})
          </option>
        </select>

        <!-- Status Filter -->
        <select 
          v-model="selectedStatus"
          @change="handleStatusChange"
          class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="graduated">Graduated</option>
          <option value="transferred">Transferred</option>
          <option value="withdrawn">Withdrawn</option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <button 
          @click="resetAllFilters"
          class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs rounded-lg transition"
        >
          Reset Filters
        </button>
        <span class="text-xs font-mono text-slate-500">
          {{ studentsStore.pagination.total }} students found
        </span>
      </div>
    </div>

    <!-- Student Roster Table -->
    <div class="bg-slate-900/60 border border-slate-800 rounded-xl overflow-hidden">
      <div v-if="studentsStore.loading" class="p-8 text-center text-slate-400 text-xs font-mono">
        Loading roster records...
      </div>

      <div v-else-if="studentsStore.students.length === 0" class="p-12 text-center space-y-3">
        <div class="text-3xl">🎒</div>
        <div class="text-base font-semibold text-white">No students enrolled yet</div>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          Start building your student roster by importing from CSV or adding students manually.
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-950/80 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
            <tr>
              <th class="px-5 py-3">Student</th>
              <th class="px-5 py-3">Admission #</th>
              <th class="px-5 py-3">Current Section</th>
              <th class="px-5 py-3">Academic Session</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr v-for="student in studentsStore.students" :key="student.id" class="hover:bg-slate-800/30 transition">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-indigo-500/20 text-indigo-300 font-bold flex items-center justify-center border border-indigo-500/30 text-xs">
                    {{ student.user?.name ? student.user.name.charAt(0) : 'S' }}
                  </div>
                  <div>
                    <div class="font-semibold text-white">{{ student.user?.name || 'Unknown' }}</div>
                    <div class="text-slate-400 text-[11px]">{{ student.user?.email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 font-mono text-slate-300">
                {{ student.admission_number }}
              </td>
              <td class="px-5 py-3.5">
                <span v-if="student.current_section" class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300 font-medium">
                  {{ student.current_section.name }}
                </span>
                <span v-else class="text-slate-500 italic">None</span>
              </td>
              <td class="px-5 py-3.5 text-slate-400">
                {{ student.current_section?.academic_year?.name || 'N/A' }}
              </td>
              <td class="px-5 py-3.5">
                <span 
                  class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase"
                  :class="{
                    'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': student.status === 'active',
                    'bg-purple-500/20 text-purple-300 border border-purple-500/30': student.status === 'graduated',
                    'bg-amber-500/20 text-amber-300 border border-amber-500/30': student.status === 'transferred',
                    'bg-rose-500/20 text-rose-300 border border-rose-500/30': student.status === 'withdrawn',
                  }"
                >
                  {{ student.status }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right space-x-2">
                <button 
                  @click="viewStudentHistory(student.id)"
                  class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded border border-slate-700 transition"
                >
                  History
                </button>
                <button 
                  v-if="authStore.isSchoolAdmin"
                  @click="handleDeleteStudent(student.id)"
                  class="px-2.5 py-1 bg-rose-950/40 hover:bg-rose-900/60 text-rose-300 rounded border border-rose-800/50 transition"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="studentsStore.pagination.last_page > 1" class="px-5 py-3 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-400">
          Page {{ studentsStore.pagination.current_page }} of {{ studentsStore.pagination.last_page }}
        </span>
        <div class="space-x-1">
          <button 
            :disabled="studentsStore.pagination.current_page === 1"
            @click="studentsStore.fetchStudents(studentsStore.pagination.current_page - 1)"
            class="px-2.5 py-1 bg-slate-800 disabled:opacity-40 text-slate-300 rounded hover:bg-slate-700"
          >
            Prev
          </button>
          <button 
            :disabled="studentsStore.pagination.current_page === studentsStore.pagination.last_page"
            @click="studentsStore.fetchStudents(studentsStore.pagination.current_page + 1)"
            class="px-2.5 py-1 bg-slate-800 disabled:opacity-40 text-slate-300 rounded hover:bg-slate-700"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Student Detail & History Modal -->
    <div v-if="showDetailModal && studentsStore.selectedStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-2xl w-full p-6 space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
          <div>
            <h2 class="text-lg font-bold text-white">{{ studentsStore.selectedStudent.user?.name }}</h2>
            <div class="text-xs text-slate-400 font-mono">Admission #{{ studentsStore.selectedStudent.admission_number }}</div>
          </div>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <!-- Student Profile Details -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-xs">
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Email</span>
            <span class="text-white font-medium">{{ studentsStore.selectedStudent.user?.email }}</span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Date of Birth</span>
            <span class="text-white font-medium">{{ studentsStore.selectedStudent.date_of_birth || 'N/A' }}</span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Gender</span>
            <span class="text-white font-medium capitalize">{{ studentsStore.selectedStudent.gender || 'N/A' }}</span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80 col-span-2">
            <span class="text-slate-400 block mb-1">Guardian Contact</span>
            <span class="text-white font-medium">
              {{ studentsStore.selectedStudent.guardian_info?.name || 'N/A' }}
              ({{ studentsStore.selectedStudent.guardian_info?.relationship || 'Guardian' }}) &bull;
              {{ studentsStore.selectedStudent.guardian_info?.phone || 'No phone' }}
            </span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Admission Date</span>
            <span class="text-white font-medium">{{ studentsStore.selectedStudent.admission_date || 'N/A' }}</span>
          </div>
        </div>

        <!-- Year-by-Year Enrollment History -->
        <div class="space-y-3">
          <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>📜 Enrollment Lifecycle History</span>
            <span class="text-xs font-mono text-slate-400">({{ studentsStore.selectedStudent.enrollments?.length || 0 }} years)</span>
          </h3>

          <div class="space-y-2">
            <div 
              v-for="enr in studentsStore.selectedStudent.enrollments" 
              :key="enr.id"
              class="p-3.5 rounded-lg border flex items-center justify-between"
              :class="enr.status === 'enrolled' ? 'bg-indigo-950/30 border-indigo-500/30' : 'bg-slate-950 border-slate-800'"
            >
              <div>
                <div class="text-xs font-bold text-white flex items-center gap-2">
                  <span>{{ enr.academic_year?.name }}</span>
                  <span v-if="enr.academic_year?.is_closed" class="text-[10px] px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300">
                    Archived
                  </span>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  {{ enr.section?.name }} &bull; Enrolled {{ enr.enrolled_at }}
                </div>
              </div>

              <span 
                class="px-2.5 py-0.5 rounded text-[10px] font-semibold uppercase"
                :class="{
                  'bg-emerald-500/20 text-emerald-300': enr.status === 'enrolled',
                  'bg-indigo-500/20 text-indigo-300': enr.status === 'promoted',
                  'bg-purple-500/20 text-purple-300': enr.status === 'graduated',
                  'bg-amber-500/20 text-amber-300': enr.status === 'retained',
                }"
              >
                {{ enr.status }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-800">
          <button @click="showDetailModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium rounded-lg">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Bulk CSV Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h2 class="text-base font-bold text-white flex items-center gap-2">
            <span>📥 Bulk Student CSV Import</span>
          </h2>
          <button @click="showImportModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <div v-if="!importResultData" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Target Academic Year *</label>
            <select v-model="importForm.academic_year_id" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
              <option value="" disabled>Select Year</option>
              <option v-for="y in openAcademicYears" :key="y.id" :value="y.id">
                {{ y.name }} {{ y.is_active ? '(Active)' : '' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Target Section *</label>
            <select v-model="importForm.section_id" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
              <option value="" disabled>Select Section</option>
              <option v-for="s in filteredSectionsForImport" :key="s.id" :value="s.id">
                {{ s.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">CSV File * (name, dob, gender, address, guardian_name, phone)</label>
            <input type="file" ref="csvFileInput" accept=".csv,text/csv" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-slate-300 text-xs" />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Default Password (Optional)</label>
            <input v-model="importForm.default_password" type="text" placeholder="Leave empty for auto-generated passwords" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white" />
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
            <button @click="showImportModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg">Cancel</button>
            <button @click="executeImport" :disabled="importing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg disabled:opacity-50">
              {{ importing ? 'Importing...' : 'Upload &amp; Enroll' }}
            </button>
          </div>
        </div>

        <!-- Generated Credentials Review & Export -->
        <div v-else class="space-y-4 text-xs">
          <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-300">
            ✅ Successfully imported {{ importResultData.imported_count }} students! Login credentials have been generated below:
          </div>

          <div class="max-h-60 overflow-y-auto border border-slate-800 rounded-lg">
            <table class="w-full text-left text-[11px]">
              <thead class="bg-slate-950 text-slate-400 sticky top-0">
                <tr>
                  <th class="p-2">Name</th>
                  <th class="p-2">Email</th>
                  <th class="p-2">Password</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-for="c in importResultData.credentials" :key="c.student_id" class="font-mono">
                  <td class="p-2 text-white">{{ c.name }}</td>
                  <td class="p-2 text-slate-400">{{ c.email }}</td>
                  <td class="p-2 text-emerald-400">{{ c.plain_password }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-end pt-3 border-t border-slate-800">
            <button @click="finishImport" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg">
              Done
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Year-End Promotion Modal -->
    <div v-if="showPromotionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-lg w-full p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h2 class="text-base font-bold text-white flex items-center gap-2">
            <span>🚀 Section Roster Promotion</span>
          </h2>
          <button @click="showPromotionModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <div class="space-y-3.5 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Source Section Roster *</label>
            <select v-model="promotionForm.source_section_id" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
              <option value="" disabled>Select Source Section</option>
              <option v-for="s in academicStore.sections" :key="s.id" :value="s.id">
                {{ s.name }} ({{ s.academic_year?.name }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Action *</label>
            <select v-model="promotionForm.action" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
              <option value="promote">Promote to Next Grade Section</option>
              <option value="retain">Retain in Current Grade Level</option>
              <option value="graduate">Graduate Section Roster</option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Target Academic Year *</label>
            <select v-model="promotionForm.target_academic_year_id" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
              <option value="" disabled>Select Year</option>
              <option v-for="y in openAcademicYears" :key="y.id" :value="y.id">
                {{ y.name }} {{ y.is_active ? '(Active)' : '' }}
              </option>
            </select>
          </div>

          <div v-if="promotionForm.action !== 'graduate'">
            <label class="block text-slate-300 font-semibold mb-1">Target Section *</label>
            <select v-model="promotionForm.target_section_id" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
              <option value="" disabled>Select Target Section</option>
              <option v-for="s in filteredSectionsForPromotion" :key="s.id" :value="s.id">
                {{ s.name }}
              </option>
            </select>
          </div>

          <div class="p-3 bg-indigo-950/30 border border-indigo-500/30 rounded-lg text-indigo-300">
            ℹ️ All active students in the source section will be enrolled into the new academic year section in one action. Prior-year enrollment records are preserved intact.
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
            <button @click="showPromotionModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg">Cancel</button>
            <button @click="executePromotion" :disabled="promoting" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg disabled:opacity-50">
              {{ promoting ? 'Processing...' : 'Execute Promotion' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStudentsStore } from '../stores/students';
import { useAcademicStore } from '../stores/academic';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';

const studentsStore = useStudentsStore();
const academicStore = useAcademicStore();
const authStore = useAuthStore();
const modalStore = useModalStore();

const searchQuery = ref('');
const selectedGrade = ref('');
const selectedSection = ref('');
const selectedStatus = ref('');

const showDetailModal = ref(false);
const showImportModal = ref(false);
const showPromotionModal = ref(false);

const csvFileInput = ref(null);
const importing = ref(false);
const promoting = ref(false);
const importResultData = ref(null);

const importForm = ref({
  academic_year_id: '',
  section_id: '',
  default_password: '',
});

const promotionForm = ref({
  source_section_id: '',
  target_academic_year_id: '',
  target_section_id: '',
  action: 'promote',
});

const openAcademicYears = computed(() => {
  return academicStore.academicYears.filter((y) => !y.is_closed);
});

const filteredSectionsForImport = computed(() => {
  if (!importForm.value.academic_year_id) return academicStore.sections;
  return academicStore.sections.filter((s) => Number(s.academic_year_id) === Number(importForm.value.academic_year_id));
});

const filteredSectionsForPromotion = computed(() => {
  if (!promotionForm.value.target_academic_year_id) return academicStore.sections;
  return academicStore.sections.filter((s) => Number(s.academic_year_id) === Number(promotionForm.value.target_academic_year_id));
});

onMounted(async () => {
  await Promise.all([
    studentsStore.fetchStudents(1),
    academicStore.fetchAll(),
  ]);
});

function handleSearch() {
  studentsStore.setFilter('search', searchQuery.value);
}

function handleGradeChange() {
  studentsStore.setFilter('grade_level_id', selectedGrade.value);
}

function handleSectionChange() {
  studentsStore.setFilter('section_id', selectedSection.value);
}

function handleStatusChange() {
  studentsStore.setFilter('status', selectedStatus.value);
}

function resetAllFilters() {
  searchQuery.value = '';
  selectedGrade.value = '';
  selectedSection.value = '';
  selectedStatus.value = '';
  studentsStore.resetFilters();
}

async function viewStudentHistory(studentId) {
  await studentsStore.fetchStudent(studentId);
  showDetailModal.value = true;
}

async function handleDeleteStudent(studentId) {
  const confirmed = await modalStore.confirm({
    title: 'Remove Student',
    message: 'Are you sure you want to remove this student? All their enrollments and academic records will be affected.',
    confirmText: 'Yes, Remove',
    destructive: true,
  });
  if (confirmed) {
    await studentsStore.deleteStudent(studentId);
    modalStore.toast('Student removed.', 'info');
  }
}

function openImportModal() {
  importResultData.value = null;
  const activeYear = academicStore.activeYear;
  if (activeYear) {
    importForm.value.academic_year_id = activeYear.id;
  }
  showImportModal.value = true;
}

function openPromotionModal() {
  const activeYear = academicStore.activeYear;
  if (activeYear) {
    promotionForm.value.target_academic_year_id = activeYear.id;
  }
  showPromotionModal.value = true;
}

async function executeImport() {
  const file = csvFileInput.value?.files?.[0];
  if (!file) {
    modalStore.alert('Please select a CSV file to import.', { type: 'warning' });
    return;
  }
  if (!importForm.value.academic_year_id || !importForm.value.section_id) {
    modalStore.alert('Please choose an academic year and section.', { type: 'warning' });
    return;
  }

  const formData = new FormData();
  formData.append('file', file);
  formData.append('academic_year_id', importForm.value.academic_year_id);
  formData.append('section_id', importForm.value.section_id);
  if (importForm.value.default_password) {
    formData.append('default_password', importForm.value.default_password);
  }

  importing.value = true;
  try {
    const res = await studentsStore.importCsv(formData);
    importResultData.value = res;
    modalStore.toast('Student roster imported successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Import failed.', { type: 'error' });
  } finally {
    importing.value = false;
  }
}

function finishImport() {
  showImportModal.value = false;
  importResultData.value = null;
}

async function executePromotion() {
  if (!promotionForm.value.source_section_id || !promotionForm.value.target_academic_year_id) {
    modalStore.alert('Source section and target academic year are required.', { type: 'warning' });
    return;
  }
  if (promotionForm.value.action !== 'graduate' && !promotionForm.value.target_section_id) {
    modalStore.alert('Please select a target section.', { type: 'warning' });
    return;
  }

  promoting.value = true;
  try {
    const res = await studentsStore.promoteRoster(promotionForm.value);
    modalStore.alert(`Successfully processed promotion for ${res.processed_count} students!`, { type: 'success', title: 'Promotion Complete' });
    showPromotionModal.value = false;
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Promotion failed.', { type: 'error' });
  } finally {
    promoting.value = false;
  }
}
</script>
