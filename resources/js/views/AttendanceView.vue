<template>
  <div class="space-y-8">
    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Daily Attendance</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            Grade &amp; Section Homeroom
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Daily section-based attendance tracking, non-school day calendar exclusions, and attendance analytics.
        </p>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-2 bg-slate-900 border border-slate-800 p-1 rounded-xl">
        <button
          @click="activeTab = 'roll-call'"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'roll-call' 
            ? 'bg-indigo-600 text-white shadow-sm' 
            : 'text-slate-400 hover:text-white'"
        >
          📋 Daily Roll Call
        </button>
        <button
          @click="switchToTrends"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'trends' 
            ? 'bg-indigo-600 text-white shadow-sm' 
            : 'text-slate-400 hover:text-white'"
        >
          📈 Trends &amp; Analytics
        </button>
        <button
          @click="switchToCalendar"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'calendar' 
            ? 'bg-indigo-600 text-white shadow-sm' 
            : 'text-slate-400 hover:text-white'"
        >
          📅 School Calendar
        </button>
      </div>
    </div>

    <!-- Alert / Message Banner -->
    <div v-if="attendanceStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
      <span>{{ attendanceStore.error }}</span>
      <button @click="attendanceStore.clearMessages" class="text-rose-400 hover:text-rose-200">✕</button>
    </div>
    <div v-if="attendanceStore.successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
      <span>{{ attendanceStore.successMessage }}</span>
      <button @click="attendanceStore.clearMessages" class="text-emerald-400 hover:text-emerald-200">✕</button>
    </div>

    <!-- TAB 1: DAILY ROLL CALL (UNDER 5 CLICKS WORKFLOW) -->
    <div v-if="activeTab === 'roll-call'" class="space-y-6">
      <!-- Controls Toolbar: Section & Date Selection -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
          <!-- Section Selector -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Section / Homeroom</label>
            <select
              v-model="selectedSectionId"
              @change="loadSectionAttendance"
              class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500 min-w-48"
            >
              <option v-for="sec in attendanceStore.sections" :key="sec.id" :value="sec.id">
                {{ sec.name }} ({{ sec.grade_level?.name || 'Grade' }})
              </option>
            </select>
          </div>

          <!-- Date Picker -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Attendance Date</label>
            <input
              v-model="selectedDate"
              @change="loadSectionAttendance"
              type="date"
              class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono"
            />
          </div>

          <!-- School Day Badge -->
          <div class="pt-4">
            <span 
              class="px-2.5 py-1 rounded-lg text-xs font-semibold border flex items-center gap-1.5"
              :class="attendanceStore.isSchoolDay 
                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' 
                : 'bg-amber-500/10 text-amber-400 border-amber-500/20'"
            >
              <span>{{ attendanceStore.isSchoolDay ? '✓ Official School Day' : '⚠ Non-School Day (Excluded from Rate)' }}</span>
            </span>
          </div>
        </div>

        <!-- Quick 1-Click Action Buttons -->
        <div class="flex items-center gap-2 pt-2 md:pt-4">
          <!-- Fast "Mark All Present" (Click 1) -->
          <button
            @click="markAllAs('present')"
            type="button"
            class="px-3.5 py-1.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600 hover:text-white rounded-lg text-xs font-semibold transition flex items-center gap-1"
          >
            <span>⚡ Mark All Present</span>
          </button>

          <!-- Clear / Reset -->
          <button
            @click="clearAllDrafts"
            type="button"
            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-semibold transition"
          >
            Reset
          </button>

          <!-- Primary Save Action Button (Click 2) -->
          <button
            @click="saveAttendance"
            :disabled="attendanceStore.actionLoading"
            type="button"
            class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition flex items-center gap-1.5"
          >
            <span>💾 {{ attendanceStore.actionLoading ? 'Saving...' : 'Save Attendance' }}</span>
          </button>
        </div>
      </div>

      <!-- Quick Metrics Summary Banner -->
      <div v-if="attendanceStore.currentStats" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center">
          <span class="text-[11px] text-slate-400">Total Enrolled</span>
          <div class="text-lg font-bold text-white mt-0.5">{{ attendanceStore.currentStats.total }}</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center">
          <span class="text-[11px] text-emerald-400">Present</span>
          <div class="text-lg font-bold text-emerald-400 mt-0.5">{{ attendanceStore.currentStats.present }}</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center">
          <span class="text-[11px] text-amber-400">Late / Tardy</span>
          <div class="text-lg font-bold text-amber-400 mt-0.5">{{ attendanceStore.currentStats.late }}</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center">
          <span class="text-[11px] text-rose-400">Absent</span>
          <div class="text-lg font-bold text-rose-400 mt-0.5">{{ attendanceStore.currentStats.absent }}</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center">
          <span class="text-[11px] text-blue-400">Excused</span>
          <div class="text-lg font-bold text-blue-400 mt-0.5">{{ attendanceStore.currentStats.excused }}</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center">
          <span class="text-[11px] text-indigo-400">Attendance Rate</span>
          <div class="text-lg font-bold text-white mt-0.5">
            {{ attendanceStore.currentStats.attendance_percentage !== null ? attendanceStore.currentStats.attendance_percentage + '%' : 'N/A' }}
          </div>
        </div>
      </div>

      <!-- Roll Call Roster Table -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-white uppercase tracking-wider">Student Roster</span>
            <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300">
              {{ localRoster.length }} students
            </span>
          </div>
          <span class="text-xs text-slate-400">
            Click status pills to adjust exceptions, then click Save
          </span>
        </div>

        <div v-if="attendanceStore.loading" class="p-12 text-center text-slate-500 text-xs">
          Loading section roster...
        </div>

        <div v-else-if="localRoster.length === 0" class="p-12 text-center text-slate-500 text-xs">
          No students currently enrolled in this section.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
              <tr>
                <th class="p-3.5">Student</th>
                <th class="p-3.5">Admission #</th>
                <th class="p-3.5">Attendance Status (1-Click Toggle)</th>
                <th class="p-3.5">Remarks / Reason</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr 
                v-for="row in localRoster" 
                :key="row.student_id"
                class="hover:bg-slate-950/40 transition"
              >
                <!-- Student Name & Avatar -->
                <td class="p-3.5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-800 text-slate-200 font-bold flex items-center justify-center text-xs">
                      {{ getInitials(row.name) }}
                    </div>
                    <div>
                      <div class="font-bold text-white text-xs">{{ row.name }}</div>
                      <div v-if="row.marked_by" class="text-[10px] text-slate-500">
                        Marked by {{ row.marked_by }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Admission Number -->
                <td class="p-3.5 font-mono text-slate-400 text-xs">
                  {{ row.admission_number }}
                </td>

                <!-- Status Buttons (1 Click per student to toggle) -->
                <td class="p-3.5">
                  <div class="flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="row.status = 'present'"
                      class="px-2.5 py-1 rounded text-xs font-semibold transition"
                      :class="row.status === 'present'
                        ? 'bg-emerald-600 text-white shadow-sm ring-1 ring-emerald-400'
                        : 'bg-slate-950 text-slate-400 hover:text-emerald-300 border border-slate-800'"
                    >
                      Present
                    </button>
                    <button
                      type="button"
                      @click="row.status = 'late'"
                      class="px-2.5 py-1 rounded text-xs font-semibold transition"
                      :class="row.status === 'late'
                        ? 'bg-amber-600 text-white shadow-sm ring-1 ring-amber-400'
                        : 'bg-slate-950 text-slate-400 hover:text-amber-300 border border-slate-800'"
                    >
                      Late
                    </button>
                    <button
                      type="button"
                      @click="row.status = 'absent'"
                      class="px-2.5 py-1 rounded text-xs font-semibold transition"
                      :class="row.status === 'absent'
                        ? 'bg-rose-600 text-white shadow-sm ring-1 ring-rose-400'
                        : 'bg-slate-950 text-slate-400 hover:text-rose-300 border border-slate-800'"
                    >
                      Absent
                    </button>
                    <button
                      type="button"
                      @click="row.status = 'excused'"
                      class="px-2.5 py-1 rounded text-xs font-semibold transition"
                      :class="row.status === 'excused'
                        ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-400'
                        : 'bg-slate-950 text-slate-400 hover:text-blue-300 border border-slate-800'"
                    >
                      Excused
                    </button>
                  </div>
                </td>

                <!-- Remarks / Notes Input -->
                <td class="p-3.5">
                  <input
                    v-model="row.remarks"
                    type="text"
                    placeholder="Optional note (e.g. flu, bus delay)"
                    class="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: TRENDS & ANALYTICS -->
    <div v-if="activeTab === 'trends'" class="space-y-6">
      <div v-if="attendanceStore.sectionSummary" class="space-y-6">
        <!-- Summary Header Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 class="text-base font-bold text-white">
                {{ attendanceStore.sectionSummary.section.name }} — Attendance Trends
              </h3>
              <p class="text-xs text-slate-400 mt-0.5">
                School Days Evaluated: <strong class="text-white">{{ attendanceStore.sectionSummary.period.school_days_in_period }}</strong> (excluding weekends &amp; holidays)
              </p>
            </div>
            <div class="flex items-center gap-3">
              <div class="text-right">
                <span class="text-[11px] text-slate-400">Average Rate</span>
                <div class="text-2xl font-black text-emerald-400">
                  {{ attendanceStore.sectionSummary.overall_summary.average_attendance_percentage }}%
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Daily Trends Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
          <div class="p-4 border-b border-slate-800">
            <h4 class="text-xs font-bold text-white uppercase tracking-wider">Daily Attendance History</h4>
          </div>
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 border-b border-slate-800">
              <tr>
                <th class="p-3">Date</th>
                <th class="p-3">Present</th>
                <th class="p-3">Late</th>
                <th class="p-3">Absent</th>
                <th class="p-3">Excused</th>
                <th class="p-3 text-right">Attendance %</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="attendanceStore.sectionSummary.daily_trends.length === 0">
                <td colspan="6" class="p-6 text-center text-slate-500">No recorded dates in this period.</td>
              </tr>
              <tr v-for="d in attendanceStore.sectionSummary.daily_trends" :key="d.date" class="hover:bg-slate-950/40">
                <td class="p-3 font-mono font-medium text-white">{{ d.date }}</td>
                <td class="p-3 text-emerald-400 font-semibold">{{ d.present }}</td>
                <td class="p-3 text-amber-400 font-semibold">{{ d.late }}</td>
                <td class="p-3 text-rose-400 font-semibold">{{ d.absent }}</td>
                <td class="p-3 text-blue-400">{{ d.excused }}</td>
                <td class="p-3 text-right font-bold" :class="d.attendance_percentage >= 90 ? 'text-emerald-400' : 'text-amber-400'">
                  {{ d.attendance_percentage }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: SCHOOL CALENDAR & HOLIDAYS -->
    <div v-if="activeTab === 'calendar'" class="space-y-6">
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
          <div>
            <h3 class="text-base font-bold text-white">School Calendar &amp; Non-School Days</h3>
            <p class="text-xs text-slate-400 mt-0.5">
              Holidays and non-school days defined here are automatically excluded from the attendance rate denominator.
            </p>
          </div>
          <button
            v-if="authStore.isSchoolAdmin"
            @click="showCalendarModal = true"
            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition"
          >
            + Add Holiday / Exception
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 border-b border-slate-800">
              <tr>
                <th class="p-3">Date</th>
                <th class="p-3">Type</th>
                <th class="p-3">School Day Status</th>
                <th class="p-3">Description</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="attendanceStore.calendarEvents.length === 0">
                <td colspan="4" class="p-6 text-center text-slate-500">No explicit calendar overrides. Default: Monday-Friday are school days.</td>
              </tr>
              <tr v-for="c in attendanceStore.calendarEvents" :key="c.id" class="hover:bg-slate-950/40">
                <td class="p-3 font-mono font-medium text-white">{{ c.date }}</td>
                <td class="p-3 capitalize">{{ c.day_type }}</td>
                <td class="p-3">
                  <span 
                    class="px-2 py-0.5 rounded text-[10px] font-semibold"
                    :class="c.is_school_day ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'"
                  >
                    {{ c.is_school_day ? 'School Day' : 'Non-School Day (Excluded)' }}
                  </span>
                </td>
                <td class="p-3 text-slate-400">{{ c.description || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD CALENDAR DAY OVERRIDE -->
    <div v-if="showCalendarModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>Add Calendar Day Exception</span>
          <button @click="showCalendarModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>

        <form @submit.prevent="submitCalendarDay" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Date</label>
            <input 
              v-model="calendarForm.date" 
              type="date" 
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Day Type</label>
            <select 
              v-model="calendarForm.day_type"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"
            >
              <option value="holiday">Holiday (School Closed)</option>
              <option value="weekend">Weekend</option>
              <option value="staff_only">Staff Development (No Students)</option>
              <option value="special_school_day">Makeup / Saturday School Day</option>
            </select>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input 
              v-model="calendarForm.is_school_day" 
              type="checkbox" 
              id="is_sch_day"
              class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-0"
            />
            <label for="is_sch_day" class="text-xs text-slate-300">Is this an active student school day?</label>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Description / Reason</label>
            <input 
              v-model="calendarForm.description" 
              type="text" 
              placeholder="e.g. Thanksgiving Break"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="flex justify-end gap-2 pt-3">
            <button 
              type="button" 
              @click="showCalendarModal = false" 
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="attendanceStore.actionLoading"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white rounded-lg shadow-sm"
            >
              Save Day
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useAttendanceStore } from '../stores/attendance';

const authStore = useAuthStore();
const attendanceStore = useAttendanceStore();

const activeTab = ref('roll-call');
const selectedSectionId = ref(null);
const selectedDate = ref(new Date().toISOString().split('T')[0]);
const localRoster = ref([]);
const showCalendarModal = ref(false);

const calendarForm = ref({
    date: new Date().toISOString().split('T')[0],
    day_type: 'holiday',
    is_school_day: false,
    description: '',
});

function getInitials(name) {
    if (!name) return 'ST';
    return name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

async function loadSectionAttendance() {
    if (!selectedSectionId.value) return;
    try {
        const data = await attendanceStore.fetchSectionDailyAttendance(selectedSectionId.value, selectedDate.value);
        if (data?.roster) {
            // Clone into editable local roster
            localRoster.value = data.roster.map(r => ({
                student_id: r.student_id,
                name: r.name,
                admission_number: r.admission_number,
                status: r.status || 'present',
                remarks: r.remarks || '',
                marked_by: r.marked_by,
            }));
        }
    } catch (e) {
        // Handled in store
    }
}

function markAllAs(status) {
    localRoster.value.forEach(row => {
        row.status = status;
    });
}

function clearAllDrafts() {
    loadSectionAttendance();
}

async function saveAttendance() {
    if (!selectedSectionId.value) return;

    const records = localRoster.value.map(r => ({
        student_id: r.student_id,
        status: r.status,
        remarks: r.remarks || null,
    }));

    try {
        await attendanceStore.markDailyAttendance(selectedSectionId.value, {
            date: selectedDate.value,
            records,
        });
    } catch (e) {
        // Handled in store
    }
}

async function switchToTrends() {
    activeTab.value = 'trends';
    if (selectedSectionId.value) {
        await attendanceStore.fetchSectionSummary(selectedSectionId.value);
    }
}

async function switchToCalendar() {
    activeTab.value = 'calendar';
    await attendanceStore.fetchCalendar();
}

async function submitCalendarDay() {
    try {
        await attendanceStore.storeCalendarDay(calendarForm.value);
        showCalendarModal.value = false;
    } catch (e) {
        // Handled in store
    }
}

onMounted(async () => {
    const sections = await attendanceStore.fetchSections();
    if (sections.length > 0) {
        selectedSectionId.value = sections[0].id;
        await loadSectionAttendance();
    }
});
</script>
