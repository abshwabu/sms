<template>
  <div class="space-y-8">
    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Parent Portal &amp; Student Linking</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
            Family &amp; Guardian Accounts
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Unified multi-child switcher, academic progress tracking, and administrative parent-student guardian linking.
        </p>
      </div>

      <!-- Tab Navigation (If Admin, can switch between Portal View and Parent Directory) -->
      <div class="flex items-center gap-2 bg-slate-900 border border-slate-800 p-1 rounded-xl">
        <button
          @click="activeTab = 'portal'"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'portal' 
            ? 'bg-emerald-600 text-white shadow-sm' 
            : 'text-slate-400 hover:text-white'"
        >
          👨‍👧‍👦 Children Portal
        </button>
        <button
          v-if="authStore.isSchoolAdmin"
          @click="switchToDirectory"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'directory' 
            ? 'bg-emerald-600 text-white shadow-sm' 
            : 'text-slate-400 hover:text-white'"
        >
          📋 Manage Parents &amp; Links
        </button>
      </div>
    </div>

    <!-- Alert / Message Banner -->
    <div v-if="parentStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
      <span>{{ parentStore.error }}</span>
      <button @click="parentStore.clearMessages" class="text-rose-400 hover:text-rose-200">✕</button>
    </div>
    <div v-if="parentStore.successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
      <span>{{ parentStore.successMessage }}</span>
      <button @click="parentStore.clearMessages" class="text-emerald-400 hover:text-emerald-200">✕</button>
    </div>

    <!-- TAB 1: PARENT PORTAL (UNIFIED CHILD SWITCHER & DASHBOARD) -->
    <div v-if="activeTab === 'portal'" class="space-y-6">
      <!-- Child Switcher Card / Bar -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Linked Children</span>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
              {{ parentStore.children.length }} enrolled
            </span>
          </div>
          <span class="text-xs text-slate-500">
            Switch between children's dashboards without re-logging in
          </span>
        </div>

        <div v-if="parentStore.loading && parentStore.children.length === 0" class="py-8 text-center text-slate-500 text-sm">
          Loading linked children...
        </div>

        <div v-else-if="parentStore.children.length === 0" class="py-8 text-center bg-slate-950/60 rounded-xl border border-dashed border-slate-800">
          <div class="text-3xl mb-2">👨‍👩‍👧‍👦</div>
          <h3 class="text-sm font-semibold text-slate-300">No Linked Children Found</h3>
          <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
            You are currently logged in as {{ authStore.user?.email || 'a parent' }}, but no students are linked to this parent profile yet. Contact your school administrator to link your child's record.
          </p>
        </div>

        <!-- Unified Children Switcher Tabs -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <button
            v-for="child in parentStore.children"
            :key="child.id"
            @click="parentStore.selectChild(child)"
            class="flex items-center gap-3 p-3 rounded-xl border text-left transition relative overflow-hidden"
            :class="parentStore.activeChild?.id === child.id 
              ? 'bg-emerald-950/40 border-emerald-500/60 ring-1 ring-emerald-500/30' 
              : 'bg-slate-950/60 border-slate-800 hover:border-slate-700 hover:bg-slate-950'"
          >
            <!-- Avatar Initials -->
            <div 
              class="w-11 h-11 rounded-xl flex items-center justify-center font-bold text-sm text-white shadow-inner flex-shrink-0"
              :class="parentStore.activeChild?.id === child.id ? 'bg-emerald-600' : 'bg-slate-800'"
            >
              {{ getInitials(child.user?.name || child.admission_number) }}
            </div>

            <!-- Child Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-1">
                <h4 class="text-xs font-bold text-white truncate">
                  {{ child.user?.name }}
                </h4>
                <span 
                  v-if="child.pivot?.relationship"
                  class="text-[10px] px-1.5 py-0.5 rounded capitalize bg-slate-800 text-slate-300 border border-slate-700"
                >
                  {{ child.pivot.relationship }}
                </span>
              </div>
              <p class="text-[11px] font-mono text-slate-400 truncate mt-0.5">
                {{ child.admission_number }}
              </p>
              <div class="flex items-center gap-1.5 mt-1">
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 truncate">
                  {{ child.current_section?.grade_level?.name || 'Grade -' }} · {{ child.current_section?.name || 'No Section' }}
                </span>
                <span v-if="child.pivot?.is_primary_contact" class="text-[10px] px-1 py-0.2 rounded bg-amber-500/10 text-amber-400 font-semibold">
                  ★ Primary
                </span>
              </div>
            </div>

            <!-- Active Indicator Pill -->
            <div v-if="parentStore.activeChild?.id === child.id" class="absolute top-2 right-2">
              <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
              </span>
            </div>
          </button>
        </div>
      </div>

      <!-- Child Dashboard Content Area -->
      <div v-if="parentStore.dashboardLoading" class="p-12 text-center text-slate-500 text-sm">
        Loading student academic profile and enrollment data...
      </div>

      <div v-else-if="parentStore.childDashboard" class="space-y-6">
        <!-- Student Header Card -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-900 to-emerald-950/40 border border-slate-800 rounded-2xl p-6 shadow-sm">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-2xl bg-emerald-600 flex items-center justify-center font-black text-2xl text-white shadow-lg shadow-emerald-900/30">
                {{ getInitials(parentStore.childDashboard.student.user?.name) }}
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-xl font-bold text-white">
                    {{ parentStore.childDashboard.student.user?.name }}
                  </h2>
                  <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase font-mono tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    {{ parentStore.childDashboard.student.status }}
                  </span>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400 mt-1">
                  <span>Admission #: <strong class="font-mono text-slate-200">{{ parentStore.childDashboard.student.admission_number }}</strong></span>
                  <span>•</span>
                  <span>Gender: <strong class="capitalize text-slate-200">{{ parentStore.childDashboard.student.gender }}</strong></span>
                  <span>•</span>
                  <span>DOB: <strong class="text-slate-200">{{ parentStore.childDashboard.student.date_of_birth }}</strong></span>
                  <span>•</span>
                  <span>Relationship: <strong class="capitalize text-emerald-400">{{ parentStore.childDashboard.relationship }}</strong></span>
                </div>
              </div>
            </div>

            <!-- Quick Status Pill -->
            <div class="flex items-center gap-2 bg-slate-950/70 border border-slate-800 px-4 py-2 rounded-xl">
              <span class="text-xs text-slate-400">Enrolled School:</span>
              <span class="text-xs font-semibold text-slate-200">{{ authStore.schoolContext?.name || 'Greenwood High' }}</span>
            </div>
          </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
            <span class="text-xs text-slate-400">Current Homeroom / Section</span>
            <div class="text-base font-bold text-white mt-1 truncate">
              {{ parentStore.childDashboard.academic_summary.current_section || 'Unassigned' }}
            </div>
            <span class="text-[11px] text-emerald-400 mt-1 inline-block">
              {{ parentStore.childDashboard.academic_summary.grade_level || 'Grade Level' }}
            </span>
          </div>

          <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
            <span class="text-xs text-slate-400">Class / Homeroom Teacher</span>
            <div class="text-base font-bold text-white mt-1 truncate">
              {{ parentStore.childDashboard.academic_summary.homeroom_teacher || 'No Teacher Assigned' }}
            </div>
            <span class="text-[11px] text-slate-500 mt-1 inline-block">
              Daily Attendance &amp; Guidance
            </span>
          </div>

          <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
            <span class="text-xs text-slate-400">Current Academic Year</span>
            <div class="text-base font-bold text-white mt-1 font-mono truncate">
              {{ parentStore.childDashboard.academic_summary.academic_year || 'Active Year' }}
            </div>
            <span class="text-[11px] text-emerald-400 mt-1 inline-block">
              Active Session
            </span>
          </div>

          <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
            <span class="text-xs text-slate-400">Total Enrolled Years</span>
            <div class="text-base font-bold text-white mt-1">
              {{ parentStore.childDashboard.academic_summary.total_enrolled_years }} Academic Year(s)
            </div>
            <span class="text-[11px] text-slate-500 mt-1 inline-block">
              {{ parentStore.childDashboard.academic_summary.subjects_count }} Subject Courses
            </span>
          </div>
        </div>

        <!-- Academic Structure & Teachers Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Homeroom & Section Leadership -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5">
            <h3 class="text-sm font-bold text-white flex items-center justify-between mb-4">
              <span>Class Teacher &amp; Section Details</span>
              <span class="text-xs font-mono text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                Homeroom
              </span>
            </h3>

            <div v-if="parentStore.childDashboard.student.current_section" class="space-y-3">
              <div class="p-3 bg-slate-950 rounded-lg border border-slate-800/80 flex items-center justify-between">
                <div>
                  <span class="text-[11px] text-slate-400">Class Section</span>
                  <div class="text-sm font-semibold text-white">
                    {{ parentStore.childDashboard.student.current_section.name }}
                  </div>
                </div>
                <div class="text-right">
                  <span class="text-[11px] text-slate-400">Grade Level</span>
                  <div class="text-sm font-semibold text-emerald-400">
                    {{ parentStore.childDashboard.student.current_section.grade_level?.name }}
                  </div>
                </div>
              </div>

              <div class="p-3 bg-slate-950 rounded-lg border border-slate-800/80">
                <span class="text-[11px] text-slate-400">Assigned Homeroom Teacher</span>
                <div class="text-sm font-semibold text-white mt-0.5">
                  {{ parentStore.childDashboard.student.current_section.homeroom_teacher?.name || 'Not yet assigned' }}
                </div>
                <div class="text-xs text-slate-400 mt-1 flex items-center gap-2">
                  <span>✉ {{ parentStore.childDashboard.student.current_section.homeroom_teacher?.email || 'N/A' }}</span>
                </div>
              </div>
            </div>
            <div v-else class="text-xs text-slate-500 py-4 text-center">
              Student is not currently enrolled in an active section.
            </div>
          </div>

          <!-- Subject Teachers Roster -->
          <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5">
            <h3 class="text-sm font-bold text-white flex items-center justify-between mb-4">
              <span>Subject Teachers</span>
              <span class="text-xs text-slate-500 font-mono">
                {{ parentStore.childDashboard.student.current_section?.subject_teachers?.length || 0 }} Assigned
              </span>
            </h3>

            <div v-if="parentStore.childDashboard.student.current_section?.subject_teachers?.length" class="space-y-2 max-h-56 overflow-y-auto pr-1">
              <div 
                v-for="st in parentStore.childDashboard.student.current_section.subject_teachers" 
                :key="st.id"
                class="p-2.5 bg-slate-950 rounded-lg border border-slate-800 flex items-center justify-between"
              >
                <div>
                  <div class="text-xs font-semibold text-white">
                    {{ st.course?.name }}
                  </div>
                  <div class="text-[11px] font-mono text-indigo-400">
                    {{ st.course?.code }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-xs text-slate-300">
                    {{ st.staff?.user?.name || 'Staff' }}
                  </div>
                  <div class="text-[10px] text-slate-500">
                    {{ st.staff?.user?.email }}
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-xs text-slate-500 py-6 text-center">
              No specific subject teacher assignments for this section yet.
            </div>
          </div>
        </div>

        <!-- Child Daily Attendance & Rate Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
              <span>Attendance Performance &amp; History</span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 font-mono">
                Official Records
              </span>
            </h3>
            <span class="text-xs text-slate-500">
              Excludes weekends &amp; school holidays
            </span>
          </div>

          <div v-if="attendanceLoading" class="py-4 text-center text-xs text-slate-500">
            Loading child attendance records...
          </div>

          <div v-else-if="childAttendance?.summary" class="space-y-4">
            <!-- Attendance Rate Metric Bars -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
              <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center">
                <span class="text-[11px] text-slate-400">School Days</span>
                <div class="text-base font-bold text-white mt-0.5">{{ childAttendance.summary.total_school_days }}</div>
              </div>
              <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center">
                <span class="text-[11px] text-emerald-400">Present</span>
                <div class="text-base font-bold text-emerald-400 mt-0.5">{{ childAttendance.summary.present_days }}</div>
              </div>
              <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center">
                <span class="text-[11px] text-amber-400">Late / Tardy</span>
                <div class="text-base font-bold text-amber-400 mt-0.5">{{ childAttendance.summary.late_days }}</div>
              </div>
              <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center">
                <span class="text-[11px] text-rose-400">Absent</span>
                <div class="text-base font-bold text-rose-400 mt-0.5">{{ childAttendance.summary.absent_days }}</div>
              </div>
              <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center">
                <span class="text-[11px] text-blue-400">Excused</span>
                <div class="text-base font-bold text-blue-400 mt-0.5">{{ childAttendance.summary.excused_days }}</div>
              </div>
              <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center">
                <span class="text-[11px] text-emerald-400">Attendance Rate</span>
                <div class="text-base font-black text-emerald-300 mt-0.5">
                  {{ childAttendance.summary.attendance_percentage }}%
                </div>
              </div>
            </div>

            <!-- Recent Records Table -->
            <div v-if="childAttendance.recent_records?.length" class="overflow-x-auto pt-2">
              <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
                  <tr>
                    <th class="p-2.5">Date</th>
                    <th class="p-2.5">Status</th>
                    <th class="p-2.5">Remarks / Reason</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                  <tr v-for="rec in childAttendance.recent_records.slice(0, 5)" :key="rec.id">
                    <td class="p-2.5 font-mono text-white">{{ rec.date }}</td>
                    <td class="p-2.5">
                      <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase"
                        :class="{
                          'bg-emerald-500/10 text-emerald-400': rec.status === 'present',
                          'bg-amber-500/10 text-amber-400': rec.status === 'late',
                          'bg-rose-500/10 text-rose-400': rec.status === 'absent',
                          'bg-blue-500/10 text-blue-400': rec.status === 'excused',
                        }"
                      >
                        {{ rec.status }}
                      </span>
                    </td>
                    <td class="p-2.5 text-slate-400">{{ rec.remarks || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else class="text-xs text-slate-500 py-4 text-center">
            No attendance records logged for this student yet.
          </div>
        </div>

        <!-- Official Report Cards & Exam Results (Prompt 8) -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span>Official Term Report Cards &amp; Exam Results</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
                  Verified &amp; Published
                </span>
              </h3>
              <p class="text-xs text-slate-400 mt-0.5">
                Download verified academic report cards in PDF format matching the school's configured grading scale.
              </p>
            </div>
            <span class="text-xs text-slate-500">
              Only published records are available
            </span>
          </div>

          <div v-if="reportCardsLoading" class="py-4 text-center text-xs text-slate-500">
            Loading academic report cards...
          </div>

          <div v-else-if="childReportCards.length > 0" class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
              <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
                <tr>
                  <th class="p-3">Academic Term</th>
                  <th class="p-3">Academic Year</th>
                  <th class="p-3">Average %</th>
                  <th class="p-3">Overall Grade</th>
                  <th class="p-3">GPA</th>
                  <th class="p-3">Class Rank</th>
                  <th class="p-3 text-right">Official Document</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-for="rc in childReportCards" :key="rc.id" class="hover:bg-slate-950/40">
                  <td class="p-3 font-semibold text-white">{{ rc.term?.name || 'Term' }}</td>
                  <td class="p-3 font-mono text-slate-400">{{ rc.academic_year?.name || '-' }}</td>
                  <td class="p-3 font-bold font-mono text-white">{{ rc.average_percentage }}%</td>
                  <td class="p-3">
                    <span class="font-bold text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                      {{ rc.overall_grade || 'N/A' }}
                    </span>
                  </td>
                  <td class="p-3 font-mono">{{ rc.gpa ? rc.gpa.toFixed(2) : '-' }}</td>
                  <td class="p-3">
                    <span v-if="rc.rank_in_section" class="px-2 py-0.5 rounded bg-slate-800 text-indigo-300 font-mono text-[11px]">
                      #{{ rc.rank_in_section }} / {{ rc.total_students_in_section || '-' }}
                    </span>
                    <span v-else class="text-slate-500">-</span>
                  </td>
                  <td class="p-3 text-right">
                    <button
                      @click="downloadChildPdf(rc)"
                      :disabled="downloadingCardId === rc.id"
                      class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold text-xs transition inline-flex items-center gap-1.5 shadow-sm"
                    >
                      <span>{{ downloadingCardId === rc.id ? 'Generating...' : '📄 Download PDF' }}</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="py-6 text-center bg-slate-950/40 rounded-lg border border-dashed border-slate-800/80">
            <div class="text-2xl mb-1">📜</div>
            <div class="text-xs font-semibold text-slate-300">No Published Report Cards Yet</div>
            <p class="text-[11px] text-slate-500 mt-1 max-w-sm mx-auto">
              Draft report cards are hidden until teachers finish assessments and the school administration publishes them.
            </p>
          </div>
        </div>

        <!-- Weekly Class Timetable (Prompt 9) -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span>Weekly Class Schedule &amp; Timetable</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">
                  Weekly Roster
                </span>
              </h3>
              <p class="text-xs text-slate-400 mt-0.5">
                Section weekly schedule across subjects, assigned teachers, and room locations.
              </p>
            </div>
          </div>

          <div v-if="timetableLoading" class="py-4 text-center text-xs text-slate-500">
            Loading child weekly timetable...
          </div>

          <div v-else-if="childTimetable?.slots?.length > 0" class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-950 text-slate-400 border-b border-slate-800 uppercase font-semibold">
                  <th class="p-2.5 text-center w-28">Period</th>
                  <th v-for="d in childTimetable.days" :key="d" class="p-2.5 text-center min-w-[120px]">
                    {{ d.charAt(0).toUpperCase() + d.slice(1) }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-for="p in childTimetable.periods" :key="p.period_number">
                  <td class="p-2 text-center bg-slate-950/40 border-r border-slate-800 font-mono text-[11px]">
                    <div class="font-bold text-white">Period {{ p.period_number }}</div>
                    <div class="text-[10px] text-slate-500">{{ p.times.start }} - {{ p.times.end }}</div>
                  </td>
                  <td v-for="d in childTimetable.days" :key="d" class="p-2 border-r border-slate-800 last:border-r-0 align-top">
                    <div v-if="childTimetable.grid[d]?.[p.period_number]" class="p-2 rounded-lg bg-slate-950 border border-slate-800 space-y-1">
                      <div class="font-bold text-white text-[11px]">{{ childTimetable.grid[d][p.period_number].subject?.name }}</div>
                      <div class="text-[10px] text-indigo-400">{{ childTimetable.grid[d][p.period_number].teacher?.name || 'No Teacher' }}</div>
                      <div class="text-[9px] text-slate-500 font-mono">📍 {{ childTimetable.grid[d][p.period_number].room || 'Room TBA' }}</div>
                    </div>
                    <div v-else class="text-center text-slate-700 text-[10px] py-2">-</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="py-6 text-center bg-slate-950/40 rounded-lg border border-dashed border-slate-800/80">
            <div class="text-2xl mb-1">📅</div>
            <div class="text-xs font-semibold text-slate-300">No Weekly Schedule Assigned</div>
            <p class="text-[11px] text-slate-500 mt-1 max-w-sm mx-auto">
              Timetable slots have not yet been assigned for this student's section.
            </p>
          </div>
        </div>

        <!-- Enrollment History / Timeline Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5">
          <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
            <span>Academic Enrollment History</span>
            <span class="text-xs text-slate-400 font-normal">
              (Preserved historical records across academic years)
            </span>
          </h3>

          <div v-if="parentStore.childDashboard.student.enrollments?.length" class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
              <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
                <tr>
                  <th class="p-3">Academic Year</th>
                  <th class="p-3">Grade Level</th>
                  <th class="p-3">Section</th>
                  <th class="p-3">Enrolled At</th>
                  <th class="p-3 text-right">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-for="enr in parentStore.childDashboard.student.enrollments" :key="enr.id" class="hover:bg-slate-950/40">
                  <td class="p-3 font-mono font-medium text-white">
                    {{ enr.academic_year?.name }}
                  </td>
                  <td class="p-3">
                    {{ enr.section?.grade_level?.name || '-' }}
                  </td>
                  <td class="p-3">
                    {{ enr.section?.name || '-' }}
                  </td>
                  <td class="p-3 text-slate-400">
                    {{ enr.enrolled_at || '-' }}
                  </td>
                  <td class="p-3 text-right">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase"
                      :class="enr.status === 'enrolled' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-800 text-slate-400'"
                    >
                      {{ enr.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-xs text-slate-500 py-4 text-center">
            No enrollment records found.
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: ADMIN PARENT DIRECTORY & STUDENT LINKING -->
    <div v-if="activeTab === 'directory' && authStore.isSchoolAdmin" class="space-y-6">
      <!-- Action Toolbar -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex-1 flex items-center gap-3">
          <input 
            v-model="parentStore.searchQuery"
            @keyup.enter="parentStore.fetchParents(1)"
            type="text" 
            placeholder="Search parent name, student name, email, phone..." 
            class="w-full md:w-80 bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
          />
          <button
            @click="parentStore.fetchParents(1)"
            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white rounded-lg transition"
          >
            Search
          </button>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="openInviteModal"
            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-lg border border-slate-700 transition"
          >
            ✉ Invite Parent
          </button>
          <button
            @click="openCreateParentModal"
            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow-sm transition"
          >
            + New Parent Account
          </button>
        </div>
      </div>

      <!-- Parents Directory Table -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
              <tr>
                <th class="p-3.5">Parent / Guardian</th>
                <th class="p-3.5">Contact &amp; Occupation</th>
                <th class="p-3.5">Linked Children</th>
                <th class="p-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="parentStore.loading" class="text-center">
                <td colspan="4" class="p-8 text-slate-500">Loading parents directory...</td>
              </tr>
              <tr v-else-if="parentStore.parents.length === 0" class="text-center">
                <td colspan="4" class="p-8 text-slate-500">No parent accounts found.</td>
              </tr>
              <tr v-for="p in parentStore.parents" :key="p.id" class="hover:bg-slate-950/40">
                <!-- Parent Name -->
                <td class="p-3.5">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-950 border border-emerald-800/40 text-emerald-300 font-bold flex items-center justify-center text-xs">
                      {{ getInitials(p.user?.name) }}
                    </div>
                    <div>
                      <div class="font-bold text-white text-sm">
                        {{ p.user?.name }}
                      </div>
                      <div class="text-[11px] text-slate-400">
                        {{ p.user?.email }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Contact & Occupation -->
                <td class="p-3.5">
                  <div class="text-slate-200">
                    {{ p.occupation || 'Parent / Guardian' }}
                  </div>
                  <div class="text-[11px] text-slate-400 mt-0.5">
                    📞 {{ p.phone || p.user?.phone || 'No phone' }}
                  </div>
                  <div v-if="p.address" class="text-[10px] text-slate-500 mt-0.5 truncate max-w-xs">
                    📍 {{ p.address }}
                  </div>
                </td>

                <!-- Linked Children Badges -->
                <td class="p-3.5">
                  <div v-if="p.students?.length" class="flex flex-wrap gap-1.5">
                    <div 
                      v-for="st in p.students" 
                      :key="st.id"
                      class="flex items-center gap-1.5 px-2 py-1 rounded bg-slate-950 border border-slate-800 text-[11px]"
                    >
                      <span class="font-medium text-slate-200">{{ st.user?.name }}</span>
                      <span class="text-[10px] text-emerald-400 capitalize">({{ st.pivot?.relationship || 'guardian' }})</span>
                      <button 
                        @click="unlinkStudent(p.id, st.id)"
                        title="Unlink student"
                        class="text-slate-500 hover:text-rose-400 ml-1 text-xs"
                      >
                        ✕
                      </button>
                    </div>
                  </div>
                  <span v-else class="text-[11px] text-slate-500 italic">No linked students</span>
                </td>

                <!-- Actions -->
                <td class="p-3.5 text-right">
                  <button
                    @click="openLinkStudentModal(p)"
                    class="px-2.5 py-1 text-[11px] font-semibold bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 rounded-lg hover:bg-indigo-600 hover:text-white transition"
                  >
                    + Link Student
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL 1: LINK STUDENT TO PARENT -->
    <div v-if="showLinkModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>Link Student to Parent</span>
          <button @click="showLinkModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>
        <p class="text-xs text-slate-400">
          Linking student to parent: <strong class="text-emerald-400">{{ selectedParentForLink?.user?.name }}</strong>
        </p>

        <form @submit.prevent="submitLinkStudent" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Select Student</label>
            <select 
              v-model="linkForm.student_id" 
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            >
              <option value="">-- Select a Student --</option>
              <option v-for="stu in availableStudents" :key="stu.id" :value="stu.id">
                {{ stu.user?.name }} ({{ stu.admission_number }}) - {{ stu.current_section?.name || 'No Section' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Relationship</label>
            <select 
              v-model="linkForm.relationship" 
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            >
              <option value="father">Father</option>
              <option value="mother">Mother</option>
              <option value="guardian">Legal Guardian</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input 
              v-model="linkForm.is_primary_contact" 
              type="checkbox" 
              id="is_primary"
              class="rounded bg-slate-950 border-slate-800 text-emerald-600 focus:ring-0"
            />
            <label for="is_primary" class="text-xs text-slate-300">Set as Primary Guardian Contact</label>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="showLinkModal = false" 
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="parentStore.actionLoading"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white rounded-lg shadow-sm"
            >
              {{ parentStore.actionLoading ? 'Linking...' : 'Link Student' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: CREATE NEW PARENT ACCOUNT -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>Create New Parent Account</span>
          <button @click="showCreateModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>

        <form @submit.prevent="submitCreateParent" class="space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Full Name</label>
              <input 
                v-model="createForm.name" 
                type="text" 
                required 
                placeholder="e.g. Ned Flanders"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Email Address</label>
              <input 
                v-model="createForm.email" 
                type="email" 
                required 
                placeholder="ned@springfield.org"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Phone Number</label>
              <input 
                v-model="createForm.phone" 
                type="text" 
                placeholder="+1 (555) 733-5555"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Occupation</label>
              <input 
                v-model="createForm.occupation" 
                type="text" 
                placeholder="e.g. Business Owner"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Address</label>
            <input 
              v-model="createForm.address" 
              type="text" 
              placeholder="744 Evergreen Terrace"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            />
          </div>

          <!-- Optional Initial Student Link -->
          <div class="border-t border-slate-800 pt-3">
            <label class="block text-xs font-semibold text-emerald-400 mb-1">Link Enrolled Student (Optional)</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <select 
                v-model="createForm.student_id" 
                class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              >
                <option value="">-- None (Link Later) --</option>
                <option v-for="stu in availableStudents" :key="stu.id" :value="stu.id">
                  {{ stu.user?.name }} ({{ stu.admission_number }})
                </option>
              </select>

              <select 
                v-model="createForm.relationship" 
                class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              >
                <option value="father">Father</option>
                <option value="mother">Mother</option>
                <option value="guardian">Guardian</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3">
            <button 
              type="button" 
              @click="showCreateModal = false" 
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="parentStore.actionLoading"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white rounded-lg shadow-sm"
            >
              {{ parentStore.actionLoading ? 'Creating...' : 'Create Parent' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 3: INVITE PARENT -->
    <div v-if="showInviteModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>Invite Parent by Email</span>
          <button @click="showInviteModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>
        <p class="text-xs text-slate-400">
          Sends an invite token and temporary credentials to the parent email.
        </p>

        <form @submit.prevent="submitInviteParent" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Parent Email</label>
            <input 
              v-model="inviteForm.email" 
              type="email" 
              required 
              placeholder="parent@example.com"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Parent Full Name</label>
            <input 
              v-model="inviteForm.name" 
              type="text" 
              placeholder="Maude Flanders"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Link Student (Optional)</label>
            <select 
              v-model="inviteForm.student_id" 
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            >
              <option value="">-- None --</option>
              <option v-for="stu in availableStudents" :key="stu.id" :value="stu.id">
                {{ stu.user?.name }} ({{ stu.admission_number }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Relationship</label>
            <select 
              v-model="inviteForm.relationship" 
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            >
              <option value="father">Father</option>
              <option value="mother">Mother</option>
              <option value="guardian">Legal Guardian</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div class="flex justify-end gap-2 pt-3">
            <button 
              type="button" 
              @click="showInviteModal = false" 
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="parentStore.actionLoading"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white rounded-lg shadow-sm"
            >
              {{ parentStore.actionLoading ? 'Inviting...' : 'Send Invitation' }}
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
import { useParentStore } from '../stores/parent';
import axios from 'axios';

const authStore = useAuthStore();
const parentStore = useParentStore();

const activeTab = ref('portal');
const showLinkModal = ref(false);
const showCreateModal = ref(false);
const showInviteModal = ref(false);

const selectedParentForLink = ref(null);
const availableStudents = ref([]);

const linkForm = ref({
    student_id: '',
    relationship: 'father',
    is_primary_contact: false,
});

const createForm = ref({
    name: '',
    email: '',
    phone: '',
    occupation: '',
    address: '',
    student_id: '',
    relationship: 'father',
});

const inviteForm = ref({
    email: '',
    name: '',
    phone: '',
    student_id: '',
    relationship: 'guardian',
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

async function loadAvailableStudents() {
    try {
        const res = await axios.get('/students?all=true');
        availableStudents.value = res.data.data || [];
    } catch (e) {
        // Silent catch for non-admins
    }
}

function switchToDirectory() {
    activeTab.value = 'directory';
    parentStore.fetchParents(1);
    loadAvailableStudents();
}

function openLinkStudentModal(parent) {
    selectedParentForLink.value = parent;
    linkForm.value = {
        student_id: '',
        relationship: 'guardian',
        is_primary_contact: false,
    };
    showLinkModal.value = true;
    loadAvailableStudents();
}

async function submitLinkStudent() {
    if (!selectedParentForLink.value?.id) return;
    try {
        await parentStore.linkStudent(selectedParentForLink.value.id, linkForm.value);
        showLinkModal.value = false;
    } catch (e) {
        // error handled in store
    }
}

async function unlinkStudent(parentId, studentId) {
    if (!confirm('Are you sure you want to unlink this student from the parent?')) return;
    await parentStore.unlinkStudent(parentId, studentId);
}

function openCreateParentModal() {
    createForm.value = {
        name: '',
        email: '',
        phone: '',
        occupation: '',
        address: '',
        student_id: '',
        relationship: 'father',
    };
    showCreateModal.value = true;
    loadAvailableStudents();
}

async function submitCreateParent() {
    try {
        await parentStore.createParent(createForm.value);
        showCreateModal.value = false;
    } catch (e) {
        // error handled in store
    }
}

function openInviteModal() {
    inviteForm.value = {
        email: '',
        name: '',
        phone: '',
        student_id: '',
        relationship: 'guardian',
    };
    showInviteModal.value = true;
    loadAvailableStudents();
}

async function submitInviteParent() {
    try {
        await parentStore.inviteParent(inviteForm.value);
        showInviteModal.value = false;
    } catch (e) {
        // error handled in store
    }
}

const childAttendance = ref(null);
const attendanceLoading = ref(false);

const childReportCards = ref([]);
const reportCardsLoading = ref(false);
const downloadingCardId = ref(null);

const childTimetable = ref(null);
const timetableLoading = ref(false);

async function loadChildAttendance(childId) {
    if (!childId) return;
    attendanceLoading.value = true;
    try {
        const res = await axios.get(`/parent/children/${childId}/attendance`);
        childAttendance.value = res.data.data;
    } catch (e) {
        childAttendance.value = null;
    } finally {
        attendanceLoading.value = false;
    }
}

async function loadChildReportCards(childId) {
    if (!childId) return;
    reportCardsLoading.value = true;
    try {
        const res = await axios.get(`/parent/children/${childId}/report-cards`);
        childReportCards.value = res.data.data || [];
    } catch (e) {
        childReportCards.value = [];
    } finally {
        reportCardsLoading.value = false;
    }
}

async function loadChildTimetable(childId) {
    if (!childId) return;
    timetableLoading.value = true;
    try {
        const res = await axios.get(`/parent/children/${childId}/timetable`);
        childTimetable.value = res.data.data;
    } catch (e) {
        childTimetable.value = null;
    } finally {
        timetableLoading.value = false;
    }
}

async function downloadChildPdf(rc) {
    const activeKid = parentStore.activeChild;
    if (!activeKid || !rc) return;
    downloadingCardId.value = rc.id;
    try {
        const res = await axios.get(`/parent/children/${activeKid.id}/report-cards/${rc.id}/pdf`, {
            responseType: 'blob',
        });
        const blob = new Blob([res.data], { type: 'application/pdf' });
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = `ReportCard_${activeKid.admission_number || 'Student'}_${rc.term?.name || 'Term'}.pdf`;
        link.click();
        window.URL.revokeObjectURL(link.href);
    } catch (e) {
        parentStore.error = e.response?.data?.error?.message || 'Failed to download report card PDF.';
    } finally {
        downloadingCardId.value = null;
    }
}

watch(() => parentStore.activeChild, (newChild) => {
    if (newChild?.id) {
        loadChildAttendance(newChild.id);
        loadChildReportCards(newChild.id);
        loadChildTimetable(newChild.id);
    }
}, { immediate: true });

onMounted(async () => {
    if (authStore.isParent) {
        activeTab.value = 'portal';
        const kids = await parentStore.fetchChildren();
        if (kids.length > 0) {
            loadChildAttendance(kids[0].id);
            loadChildReportCards(kids[0].id);
            loadChildTimetable(kids[0].id);
        }
    } else if (authStore.isSchoolAdmin) {
        activeTab.value = 'directory';
        await parentStore.fetchParents(1);
        await loadAvailableStudents();
    } else {
        const kids = await parentStore.fetchChildren();
        if (kids.length > 0) {
            loadChildAttendance(kids[0].id);
            loadChildReportCards(kids[0].id);
            loadChildTimetable(kids[0].id);
        }
    }
});
</script>
