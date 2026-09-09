<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Weekly Timetable &amp; Scheduling</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            Grade-Based Roster
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Weekly section timetables, automated conflict detection against teacher double-booking, and aggregated teacher schedules.
        </p>
      </div>

      <!-- Mode Switcher -->
      <div class="flex items-center gap-2 bg-slate-900 border border-slate-800 p-1 rounded-xl">
        <button
          @click="activeMode = 'section'"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeMode === 'section' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
        >
          🏫 Section Timetable
        </button>
        <button
          @click="switchToTeacherMode"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeMode === 'teacher' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
        >
          👩‍🏫 Teacher Schedule
        </button>
        <button
          @click="switchToStudentMode"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeMode === 'student' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
        >
          🎓 Student Schedule (Electives)
        </button>
      </div>
    </div>

    <!-- Alert / Conflict Banner -->
    <div v-if="timetableStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs space-y-1">
      <div class="flex items-center justify-between font-bold">
        <span>⚠️ Scheduling Conflict Detected</span>
        <button @click="timetableStore.clearMessages" class="text-rose-400 hover:text-rose-200">✕</button>
      </div>
      <p>{{ timetableStore.error }}</p>
      <div v-if="timetableStore.conflictDetails" class="pt-1 text-[11px] font-mono text-rose-200">
        Conflict Type: <span class="uppercase font-bold">{{ timetableStore.conflictDetails.conflict_type }}</span>
        <span v-if="timetableStore.conflictDetails.conflicting_section_name"> &bull; In: {{ timetableStore.conflictDetails.conflicting_section_name }}</span>
        <span v-if="timetableStore.conflictDetails.time_range"> &bull; Time: {{ timetableStore.conflictDetails.time_range }}</span>
      </div>
    </div>

    <div v-if="timetableStore.successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
      <span>{{ timetableStore.successMessage }}</span>
      <button @click="timetableStore.clearMessages" class="text-emerald-400 hover:text-emerald-200">✕</button>
    </div>

    <!-- MODE 1: SECTION TIMETABLE -->
    <div v-if="activeMode === 'section'" class="space-y-6">
      <!-- Selector & Stats Bar -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Select Section</label>
            <select
              v-model="selectedSectionId"
              @change="onSectionChange"
              class="w-64 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option v-for="sec in timetableStore.sections" :key="sec.id" :value="sec.id">
                {{ sec.name }} ({{ sec.grade_level?.name || 'Grade' }})
              </option>
            </select>
          </div>

          <div v-if="timetableStore.sectionTimetable" class="flex items-center gap-3 pt-2 sm:pt-4 text-xs text-slate-300">
            <span class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 font-mono">
              Homeroom: <strong>{{ timetableStore.sectionTimetable.section.homeroom_teacher || 'Not Assigned' }}</strong>
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 font-mono">
              Slots: <strong>{{ timetableStore.sectionTimetable.stats.total_slots }}</strong>
            </span>
          </div>
        </div>

        <button
          @click="openAddSlotModal()"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center gap-1.5 self-start md:self-auto"
        >
          <span>➕ Add Class Slot</span>
        </button>
      </div>

      <!-- Weekly Schedule Matrix Grid -->
      <div v-if="timetableStore.sectionTimetable" class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-950 text-slate-400 border-b border-slate-800 uppercase tracking-wider font-semibold">
                <th class="p-3 w-32 border-r border-slate-800 text-center">Period / Time</th>
                <th v-for="day in timetableStore.sectionTimetable.days" :key="day" class="p-3 text-center min-w-[150px] border-r border-slate-800 last:border-r-0">
                  {{ formatDay(day) }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-for="period in timetableStore.sectionTimetable.periods" :key="period.period_number">
                <!-- Period Info Cell -->
                <td class="p-3 bg-slate-950/40 border-r border-slate-800 text-center">
                  <div class="font-bold text-white text-sm">Period {{ period.period_number }}</div>
                  <div class="font-mono text-[10px] text-slate-400 mt-0.5">{{ period.times.start }} - {{ period.times.end }}</div>
                </td>

                <!-- Day Columns for this Period -->
                <td
                  v-for="day in timetableStore.sectionTimetable.days"
                  :key="day"
                  class="p-2.5 border-r border-slate-800 last:border-r-0 align-top hover:bg-slate-850/30 transition"
                >
                  <!-- If slot exists in this (day, period) -->
                  <div
                    v-if="timetableStore.sectionTimetable.grid[day]?.[period.period_number]"
                    class="p-2.5 rounded-xl border border-slate-700/60 bg-slate-950/80 space-y-1.5 relative group shadow-sm"
                  >
                    <div class="flex items-start justify-between gap-1">
                      <span class="font-bold text-white text-xs leading-tight">
                        {{ timetableStore.sectionTimetable.grid[day][period.period_number].subject?.name }}
                      </span>
                      <button
                        @click="deleteSlot(timetableStore.sectionTimetable.grid[day][period.period_number].id)"
                        class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-rose-400 text-xs transition"
                        title="Delete slot"
                      >
                        ✕
                      </button>
                    </div>

                    <div class="text-[11px] text-indigo-300 font-medium flex items-center gap-1">
                      <span>👤 {{ timetableStore.sectionTimetable.grid[day][period.period_number].teacher?.name || 'No Teacher' }}</span>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-1 border-t border-slate-800/80">
                      <span>📍 {{ timetableStore.sectionTimetable.grid[day][period.period_number].room || 'Room TBA' }}</span>
                      <span class="text-[9px] px-1 py-0.2 rounded bg-slate-800">{{ timetableStore.sectionTimetable.grid[day][period.period_number].subject?.code }}</span>
                    </div>
                  </div>

                  <!-- Empty Slot placeholder -->
                  <div
                    v-else
                    @click="openAddSlotModal(day, period.period_number)"
                    class="h-20 rounded-xl border border-dashed border-slate-800/80 hover:border-slate-700 flex items-center justify-center text-slate-600 hover:text-slate-400 cursor-pointer transition text-xs group"
                  >
                    <span class="group-hover:scale-110 transition">➕</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODE 2: TEACHER PERSONAL SCHEDULE (AGGREGATED ACROSS SECTIONS) -->
    <div v-if="activeMode === 'teacher'" class="space-y-6">
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Teacher</label>
            <select
              v-model="selectedTeacherId"
              @change="onTeacherChange"
              class="w-64 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option v-for="t in timetableStore.teachers" :key="t.id" :value="t.id">
                {{ t.name }}
              </option>
            </select>
          </div>

          <div v-if="timetableStore.teacherTimetable" class="flex flex-wrap items-center gap-2 pt-2 sm:pt-4 text-xs">
            <span class="text-slate-400">Assigned Sections:</span>
            <span
              v-for="sec in timetableStore.teacherTimetable.sections_taught"
              :key="sec.id"
              class="px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono text-[11px]"
            >
              {{ sec.name }}
            </span>
          </div>
        </div>

        <div v-if="timetableStore.teacherTimetable" class="text-xs text-slate-300 font-mono">
          Total Teaching Load: <strong>{{ timetableStore.teacherTimetable.stats.total_weekly_periods }} periods/week</strong>
        </div>
      </div>

      <!-- Teacher Aggregated Grid -->
      <div v-if="timetableStore.teacherTimetable" class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-950 text-slate-400 border-b border-slate-800 uppercase tracking-wider font-semibold">
                <th class="p-3 w-32 border-r border-slate-800 text-center">Period / Time</th>
                <th v-for="day in timetableStore.teacherTimetable.days" :key="day" class="p-3 text-center min-w-[150px] border-r border-slate-800 last:border-r-0">
                  {{ formatDay(day) }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-for="period in timetableStore.teacherTimetable.periods" :key="period.period_number">
                <td class="p-3 bg-slate-950/40 border-r border-slate-800 text-center">
                  <div class="font-bold text-white text-sm">Period {{ period.period_number }}</div>
                  <div class="font-mono text-[10px] text-slate-400 mt-0.5">{{ period.times.start }} - {{ period.times.end }}</div>
                </td>

                <td
                  v-for="day in timetableStore.teacherTimetable.days"
                  :key="day"
                  class="p-2.5 border-r border-slate-800 last:border-r-0 align-top"
                >
                  <!-- Aggregated Section Card -->
                  <div
                    v-if="timetableStore.teacherTimetable.grid[day]?.[period.period_number]"
                    class="p-2.5 rounded-xl border border-indigo-500/30 bg-indigo-950/20 space-y-1.5 shadow-sm"
                  >
                    <div class="flex items-center justify-between">
                      <span class="font-bold text-indigo-300 text-xs">
                        {{ timetableStore.teacherTimetable.grid[day][period.period_number].subject?.name }}
                      </span>
                    </div>

                    <div class="text-[11px] font-bold text-white flex items-center gap-1">
                      <span>🏫 {{ timetableStore.teacherTimetable.grid[day][period.period_number].section?.name }}</span>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-1 border-t border-slate-800/80">
                      <span>📍 {{ timetableStore.teacherTimetable.grid[day][period.period_number].room || 'Room TBA' }}</span>
                      <span class="text-[9px] px-1 py-0.2 rounded bg-slate-800">{{ timetableStore.teacherTimetable.grid[day][period.period_number].subject?.code }}</span>
                    </div>
                  </div>

                  <div v-else class="h-16 rounded-xl border border-dashed border-slate-850 flex items-center justify-center text-slate-700 text-[10px]">
                    Free Period
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODE 3: STUDENT TIMETABLE (ELECTIVES MERGED) -->
    <div v-if="activeMode === 'student'" class="space-y-6">
      <!-- Selector & Stats Bar -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <div>
            <label class="block text-[11px] font-mono uppercase text-slate-400 font-semibold mb-1">Select Student</label>
            <select
              v-model="selectedStudentId"
              @change="onStudentChange"
              class="bg-slate-800 border border-slate-700 text-white text-xs rounded-xl px-3 py-2 font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none min-w-[260px]"
            >
              <option v-for="std in timetableStore.students" :key="std.id" :value="std.id">
                {{ std.user?.name || std.admission_number }} ({{ std.admission_number }})
              </option>
            </select>
          </div>

          <div v-if="timetableStore.studentTimetable?.section" class="text-xs text-slate-400 border-l border-slate-800 pl-4">
            <div><span class="text-slate-300 font-medium">Section:</span> {{ timetableStore.studentTimetable.section.name }} &bull; {{ timetableStore.studentTimetable.section.grade_level }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Homeroom: {{ timetableStore.studentTimetable.section.homeroom_teacher || 'Unassigned' }}</div>
          </div>
        </div>

        <!-- Elective Stats Badge -->
        <div v-if="timetableStore.studentTimetable?.stats" class="flex items-center gap-3">
          <div class="px-3 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-center">
            <div class="text-[10px] font-mono text-slate-400 uppercase">Core Slots</div>
            <div class="text-base font-bold text-white">{{ timetableStore.studentTimetable.stats.core_slots_count }}</div>
          </div>
          <div class="px-3 py-2 rounded-xl bg-purple-950/40 border border-purple-500/40 text-center">
            <div class="text-[10px] font-mono text-purple-300 uppercase">Elective Slots</div>
            <div class="text-base font-bold text-purple-300">{{ timetableStore.studentTimetable.stats.elective_slots_count }}</div>
          </div>
          <div class="px-3 py-2 rounded-xl bg-indigo-950/40 border border-indigo-500/40 text-center">
            <div class="text-[10px] font-mono text-indigo-300 uppercase">Electives Chosen</div>
            <div class="text-base font-bold text-indigo-300">{{ timetableStore.studentTimetable.stats.enrolled_electives_count }}</div>
          </div>
        </div>
      </div>

      <!-- Weekly Matrix Grid -->
      <div v-if="timetableStore.studentTimetable" class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left border-collapse">
            <thead>
              <tr class="bg-slate-950 text-slate-400 border-b border-slate-800 uppercase tracking-wider font-semibold">
                <th class="p-3 w-32 border-r border-slate-800 text-center">Period / Time</th>
                <th v-for="day in timetableStore.studentTimetable.days" :key="day" class="p-3 text-center min-w-[150px] border-r border-slate-800 last:border-r-0">
                  {{ formatDay(day) }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-for="period in timetableStore.studentTimetable.periods" :key="period.period_number">
                <td class="p-3 bg-slate-950/40 border-r border-slate-800 text-center">
                  <div class="font-bold text-white text-sm">Period {{ period.period_number }}</div>
                  <div class="font-mono text-[10px] text-slate-400 mt-0.5">{{ period.times.start }} - {{ period.times.end }}</div>
                </td>

                <td
                  v-for="day in timetableStore.studentTimetable.days"
                  :key="day"
                  class="p-2.5 border-r border-slate-800 last:border-r-0 align-top"
                >
                  <div
                    v-if="timetableStore.studentTimetable.grid[day]?.[period.period_number]"
                    class="p-2.5 rounded-xl border space-y-1.5 shadow-sm"
                    :class="timetableStore.studentTimetable.grid[day][period.period_number].subject?.is_elective
                      ? 'border-purple-500/40 bg-purple-950/20'
                      : 'border-slate-700/60 bg-slate-800/50'"
                  >
                    <div class="flex items-center justify-between">
                      <span class="font-bold text-xs" :class="timetableStore.studentTimetable.grid[day][period.period_number].subject?.is_elective ? 'text-purple-300' : 'text-slate-200'">
                        {{ timetableStore.studentTimetable.grid[day][period.period_number].subject?.name }}
                      </span>
                      <span
                        v-if="timetableStore.studentTimetable.grid[day][period.period_number].subject?.is_elective"
                        class="text-[9px] px-1.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 font-semibold"
                      >
                        ELECTIVE
                      </span>
                    </div>

                    <div class="text-[11px] text-slate-300 flex items-center justify-between">
                      <span>👤 {{ timetableStore.studentTimetable.grid[day][period.period_number].teacher?.name || 'Teacher TBD' }}</span>
                      <span v-if="timetableStore.studentTimetable.grid[day][period.period_number].section_id !== timetableStore.studentTimetable.section?.id" class="text-[10px] text-amber-400 font-mono">
                        (Cross-Section)
                      </span>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-1 border-t border-slate-800/80">
                      <span>📍 {{ timetableStore.studentTimetable.grid[day][period.period_number].room || 'Room TBA' }}</span>
                      <span class="text-[9px] px-1 py-0.2 rounded bg-slate-800">{{ timetableStore.studentTimetable.grid[day][period.period_number].subject?.code }}</span>
                    </div>
                  </div>

                  <div v-else class="h-16 rounded-xl border border-dashed border-slate-850 flex items-center justify-center text-slate-700 text-[10px]">
                    Free Period
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD TIMETABLE SLOT -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <h3 class="text-base font-bold text-white">Schedule Class Slot</h3>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitAddSlot" class="space-y-4 text-xs">
          <!-- Day of week -->
          <div>
            <label class="block text-slate-400 font-semibold mb-1">Day of Week</label>
            <select
              v-model="slotForm.day_of_week"
              required
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white"
            >
              <option value="monday">Monday</option>
              <option value="tuesday">Tuesday</option>
              <option value="wednesday">Wednesday</option>
              <option value="thursday">Thursday</option>
              <option value="friday">Friday</option>
            </select>
          </div>

          <!-- Period number -->
          <div>
            <label class="block text-slate-400 font-semibold mb-1">Period Number (1 - 8)</label>
            <input
              v-model.number="slotForm.period_number"
              type="number"
              min="1"
              max="8"
              required
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white font-mono"
            />
          </div>

          <!-- Subject -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-slate-400 font-semibold">Subject / Course *</label>
              <router-link to="/courses" class="text-[11px] text-indigo-400 hover:text-indigo-300">
                + Manage Catalog
              </router-link>
            </div>
            <select
              v-model="slotForm.subject_id"
              required
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null" disabled>Select subject / course...</option>
              <option v-for="sub in timetableStore.subjects" :key="sub.id" :value="sub.id">
                {{ sub.name }} ({{ sub.code }}) {{ sub.is_elective ? '• Elective' : '' }}
              </option>
            </select>
            <p v-if="timetableStore.subjects.length === 0" class="text-[11px] text-amber-400 mt-1">
              No subjects or courses found. <router-link to="/courses" class="underline hover:text-amber-300">Add courses in catalog</router-link> to schedule here.
            </p>
          </div>

          <!-- Teacher -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-slate-400 font-semibold">Teacher</label>
              <router-link to="/staff?action=add-teacher" class="text-[11px] text-indigo-400 hover:text-indigo-300">
                + Add Teacher
              </router-link>
            </div>
            <select
              v-model="slotForm.teacher_id"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null">No Teacher Assigned (Self-Study / TBA)</option>
              <option v-for="t in timetableStore.teachers" :key="t.id" :value="t.id">
                {{ t.name }} &bull; {{ t.role_title || 'Faculty' }}
              </option>
            </select>
            <p v-if="timetableStore.teachers.length === 0" class="text-[11px] text-amber-400 mt-1">
              No teachers registered. <router-link to="/staff?action=add-teacher" class="underline hover:text-amber-300">Add a teacher</router-link>.
            </p>
          </div>

          <!-- Room -->
          <div>
            <label class="block text-slate-400 font-semibold mb-1">Room / Location (Optional)</label>
            <input
              v-model="slotForm.room"
              type="text"
              placeholder="e.g. Room 101, Lab A"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white"
            />
          </div>

          <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
            <button
              type="button"
              @click="showAddModal = false"
              class="px-4 py-2 text-slate-400 hover:text-white"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="timetableStore.actionLoading"
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold rounded-lg transition"
            >
              {{ timetableStore.actionLoading ? 'Verifying & Saving...' : 'Confirm & Schedule' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useTimetableStore } from '../stores/timetable';
import { useModalStore } from '../stores/modal';

const timetableStore = useTimetableStore();
const modalStore = useModalStore();

const activeMode = ref('section');
const selectedSectionId = ref(null);
const selectedTeacherId = ref(null);
const selectedStudentId = ref(null);

const showAddModal = ref(false);
const slotForm = ref({
    day_of_week: 'monday',
    period_number: 1,
    subject_id: null,
    teacher_id: null,
    room: '',
});

onMounted(async () => {
    await timetableStore.fetchSections();
    await timetableStore.fetchTeachers();

    if (timetableStore.sections.length > 0) {
        selectedSectionId.value = timetableStore.sections[0].id;
        const curSec = timetableStore.sections[0];
        await Promise.all([
            timetableStore.fetchSubjects(curSec.grade_level_id || null),
            timetableStore.fetchSectionTimetable(selectedSectionId.value),
        ]);
    } else {
        await timetableStore.fetchSubjects();
    }

    if (timetableStore.teachers.length > 0) {
        selectedTeacherId.value = timetableStore.teachers[0].id;
    }
});

function formatDay(day) {
    if (!day) return '';
    return day.charAt(0).toUpperCase() + day.slice(1);
}

async function onSectionChange() {
    if (selectedSectionId.value) {
        const curSec = timetableStore.sections.find(s => s.id === selectedSectionId.value);
        if (curSec?.grade_level_id) {
            await timetableStore.fetchSubjects(curSec.grade_level_id);
        } else {
            await timetableStore.fetchSubjects();
        }
        await timetableStore.fetchSectionTimetable(selectedSectionId.value);
    }
}

async function onTeacherChange() {
    if (selectedTeacherId.value) {
        await timetableStore.fetchTeacherTimetable(selectedTeacherId.value);
    }
}

async function switchToTeacherMode() {
    activeMode.value = 'teacher';
    if (selectedTeacherId.value) {
        await timetableStore.fetchTeacherTimetable(selectedTeacherId.value);
    }
}

async function onStudentChange() {
    if (selectedStudentId.value) {
        await timetableStore.fetchStudentTimetable(selectedStudentId.value);
    }
}

async function switchToStudentMode() {
    activeMode.value = 'student';
    if (!timetableStore.students.length) {
        await timetableStore.fetchStudents();
    }
    if (timetableStore.students.length && !selectedStudentId.value) {
        selectedStudentId.value = timetableStore.students[0].id;
    }
    if (selectedStudentId.value) {
        await timetableStore.fetchStudentTimetable(selectedStudentId.value);
    }
}

function openAddSlotModal(day = 'monday', period = 1) {
    const curSec = timetableStore.sections.find(s => s.id === selectedSectionId.value);
    if (curSec?.grade_level_id && timetableStore.subjects.length === 0) {
        timetableStore.fetchSubjects(curSec.grade_level_id);
    }
    slotForm.value = {
        day_of_week: day,
        period_number: period,
        subject_id: timetableStore.subjects[0]?.id || null,
        teacher_id: timetableStore.teachers[0]?.id || null,
        room: '',
    };
    timetableStore.clearMessages();
    showAddModal.value = true;
}

async function submitAddSlot() {
    if (!selectedSectionId.value) return;
    try {
        await timetableStore.createSlot(selectedSectionId.value, slotForm.value);
        showAddModal.value = false;
    } catch (e) {
        // error & conflict details set in store
    }
}

async function deleteSlot(slotId) {
    const confirmed = await modalStore.confirm({
        title: 'Remove Timetable Slot',
        message: 'Are you sure you want to remove this timetable slot from the schedule?',
        confirmText: 'Remove Slot',
        destructive: true,
    });
    if (!confirmed) return;
    await timetableStore.deleteSlot(slotId, selectedSectionId.value);
    modalStore.toast('Timetable slot removed.', 'info');
}
</script>
