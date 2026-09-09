<template>
  <div class="space-y-8">
    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Staff &amp; Teacher Management</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">
            Faculty &amp; Teaching Assignments
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Manage teaching and administrative staff, homeroom section leadership, and subject grading access.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <button 
          @click="openAssignmentModal"
          v-if="authStore.isSchoolAdmin"
          class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
        >
          <span>🎯 Assign to Sections</span>
        </button>

        <button 
          @click="openAddStaffModal"
          v-if="authStore.isSchoolAdmin"
          class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-lg border border-slate-700 transition flex items-center gap-1.5"
        >
          <span>+ Add Staff Member</span>
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
            placeholder="Search name, staff #, email..." 
            class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
          />
        </div>

        <!-- Department Filter -->
        <select 
          v-model="selectedDepartment"
          @change="handleDepartmentChange"
          class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option value="">All Departments</option>
          <option value="Mathematics">Mathematics</option>
          <option value="Sciences">Sciences</option>
          <option value="Humanities">Humanities</option>
          <option value="Languages">Languages</option>
          <option value="Administration">Administration</option>
        </select>

        <!-- Status Filter -->
        <select 
          v-model="selectedStatus"
          @change="handleStatusChange"
          class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="on_leave">On Leave</option>
          <option value="resigned">Resigned</option>
          <option value="terminated">Terminated</option>
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
          {{ staffStore.pagination.total }} staff members
        </span>
      </div>
    </div>

    <!-- Staff Directory Table -->
    <div class="bg-slate-900/60 border border-slate-800 rounded-xl overflow-hidden">
      <div v-if="staffStore.loading" class="p-8 text-center text-slate-400 text-xs font-mono">
        Loading staff directory...
      </div>

      <div v-else-if="staffStore.staffList.length === 0" class="p-12 text-center space-y-3">
        <div class="text-3xl">👨‍🏫</div>
        <div class="text-base font-semibold text-white">No staff members found</div>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          Add faculty and staff members or adjust your search filters.
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-950/80 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
            <tr>
              <th class="px-5 py-3">Staff Member</th>
              <th class="px-5 py-3">Staff #</th>
              <th class="px-5 py-3">Role &amp; Department</th>
              <th class="px-5 py-3">Homeroom Section</th>
              <th class="px-5 py-3">Subjects Taught</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr v-for="staff in staffStore.staffList" :key="staff.id" class="hover:bg-slate-800/30 transition">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-300 font-bold flex items-center justify-center border border-blue-500/30 text-xs">
                    {{ staff.user?.name ? staff.user.name.charAt(0) : 'T' }}
                  </div>
                  <div>
                    <div class="font-semibold text-white">{{ staff.user?.name || 'Unknown' }}</div>
                    <div class="text-slate-400 text-[11px]">{{ staff.user?.email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 font-mono text-slate-300">
                {{ staff.staff_number }}
              </td>
              <td class="px-5 py-3.5">
                <div class="text-white font-medium">{{ staff.role_title }}</div>
                <div class="text-slate-400 text-[11px]">{{ staff.department || 'General Faculty' }}</div>
              </td>
              <td class="px-5 py-3.5">
                <span v-if="staff.homeroom_sections && staff.homeroom_sections.length > 0" class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-medium">
                  {{ staff.homeroom_sections[0].name }}
                </span>
                <span v-else class="text-slate-500 italic">None</span>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex flex-wrap gap-1">
                  <span 
                    v-for="c in staff.courses" 
                    :key="c.id"
                    class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300 text-[10px]"
                  >
                    {{ c.name }}
                  </span>
                  <span v-if="!staff.courses || staff.courses.length === 0" class="text-slate-500 italic text-[11px]">
                    No subjects linked
                  </span>
                </div>
              </td>
              <td class="px-5 py-3.5">
                <span 
                  class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase"
                  :class="{
                    'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': staff.status === 'active',
                    'bg-amber-500/20 text-amber-300 border border-amber-500/30': staff.status === 'on_leave',
                    'bg-slate-500/20 text-slate-300 border border-slate-500/30': staff.status === 'resigned',
                    'bg-rose-500/20 text-rose-300 border border-rose-500/30': staff.status === 'terminated',
                  }"
                >
                  {{ staff.status }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right space-x-2">
                <button 
                  @click="viewStaffDetail(staff.id)"
                  class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded border border-slate-700 transition"
                >
                  Details
                </button>
                <button 
                  v-if="authStore.isSchoolAdmin"
                  @click="handleDeleteStaff(staff.id)"
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
      <div v-if="staffStore.pagination.last_page > 1" class="px-5 py-3 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-400">
          Page {{ staffStore.pagination.current_page }} of {{ staffStore.pagination.last_page }}
        </span>
        <div class="space-x-1">
          <button 
            :disabled="staffStore.pagination.current_page === 1"
            @click="staffStore.fetchStaff(staffStore.pagination.current_page - 1)"
            class="px-2.5 py-1 bg-slate-800 disabled:opacity-40 text-slate-300 rounded hover:bg-slate-700"
          >
            Prev
          </button>
          <button 
            :disabled="staffStore.pagination.current_page === staffStore.pagination.last_page"
            @click="staffStore.fetchStaff(staffStore.pagination.current_page + 1)"
            class="px-2.5 py-1 bg-slate-800 disabled:opacity-40 text-slate-300 rounded hover:bg-slate-700"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Staff Detail Modal -->
    <div v-if="showDetailModal && staffStore.selectedStaff" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-2xl w-full p-6 space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
          <div>
            <h2 class="text-lg font-bold text-white">{{ staffStore.selectedStaff.user?.name }}</h2>
            <div class="text-xs text-slate-400 font-mono">{{ staffStore.selectedStaff.role_title }} &bull; {{ staffStore.selectedStaff.staff_number }}</div>
          </div>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <!-- Profile Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-xs">
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Email</span>
            <span class="text-white font-medium">{{ staffStore.selectedStaff.user?.email }}</span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Department</span>
            <span class="text-white font-medium">{{ staffStore.selectedStaff.department || 'N/A' }}</span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Hire Date</span>
            <span class="text-white font-medium">{{ staffStore.selectedStaff.hire_date || 'N/A' }}</span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80 col-span-2">
            <span class="text-slate-400 block mb-1">Qualifications</span>
            <span class="text-white font-medium">{{ staffStore.selectedStaff.qualification || 'Standard Teaching Credentials' }}</span>
          </div>
          <div class="bg-slate-950 p-3 rounded-lg border border-slate-800/80">
            <span class="text-slate-400 block mb-1">Contact Phone</span>
            <span class="text-white font-medium">{{ staffStore.selectedStaff.phone || 'N/A' }}</span>
          </div>
        </div>

        <!-- Section Assignments -->
        <div class="space-y-4">
          <!-- Homeroom Sections -->
          <div>
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-2 flex items-center gap-1.5">
              <span>🏫 Homeroom Leadership</span>
              <span class="text-slate-400 font-normal">({{ staffStore.selectedStaff.homeroom_sections?.length || 0 }})</span>
            </h3>
            <div v-if="!staffStore.selectedStaff.homeroom_sections || staffStore.selectedStaff.homeroom_sections.length === 0" class="text-xs text-slate-500 italic p-3 bg-slate-950 rounded-lg border border-slate-800">
              Not assigned as homeroom teacher to any section.
            </div>
            <div v-else class="space-y-2">
              <div v-for="sec in staffStore.selectedStaff.homeroom_sections" :key="sec.id" class="p-3 bg-slate-950 rounded-lg border border-slate-800 flex items-center justify-between text-xs">
                <div>
                  <div class="font-bold text-white">{{ sec.name }}</div>
                  <div class="text-[11px] text-slate-400">{{ sec.academic_year?.name }} &bull; Full attendance &amp; grading oversight</div>
                </div>
                <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono text-[10px]">Homeroom Teacher</span>
              </div>
            </div>
          </div>

          <!-- Subject Teaching Assignments -->
          <div>
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-2 flex items-center gap-1.5">
              <span>📚 Subject Teaching &amp; Grading Permissions</span>
              <span class="text-slate-400 font-normal">({{ staffStore.selectedStaff.section_subject_assignments?.length || 0 }})</span>
            </h3>
            <div v-if="!staffStore.selectedStaff.section_subject_assignments || staffStore.selectedStaff.section_subject_assignments.length === 0" class="text-xs text-slate-500 italic p-3 bg-slate-950 rounded-lg border border-slate-800">
              No specific section-subject teaching assignments.
            </div>
            <div v-else class="space-y-2">
              <div v-for="assign in staffStore.selectedStaff.section_subject_assignments" :key="assign.id" class="p-3 bg-slate-950 rounded-lg border border-slate-800 flex items-center justify-between text-xs">
                <div>
                  <div class="font-bold text-white">{{ assign.course?.name }} ({{ assign.course?.code }})</div>
                  <div class="text-[11px] text-slate-400">Section: {{ assign.section?.name }} &bull; {{ assign.section?.academic_year?.name }}</div>
                </div>
                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono text-[10px]">Grading Authorized</span>
              </div>
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

    <!-- Add Staff Member Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h2 class="text-base font-bold text-white">Add Faculty / Staff Member</h2>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Full Name *</label>
            <input v-model="newStaff.name" type="text" placeholder="e.g. Eleanor Vance" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white" />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Email Address *</label>
            <input v-model="newStaff.email" type="email" placeholder="e.g. e.vance@school.edu" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Role Title *</label>
              <input v-model="newStaff.role_title" type="text" placeholder="e.g. Biology Teacher" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white" />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Department</label>
              <select v-model="newStaff.department" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
                <option value="">Select Department</option>
                <option value="Sciences">Sciences</option>
                <option value="Mathematics">Mathematics</option>
                <option value="Humanities">Humanities</option>
                <option value="Languages">Languages</option>
                <option value="Administration">Administration</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Phone Number</label>
              <input v-model="newStaff.phone" type="text" placeholder="+1 (555) 000-0000" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white" />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Hire Date</label>
              <input v-model="newStaff.hire_date" type="date" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white" />
            </div>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Subjects Qualified to Teach</label>
            <div class="grid grid-cols-2 gap-1.5 max-h-36 overflow-y-auto p-2 bg-slate-950 rounded border border-slate-800">
              <label v-for="c in coursesList" :key="c.id" class="flex items-center gap-2 text-slate-300 text-[11px] cursor-pointer">
                <input type="checkbox" :value="c.id" v-model="newStaff.course_ids" class="rounded border-slate-700" />
                <span>{{ c.name }} ({{ c.code }})</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
            <button @click="showAddModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg">Cancel</button>
            <button @click="handleCreateStaff" :disabled="saving" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save Staff Member' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Section Teacher Assignment Modal -->
    <div v-if="showAssignmentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h2 class="text-base font-bold text-white">Section Teacher Assignments</h2>
          <button @click="showAssignmentModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <div class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Target Section *</label>
            <select v-model="assignForm.section_id" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-white">
              <option value="" disabled>Select Section</option>
              <option v-for="s in academicStore.sections" :key="s.id" :value="s.id">
                {{ s.name }} ({{ s.academic_year?.name }})
              </option>
            </select>
          </div>

          <!-- Homeroom Teacher Assignment -->
          <div class="p-3.5 bg-slate-950 rounded-lg border border-slate-800 space-y-2">
            <div class="font-bold text-white">Assign Homeroom Teacher</div>
            <div class="text-[11px] text-slate-400">Homeroom teachers hold primary pastoral and grading oversight for this section.</div>
            <div class="flex gap-2">
              <select v-model="assignForm.homeroom_teacher_id" class="flex-1 bg-slate-900 border border-slate-700 rounded p-1.5 text-white text-xs">
                <option value="">Select Teacher</option>
                <option v-for="st in staffStore.staffList" :key="st.user_id" :value="st.user_id">
                  {{ st.user?.name }} ({{ st.role_title }})
                </option>
              </select>
              <button @click="executeAssignHomeroom" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-medium text-xs">
                Assign
              </button>
            </div>
          </div>

          <!-- Subject Teacher Assignment -->
          <div class="p-3.5 bg-slate-950 rounded-lg border border-slate-800 space-y-2">
            <div class="font-bold text-white">Assign Subject Teacher</div>
            <div class="text-[11px] text-slate-400">Grants permission to view roster and record grades for a specific course.</div>
            <div class="grid grid-cols-2 gap-2">
              <select v-model="assignForm.course_id" class="bg-slate-900 border border-slate-700 rounded p-1.5 text-white text-xs">
                <option value="">Select Subject</option>
                <option v-for="c in coursesList" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <select v-model="assignForm.staff_id" class="bg-slate-900 border border-slate-700 rounded p-1.5 text-white text-xs">
                <option value="">Select Staff</option>
                <option v-for="st in staffStore.staffList" :key="st.id" :value="st.id">{{ st.user?.name }}</option>
              </select>
            </div>
            <div class="flex justify-end pt-1">
              <button @click="executeAssignSubjectTeacher" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded font-medium text-xs">
                Add Subject Assignment
              </button>
            </div>
          </div>

          <div class="flex justify-end pt-3 border-t border-slate-800">
            <button @click="showAssignmentModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs rounded-lg">Done</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useStaffStore } from '../stores/staff';
import { useAcademicStore } from '../stores/academic';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';
import axios from 'axios';

const staffStore = useStaffStore();
const academicStore = useAcademicStore();
const authStore = useAuthStore();
const modalStore = useModalStore();

const searchQuery = ref('');
const selectedDepartment = ref('');
const selectedStatus = ref('');

const showDetailModal = ref(false);
const showAddModal = ref(false);
const showAssignmentModal = ref(false);
const saving = ref(false);

const coursesList = ref([]);

const newStaff = ref({
  name: '',
  email: '',
  phone: '',
  role_title: '',
  department: '',
  hire_date: '',
  course_ids: [],
});

const assignForm = ref({
  section_id: '',
  homeroom_teacher_id: '',
  course_id: '',
  staff_id: '',
});

onMounted(async () => {
  await Promise.all([
    staffStore.fetchStaff(1),
    academicStore.fetchAll(),
    fetchCourses(),
  ]);
});

async function fetchCourses() {
  try {
    const res = await axios.get('/courses');
    coursesList.value = res.data.data;
  } catch (err) {
    console.error('Failed to load courses', err);
  }
}

function handleSearch() {
  staffStore.setFilter('search', searchQuery.value);
}

function handleDepartmentChange() {
  staffStore.setFilter('department', selectedDepartment.value);
}

function handleStatusChange() {
  staffStore.setFilter('status', selectedStatus.value);
}

function resetAllFilters() {
  searchQuery.value = '';
  selectedDepartment.value = '';
  selectedStatus.value = '';
  staffStore.resetFilters();
}

async function viewStaffDetail(id) {
  await staffStore.fetchStaffMember(id);
  showDetailModal.value = true;
}

function openAddStaffModal() {
  newStaff.value = {
    name: '',
    email: '',
    phone: '',
    role_title: '',
    department: '',
    hire_date: '',
    course_ids: [],
  };
  showAddModal.value = true;
}

function openAssignmentModal() {
  assignForm.value = {
    section_id: academicStore.sections[0]?.id || '',
    homeroom_teacher_id: '',
    course_id: coursesList.value[0]?.id || '',
    staff_id: staffStore.staffList[0]?.id || '',
  };
  showAssignmentModal.value = true;
}

async function handleCreateStaff() {
  if (!newStaff.value.name || !newStaff.value.email || !newStaff.value.role_title) {
    modalStore.alert('Please fill out all required fields.', { type: 'warning' });
    return;
  }

  saving.value = true;
  try {
    await staffStore.createStaffMember(newStaff.value);
    showAddModal.value = false;
    modalStore.toast('Staff member created successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to create staff member.', { type: 'error' });
  } finally {
    saving.value = false;
  }
}

async function handleDeleteStaff(id) {
  const confirmed = await modalStore.confirm({
    title: 'Remove Staff Member',
    message: 'Are you sure you want to remove this staff member? This will remove all their section and subject assignments.',
    confirmText: 'Yes, Remove',
    destructive: true,
  });
  if (confirmed) {
    await staffStore.deleteStaffMember(id);
    modalStore.toast('Staff member removed.', 'info');
  }
}

async function executeAssignHomeroom() {
  if (!assignForm.value.section_id || !assignForm.value.homeroom_teacher_id) {
    modalStore.alert('Please select both a section and a teacher.', { type: 'warning' });
    return;
  }

  try {
    await staffStore.assignHomeroom(assignForm.value.section_id, assignForm.value.homeroom_teacher_id);
    modalStore.toast('Homeroom teacher assigned successfully!', 'success');
    await academicStore.fetchSections();
    await staffStore.fetchStaff();
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to assign homeroom teacher.', { type: 'error' });
  }
}

async function executeAssignSubjectTeacher() {
  if (!assignForm.value.section_id || !assignForm.value.course_id || !assignForm.value.staff_id) {
    modalStore.alert('Please select section, course, and staff member.', { type: 'warning' });
    return;
  }

  try {
    await staffStore.assignSubjectTeacher(assignForm.value.section_id, assignForm.value.course_id, assignForm.value.staff_id);
    modalStore.toast('Subject teacher assigned successfully!', 'success');
    await staffStore.fetchStaff();
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to assign subject teacher.', { type: 'error' });
  }
}
</script>
