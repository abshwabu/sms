<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Grading, Report Cards &amp; Exams</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            Academic Performance
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Subject-level mark entry, automatic report card aggregation, class standings, and PDF distribution.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Tab Switcher -->
        <div class="flex items-center gap-1.5 bg-slate-900 border border-slate-800 p-1 rounded-xl">
          <button
            @click="switchTab('entry')"
            class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
            :class="activeTab === 'entry' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
          >
            📝 Grade Entry
          </button>
          <button
            @click="switchTab('reports')"
            class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
            :class="activeTab === 'reports' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
          >
            📊 Report Cards &amp; Standings
          </button>
          <button
            @click="switchTab('scales')"
            class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
            :class="activeTab === 'scales' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
          >
            ⚙️ Grading Scales &amp; Exams
          </button>
        </div>

        <!-- Quick Assessment Button -->
        <button
          @click="showCreateExamModal = true"
          class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-sm inline-flex items-center gap-1.5 whitespace-nowrap"
        >
          <span>+ New Assessment</span>
        </button>
      </div>
    </div>

    <!-- TAB 1: GRADE ENTRY ROSTER -->
    <div v-if="activeTab === 'entry'" class="space-y-6">
      <!-- Selector Card -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <!-- Section Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Section / Class *</label>
            <select
              v-model="selectedSectionId"
              @change="onSectionChange"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option :value="null" disabled>Select section...</option>
              <option v-for="sec in gradingStore.sections" :key="sec.id" :value="sec.id">
                {{ sec.name }} ({{ sec.grade_level?.name || 'Grade' }})
              </option>
            </select>
          </div>

          <!-- Subject Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Subject *</label>
            <select
              v-model="selectedSubjectId"
              @change="onSubjectChange"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option :value="null" disabled>Select subject...</option>
              <option v-for="sub in gradingStore.subjects" :key="sub.id" :value="sub.id">
                {{ sub.name }} ({{ sub.code }})
              </option>
            </select>
          </div>

          <!-- Exam / Assessment Selector -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Assessment / Exam *</label>
              <button
                type="button"
                @click="showCreateExamModal = true"
                class="text-[11px] text-indigo-400 hover:text-indigo-300 font-semibold"
              >
                + Add Exam
              </button>
            </div>
            <select
              v-model="selectedExamId"
              @change="onExamChange"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option :value="null" disabled>Select assessment...</option>
              <option v-for="ex in gradingStore.exams" :key="ex.id" :value="ex.id">
                {{ ex.name }} ({{ ex.type }} &bull; {{ ex.weight }}% weight)
              </option>
            </select>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
          <div class="text-slate-400 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            <span>Marks are auto-aggregated across exams into GPA, letter grades, and section rankings upon save.</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="loadGradingRoster"
              :disabled="!selectedSectionId || !selectedSubjectId || !selectedExamId || gradingStore.loading"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold rounded-lg transition inline-flex items-center gap-1.5"
            >
              <svg v-if="gradingStore.loading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ gradingStore.loading ? 'Loading Roster...' : 'Fetch Roster' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Notice when no exams are created -->
      <div v-if="gradingStore.exams.length === 0 && !gradingStore.loading" class="p-5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <div class="font-bold text-sm text-amber-200">No Assessments Created Yet</div>
          <div class="mt-0.5 text-amber-300/80">To enter student grades, please create at least one assessment (Quiz, Midterm, or Final Examination).</div>
        </div>
        <button
          @click="showCreateExamModal = true"
          class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-xl text-xs transition whitespace-nowrap shadow-sm"
        >
          + Create First Assessment
        </button>
      </div>

      <!-- Grading Table -->
      <div v-if="gradingRoster" class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-950/40">
          <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
              <span>{{ gradingRoster.subject.name }} ({{ gradingRoster.subject.code }})</span>
              <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
                {{ gradingRoster.exam ? gradingRoster.exam.name : 'Assessment' }}
              </span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Section: <span class="text-slate-300 font-semibold">{{ gradingRoster.section.name }}</span> &bull;
              Weight: <span class="text-slate-300 font-semibold">{{ gradingRoster.exam?.weight || 0 }}%</span> &bull;
              Max Marks: <span class="text-slate-300 font-semibold">{{ gradingRoster.exam?.max_marks || 100 }}</span> &bull;
              Total Students: <span class="text-slate-300 font-semibold">{{ rosterInputs.length }}</span>
            </p>
          </div>

          <!-- Quick Fill Tools -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs text-slate-400 mr-1">Quick Fill:</span>
            <button
              type="button"
              @click="quickFill(100)"
              class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
            >
              100%
            </button>
            <button
              type="button"
              @click="quickFill(85)"
              class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
            >
              85%
            </button>
            <button
              type="button"
              @click="quickFill(75)"
              class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
            >
              75%
            </button>
            <button
              type="button"
              @click="clearMarks"
              class="px-2.5 py-1 text-xs rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 transition"
            >
              Clear
            </button>
          </div>
        </div>

        <div v-if="rosterInputs.length === 0" class="p-12 text-center text-xs text-slate-400 space-y-2">
          <div class="text-base font-semibold text-slate-300">No Students Enrolled in This Section</div>
          <p class="max-w-md mx-auto text-slate-500">
            There are currently no active students assigned to {{ gradingRoster.section.name }}. You can enroll students from the student directory.
          </p>
          <div class="pt-2">
            <router-link
              to="/students"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold inline-block transition"
            >
              Go to Student Directory &rarr;
            </router-link>
          </div>
        </div>

        <form v-else @submit.prevent="submitGrades">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
              <thead class="bg-slate-950 text-slate-400 font-semibold border-b border-slate-800 uppercase tracking-wider">
                <tr>
                  <th class="p-3.5">Student Name</th>
                  <th class="p-3.5">Admission No</th>
                  <th class="p-3.5">Marks Obtained</th>
                  <th class="p-3.5">Max Marks</th>
                  <th class="p-3.5">Score %</th>
                  <th class="p-3.5">Status</th>
                  <th class="p-3.5">Teacher Feedback / Remarks</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-for="item in rosterInputs" :key="item.student_id" class="hover:bg-slate-850/50">
                  <td class="p-3.5 font-semibold text-white flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-indigo-300">
                      {{ item.name ? item.name.charAt(0).toUpperCase() : 'S' }}
                    </div>
                    <span>{{ item.name }}</span>
                  </td>
                  <td class="p-3.5 font-mono text-slate-400">{{ item.admission_number }}</td>
                  <td class="p-3.5">
                    <input
                      v-model.number="item.marks_obtained"
                      type="number"
                      step="0.5"
                      min="0"
                      :max="item.max_marks"
                      placeholder="—"
                      class="w-24 bg-slate-950 border border-slate-700 rounded px-2 py-1 text-sm text-white font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    />
                  </td>
                  <td class="p-3.5 font-mono text-slate-400">{{ item.max_marks }}</td>
                  <td class="p-3.5">
                    <span
                      class="px-2 py-0.5 rounded font-mono font-bold text-xs"
                      :class="getPercentageBadgeClass(item)"
                    >
                      {{ calculatePct(item) !== null ? calculatePct(item) + '%' : 'Pending' }}
                    </span>
                  </td>
                  <td class="p-3.5">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider"
                      :class="item.is_entered ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400'"
                    >
                      {{ item.is_entered ? 'Recorded' : 'New' }}
                    </span>
                  </td>
                  <td class="p-3.5">
                    <input
                      v-model="item.remarks"
                      type="text"
                      placeholder="Optional feedback..."
                      class="w-full bg-slate-950 border border-slate-700 rounded px-2.5 py-1 text-xs text-white placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="p-5 border-t border-slate-800 bg-slate-950/40 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-slate-400">
              Saving grades triggers instant report card aggregation and updates student standings.
            </div>
            <button
              type="submit"
              :disabled="gradingStore.actionLoading"
              class="w-full sm:w-auto px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition flex items-center justify-center gap-2"
            >
              <svg v-if="gradingStore.actionLoading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ gradingStore.actionLoading ? 'Saving & Aggregating...' : '💾 Save Grades & Aggregate Standings' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- TAB 2: REPORT CARDS & STANDINGS -->
    <div v-if="activeTab === 'reports'" class="space-y-6">
      <!-- Section & Term Selector -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row items-stretch md:items-end justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1 max-w-xl">
          <!-- Section Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Section *</label>
            <select
              v-model="reportSectionId"
              @change="loadSectionReportCards"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option v-for="sec in gradingStore.sections" :key="sec.id" :value="sec.id">
                {{ sec.name }} ({{ sec.grade_level?.name || 'Grade' }})
              </option>
            </select>
          </div>

          <!-- Term Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Academic Term</label>
            <select
              v-model="reportTermId"
              @change="loadSectionReportCards"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option :value="null">All / Active Term</option>
              <option v-for="term in gradingStore.terms" :key="term.id" :value="term.id">
                {{ term.name }} ({{ term.academic_year?.name || 'Year' }}) {{ term.is_active ? '★ Active' : '' }}
              </option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="loadSectionReportCards"
            :disabled="!reportSectionId || gradingStore.loading"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-lg transition inline-flex items-center gap-1.5"
          >
            <span>🔄 Reload</span>
          </button>
          <button
            @click="bulkPublish"
            :disabled="!reportSectionId || gradingStore.actionLoading || !gradingStore.sectionReportCards?.report_cards?.length"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition inline-flex items-center gap-1.5 shadow-sm shadow-emerald-600/20"
          >
            <span>📢 Bulk Publish Section</span>
          </button>
        </div>
      </div>

      <!-- Metrics Summary Widgets -->
      <div v-if="gradingStore.sectionReportCards?.report_cards?.length" class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Students Ranked</div>
          <div class="text-2xl font-bold text-white mt-1 font-mono">
            {{ gradingStore.sectionReportCards.report_cards.length }}
          </div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Class Average</div>
          <div class="text-2xl font-bold text-indigo-400 mt-1 font-mono">
            {{ calculateClassAverage() }}%
          </div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Top Performer</div>
          <div class="text-base font-bold text-emerald-400 mt-1 truncate">
            {{ getTopStudentName() }}
          </div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Submissions</div>
          <div class="text-xl font-bold text-white mt-1 font-mono">
            {{ countSubmittedCards() }}/{{ gradingStore.sectionReportCards.report_cards.length }}
          </div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Published</div>
          <div class="text-xl font-bold text-white mt-1 font-mono">
            {{ countPublishedCards() }}/{{ gradingStore.sectionReportCards.report_cards.length }}
          </div>
        </div>
      </div>

      <!-- Report Cards List -->
      <div v-if="gradingStore.sectionReportCards" class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-950/40">
          <div>
            <h2 class="text-base font-bold text-white">
              Official Report Cards &amp; Rankings: {{ gradingStore.sectionReportCards.section.name }}
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Term: <span class="text-slate-300 font-semibold">{{ gradingStore.sectionReportCards.term.name }}</span> &bull;
              Total Students: <span class="text-slate-300 font-semibold">{{ gradingStore.sectionReportCards.report_cards.length }}</span>
            </p>
          </div>
        </div>

        <div v-if="!gradingStore.sectionReportCards.report_cards?.length" class="p-12 text-center text-xs text-slate-400 space-y-2">
          <div class="text-base font-semibold text-slate-300">No Report Cards Found</div>
          <p class="max-w-md mx-auto text-slate-500">
            No student marks have been aggregated for this section yet. Enter subject grades in the Grade Entry tab to generate official report cards.
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-semibold border-b border-slate-800 uppercase tracking-wider">
              <tr>
                <th class="p-3.5">Rank</th>
                <th class="p-3.5">Student</th>
                <th class="p-3.5">Admission No</th>
                <th class="p-3.5">Average %</th>
                <th class="p-3.5">Grade</th>
                <th class="p-3.5">GPA</th>
                <th class="p-3.5">Teachers Submitted</th>
                <th class="p-3.5">Status</th>
                <th class="p-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-for="rc in gradingStore.sectionReportCards.report_cards" :key="rc.id" class="hover:bg-slate-850/50">
                <td class="p-3.5">
                  <span
                    class="font-bold text-xs px-2.5 py-1 rounded-lg font-mono inline-block"
                    :class="rc.rank_in_section === 1 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800 text-indigo-300'"
                  >
                    #{{ rc.rank_in_section || '-' }}
                  </span>
                </td>
                <td class="p-3.5 font-medium text-white">{{ rc.student?.user?.name }}</td>
                <td class="p-3.5 font-mono text-slate-400">{{ rc.student?.admission_number }}</td>
                <td class="p-3.5 font-bold font-mono text-white">{{ rc.average_percentage }}%</td>
                <td class="p-3.5">
                  <span class="font-bold text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    {{ rc.overall_grade || 'N/A' }}
                  </span>
                </td>
                <td class="p-3.5 font-mono text-slate-300">{{ rc.gpa ? rc.gpa.toFixed(2) : '-' }}</td>
                <td class="p-3.5">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase"
                    :class="rc.all_teachers_submitted ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400'"
                  >
                    {{ rc.all_teachers_submitted ? 'Complete' : 'Pending' }}
                  </span>
                </td>
                <td class="p-3.5">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                    :class="rc.status === 'published' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'"
                  >
                    {{ rc.status }}
                  </span>
                </td>
                <td class="p-3.5 text-right space-x-1.5 whitespace-nowrap">
                  <button
                    @click="viewReportCardDetails(rc.id)"
                    class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-200 transition font-semibold"
                  >
                    👁️ Details
                  </button>
                  <button
                    v-if="rc.status === 'draft'"
                    @click="publishCard(rc.id)"
                    class="px-2.5 py-1 text-xs rounded bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition shadow-sm"
                  >
                    Publish
                  </button>
                  <button
                    @click="downloadPdf(rc)"
                    class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold transition inline-flex items-center gap-1"
                  >
                    <span>📄 PDF</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: GRADING SCALES & ASSESSMENTS -->
    <div v-if="activeTab === 'scales'" class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold text-white">Grading Systems &amp; Assessment Catalog</h2>
          <p class="text-xs text-slate-400">Configure weighting rules, grade point averages, and exam categories.</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="showCreateExamModal = true"
            class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-sm"
          >
            + Create Assessment
          </button>
          <button
            @click="showCreateScaleModal = true"
            class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl transition"
          >
            + Configure Scale
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Configured Grading Scales -->
        <div class="space-y-4">
          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-white flex items-center justify-between">
              <span>Configured Grading Scales</span>
              <span class="text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
                {{ gradingStore.gradingScales.length }} configured
              </span>
            </h3>

            <div v-for="scale in gradingStore.gradingScales" :key="scale.id" class="p-4 rounded-xl bg-slate-950 border border-slate-800/80 space-y-3">
              <div class="flex items-center justify-between text-xs font-semibold text-slate-300">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-white">{{ scale.name }}</span>
                  <span class="uppercase text-[10px] font-mono text-indigo-400 bg-indigo-500/10 px-1.5 py-0.5 rounded border border-indigo-500/20">
                    {{ scale.scale_type }}
                  </span>
                </div>
                <div class="flex items-center gap-2">
                  <span v-if="scale.is_default" class="text-emerald-400 font-mono text-[10px] bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                    Default
                  </span>
                  <button
                    v-if="!scale.is_default"
                    @click="handleDeleteScale(scale)"
                    class="text-rose-400 hover:text-rose-300 text-xs"
                    title="Delete scale"
                  >
                    ✕
                  </button>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300 border border-slate-800 rounded overflow-hidden">
                  <thead class="bg-slate-900 text-slate-400 font-mono text-[11px]">
                    <tr>
                      <th class="p-2">Score Range</th>
                      <th class="p-2">Letter</th>
                      <th class="p-2">GPA</th>
                      <th class="p-2">Description</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="rule in scale.rules" :key="rule.grade" class="hover:bg-slate-900/50">
                      <td class="p-2 font-mono text-slate-300">{{ rule.min_score }}% - {{ rule.max_score }}%</td>
                      <td class="p-2 font-bold text-white">{{ rule.grade }}</td>
                      <td class="p-2 font-mono text-indigo-300">{{ rule.gpa_point }}</td>
                      <td class="p-2 text-slate-400">{{ rule.description }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Assessments / Exams -->
        <div class="space-y-4">
          <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-white flex items-center justify-between">
              <span>Assessments &amp; Weightings</span>
              <span class="text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
                {{ gradingStore.exams.length }} active
              </span>
            </h3>

            <div v-if="gradingStore.exams.length === 0" class="p-8 text-center text-xs text-slate-400">
              No assessments defined. Click "+ Create Assessment" above to add one.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-xs text-slate-300 border border-slate-800 rounded overflow-hidden">
                <thead class="bg-slate-950 text-slate-400 font-mono text-[11px]">
                  <tr>
                    <th class="p-2.5">Name</th>
                    <th class="p-2.5">Type</th>
                    <th class="p-2.5">Weight</th>
                    <th class="p-2.5">Max Marks</th>
                    <th class="p-2.5">Term</th>
                    <th class="p-2.5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                  <tr v-for="ex in gradingStore.exams" :key="ex.id" class="hover:bg-slate-950/50">
                    <td class="p-2.5 font-semibold text-white">{{ ex.name }}</td>
                    <td class="p-2.5 uppercase font-mono text-[10px] text-indigo-400">{{ ex.type }}</td>
                    <td class="p-2.5 font-mono font-bold text-white">{{ ex.weight }}%</td>
                    <td class="p-2.5 font-mono">{{ ex.max_marks }}</td>
                    <td class="p-2.5 text-slate-400">{{ ex.term?.name || '-' }}</td>
                    <td class="p-2.5 text-right">
                      <button
                        @click="handleDeleteExam(ex)"
                        class="text-rose-400 hover:text-rose-300 text-xs px-2 py-0.5 rounded bg-rose-500/10 border border-rose-500/20"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: CREATE ASSESSMENT -->
    <div v-if="showCreateExamModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl space-y-4 p-6">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div>
            <h3 class="text-base font-bold text-white">Create New Assessment</h3>
            <p class="text-xs text-slate-400 mt-0.5">Define exam name, type, and term weighting.</p>
          </div>
          <button @click="showCreateExamModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="handleCreateExam" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Assessment Name *</label>
            <input
              v-model="newExam.name"
              type="text"
              placeholder="e.g. Midterm Exam, Quiz 1, Final Exam"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Type *</label>
              <select
                v-model="newExam.type"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              >
                <option value="quiz">Quiz</option>
                <option value="midterm">Midterm Exam</option>
                <option value="final">Final Exam</option>
                <option value="cat">CAT (Continuous Assessment)</option>
                <option value="assignment">Assignment / Project</option>
              </select>
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Weight Percentage (%) *</label>
              <input
                v-model.number="newExam.weight"
                type="number"
                min="1"
                max="100"
                step="1"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Max Marks *</label>
              <input
                v-model.number="newExam.max_marks"
                type="number"
                min="1"
                step="1"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Grade Level *</label>
              <select
                v-model="newExam.grade_level_id"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              >
                <option :value="null" disabled>Select grade level...</option>
                <option v-for="gl in gradingStore.gradeLevels" :key="gl.id" :value="gl.id">
                  {{ gl.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Academic Year *</label>
              <select
                v-model="newExam.academic_year_id"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              >
                <option :value="null" disabled>Select year...</option>
                <option v-for="yr in gradingStore.academicYears" :key="yr.id" :value="yr.id">
                  {{ yr.name }} {{ yr.is_active ? '(Active)' : '' }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Academic Term *</label>
              <select
                v-model="newExam.term_id"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              >
                <option :value="null" disabled>Select term...</option>
                <option v-for="tm in gradingStore.terms" :key="tm.id" :value="tm.id">
                  {{ tm.name }} {{ tm.is_active ? '(Active)' : '' }}
                </option>
              </select>
            </div>
          </div>

          <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showCreateExamModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-lg transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="gradingStore.actionLoading"
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition shadow-md shadow-indigo-600/20"
            >
              {{ gradingStore.actionLoading ? 'Creating...' : 'Create Assessment' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: CONFIGURE GRADING SCALE -->
    <div v-if="showCreateScaleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl space-y-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div>
            <h3 class="text-base font-bold text-white">Configure Grading Scale</h3>
            <p class="text-xs text-slate-400 mt-0.5">Define letter grades, percentage thresholds, and GPA points.</p>
          </div>
          <button @click="showCreateScaleModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="handleCreateGradingScale" class="space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Scale Name *</label>
              <input
                v-model="newScale.name"
                type="text"
                placeholder="e.g. Standard Letter Scale"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Scale Type *</label>
              <select
                v-model="newScale.scale_type"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              >
                <option value="letter">Letter (A-F)</option>
                <option value="gpa">GPA Point Scale</option>
                <option value="percentage">Percentage Pass/Fail</option>
              </select>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <input
              v-model="newScale.is_default"
              type="checkbox"
              id="isDefaultScale"
              class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500"
            />
            <label for="isDefaultScale" class="text-slate-300">Set as default grading scale for this school</label>
          </div>

          <!-- Brackets Rules Table -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="font-semibold text-slate-300">Score Range Brackets &amp; Grades</label>
              <button
                type="button"
                @click="addScaleRule"
                class="text-indigo-400 hover:text-indigo-300 text-xs font-semibold"
              >
                + Add Bracket
              </button>
            </div>

            <div class="space-y-2">
              <div v-for="(rule, index) in newScale.rules" :key="index" class="grid grid-cols-12 gap-2 items-center bg-slate-950 p-2 rounded-lg border border-slate-800/80">
                <div class="col-span-2">
                  <label class="block text-[10px] text-slate-500 mb-0.5">Min %</label>
                  <input
                    v-model.number="rule.min_score"
                    type="number"
                    min="0"
                    max="100"
                    required
                    class="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white font-mono text-xs"
                  />
                </div>
                <div class="col-span-2">
                  <label class="block text-[10px] text-slate-500 mb-0.5">Max %</label>
                  <input
                    v-model.number="rule.max_score"
                    type="number"
                    min="0"
                    max="100"
                    required
                    class="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white font-mono text-xs"
                  />
                </div>
                <div class="col-span-2">
                  <label class="block text-[10px] text-slate-500 mb-0.5">Grade</label>
                  <input
                    v-model="rule.grade"
                    type="text"
                    required
                    class="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white font-bold text-xs"
                  />
                </div>
                <div class="col-span-2">
                  <label class="block text-[10px] text-slate-500 mb-0.5">GPA Point</label>
                  <input
                    v-model.number="rule.gpa_point"
                    type="number"
                    step="0.1"
                    min="0"
                    max="5"
                    class="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white font-mono text-xs"
                  />
                </div>
                <div class="col-span-3">
                  <label class="block text-[10px] text-slate-500 mb-0.5">Description</label>
                  <input
                    v-model="rule.description"
                    type="text"
                    class="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white text-xs"
                  />
                </div>
                <div class="col-span-1 text-right pt-3">
                  <button
                    type="button"
                    @click="removeScaleRule(index)"
                    :disabled="newScale.rules.length <= 1"
                    class="text-rose-400 hover:text-rose-300 disabled:opacity-30"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showCreateScaleModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-lg transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="gradingStore.actionLoading"
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition shadow-md shadow-indigo-600/20"
            >
              {{ gradingStore.actionLoading ? 'Saving...' : 'Save Scale' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: REPORT CARD DETAIL PREVIEW -->
    <div v-if="detailReportCard" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-3xl overflow-hidden shadow-2xl space-y-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div>
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
              <span>Official Academic Report Card</span>
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider"
                :class="detailReportCard.status === 'published' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'"
              >
                {{ detailReportCard.status }}
              </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ detailReportCard.student?.user?.name }} &bull; Admission: {{ detailReportCard.student?.admission_number }}
            </p>
          </div>
          <button @click="detailReportCard = null" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <!-- Meta Overview -->
        <div class="grid grid-cols-4 gap-3 text-xs bg-slate-950 p-3.5 rounded-xl border border-slate-800">
          <div>
            <div class="text-slate-500 text-[10px] uppercase font-semibold">Section Rank</div>
            <div class="text-lg font-bold text-indigo-400 font-mono">#{{ detailReportCard.rank_in_section || '-' }}</div>
          </div>
          <div>
            <div class="text-slate-500 text-[10px] uppercase font-semibold">Mean Average</div>
            <div class="text-lg font-bold text-white font-mono">{{ detailReportCard.average_percentage }}%</div>
          </div>
          <div>
            <div class="text-slate-500 text-[10px] uppercase font-semibold">Overall Grade</div>
            <div class="text-lg font-bold text-emerald-400 font-mono">{{ detailReportCard.overall_grade || 'N/A' }}</div>
          </div>
          <div>
            <div class="text-slate-500 text-[10px] uppercase font-semibold">GPA</div>
            <div class="text-lg font-bold text-white font-mono">{{ detailReportCard.gpa ? detailReportCard.gpa.toFixed(2) : '-' }}</div>
          </div>
        </div>

        <!-- Subjects Breakdown Table -->
        <div class="space-y-2">
          <div class="text-xs font-semibold text-slate-300">Subject Breakdown &amp; Teacher Remarks</div>
          <div class="overflow-x-auto border border-slate-800 rounded-xl overflow-hidden">
            <table class="w-full text-left text-xs text-slate-300">
              <thead class="bg-slate-950 text-slate-400 uppercase font-mono text-[10px]">
                <tr>
                  <th class="p-2.5">Subject</th>
                  <th class="p-2.5">Score %</th>
                  <th class="p-2.5">Grade</th>
                  <th class="p-2.5">GPA</th>
                  <th class="p-2.5">Teacher</th>
                  <th class="p-2.5">Remarks</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60 bg-slate-900/40">
                <tr v-for="item in detailReportCard.items" :key="item.id" class="hover:bg-slate-850/50">
                  <td class="p-2.5 font-semibold text-white">
                    {{ item.subject?.name }}
                    <span class="text-[10px] text-slate-500 font-mono ml-1">({{ item.subject?.code }})</span>
                  </td>
                  <td class="p-2.5 font-mono font-bold text-white">{{ item.percentage }}%</td>
                  <td class="p-2.5 font-bold text-indigo-400">{{ item.letter_grade }}</td>
                  <td class="p-2.5 font-mono">{{ item.gpa_point }}</td>
                  <td class="p-2.5 text-slate-400">{{ item.teacher?.name || '-' }}</td>
                  <td class="p-2.5 text-slate-400 italic">{{ item.teacher_remarks || 'Satisfactory' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Attendance Summary -->
        <div v-if="detailReportCard.attendance_summary" class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-xs text-slate-400 space-y-1">
          <div class="font-semibold text-slate-300">Attendance Summary</div>
          <div>{{ detailReportCard.attendance_summary }}</div>
        </div>

        <!-- Actions -->
        <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
          <button
            @click="downloadPdf(detailReportCard)"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg text-xs transition inline-flex items-center gap-1.5"
          >
            <span>📄 Download Official PDF</span>
          </button>
          <div class="flex items-center gap-2">
            <button
              v-if="detailReportCard.status === 'draft'"
              @click="publishCard(detailReportCard.id)"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg text-xs transition"
            >
              Publish Report Card
            </button>
            <button
              @click="detailReportCard = null"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-lg text-xs transition"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useGradingStore } from '../stores/grading';
import { useModalStore } from '../stores/modal';

const gradingStore = useGradingStore();
const modalStore = useModalStore();

const activeTab = ref('entry');
const selectedSectionId = ref(null);
const selectedSubjectId = ref(null);
const selectedExamId = ref(null);

const reportSectionId = ref(null);
const reportTermId = ref(null);

const gradingRoster = ref(null);
const rosterInputs = ref([]);
const detailReportCard = ref(null);

const showCreateExamModal = ref(false);
const showCreateScaleModal = ref(false);

const newExam = reactive({
  name: '',
  type: 'midterm',
  weight: 50,
  max_marks: 100,
  academic_year_id: null,
  term_id: null,
  grade_level_id: null,
});

const newScale = reactive({
  name: '',
  scale_type: 'letter',
  is_default: false,
  rules: [
    { min_score: 90, max_score: 100, grade: 'A', gpa_point: 4.0, description: 'Excellent' },
    { min_score: 80, max_score: 89.9, grade: 'B', gpa_point: 3.0, description: 'Good' },
    { min_score: 70, max_score: 79.9, grade: 'C', gpa_point: 2.0, description: 'Satisfactory' },
    { min_score: 60, max_score: 69.9, grade: 'D', gpa_point: 1.0, description: 'Pass' },
    { min_score: 0, max_score: 59.9, grade: 'F', gpa_point: 0.0, description: 'Fail' },
  ],
});

onMounted(async () => {
  await Promise.all([
    gradingStore.fetchSections(),
    gradingStore.fetchSubjects(),
    gradingStore.fetchExams(),
    gradingStore.fetchTerms(),
    gradingStore.fetchAcademicYears(),
    gradingStore.fetchGradeLevels(),
    gradingStore.fetchGradingScales(),
  ]);

  if (gradingStore.sections.length > 0) {
    selectedSectionId.value = gradingStore.sections[0].id;
    reportSectionId.value = gradingStore.sections[0].id;
  }
  if (gradingStore.subjects.length > 0) {
    selectedSubjectId.value = gradingStore.subjects[0].id;
  }
  if (gradingStore.exams.length > 0) {
    selectedExamId.value = gradingStore.exams[0].id;
  }
  if (gradingStore.terms.length > 0) {
    const active = gradingStore.terms.find((t) => t.is_active);
    reportTermId.value = active ? active.id : gradingStore.terms[0].id;
  }

  // Pre-fill exam modal defaults
  if (gradingStore.academicYears.length > 0) {
    const activeYr = gradingStore.academicYears.find((y) => y.is_active) || gradingStore.academicYears[0];
    newExam.academic_year_id = activeYr.id;
  }
  if (gradingStore.terms.length > 0) {
    newExam.term_id = gradingStore.terms[0].id;
  }
  if (gradingStore.gradeLevels.length > 0) {
    newExam.grade_level_id = gradingStore.gradeLevels[0].id;
  }

  if (selectedSectionId.value && selectedSubjectId.value && selectedExamId.value) {
    await loadGradingRoster();
  }
});

function switchTab(tab) {
  activeTab.value = tab;
  if (tab === 'reports' && reportSectionId.value && !gradingStore.sectionReportCards) {
    loadSectionReportCards();
  }
}

async function onSectionChange() {
  const sec = gradingStore.sections.find((s) => s.id === selectedSectionId.value);
  if (sec && sec.grade_level_id) {
    await gradingStore.fetchSubjects(sec.grade_level_id);
    if (!gradingStore.subjects.some((s) => s.id === selectedSubjectId.value)) {
      selectedSubjectId.value = gradingStore.subjects[0]?.id || null;
    }
  }
  if (selectedSectionId.value && selectedSubjectId.value && selectedExamId.value) {
    await loadGradingRoster();
  }
}

async function onSubjectChange() {
  if (selectedSectionId.value && selectedSubjectId.value && selectedExamId.value) {
    await loadGradingRoster();
  }
}

async function onExamChange() {
  if (selectedSectionId.value && selectedSubjectId.value && selectedExamId.value) {
    await loadGradingRoster();
  }
}

async function loadGradingRoster() {
  if (!selectedSectionId.value || !selectedSubjectId.value || !selectedExamId.value) return;

  try {
    const data = await gradingStore.fetchSectionSubjectGrades(
      selectedSectionId.value,
      selectedSubjectId.value,
      selectedExamId.value
    );
    gradingRoster.value = data;
    rosterInputs.value = (data.roster || []).map((r) => ({
      student_id: r.student_id,
      name: r.name,
      admission_number: r.admission_number,
      marks_obtained: r.marks_obtained !== null && r.marks_obtained !== undefined ? Number(r.marks_obtained) : null,
      max_marks: Number(r.max_marks || data.exam?.max_marks || 100),
      remarks: r.remarks || '',
      is_entered: !!r.is_entered,
    }));
  } catch (err) {
    // Error captured in store
  }
}

function calculatePct(item) {
  if (item.marks_obtained === null || item.marks_obtained === undefined || item.marks_obtained === '') {
    return null;
  }
  if (!item.max_marks || item.max_marks <= 0) return '0.0';
  return ((Number(item.marks_obtained) / Number(item.max_marks)) * 100).toFixed(1);
}

function getPercentageBadgeClass(item) {
  const pctStr = calculatePct(item);
  if (pctStr === null) return 'bg-slate-800 text-slate-400';
  const pct = Number(pctStr);
  if (pct >= 90) return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
  if (pct >= 75) return 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20';
  if (pct >= 60) return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
  return 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
}

function quickFill(score) {
  rosterInputs.value.forEach((item) => {
    item.marks_obtained = score;
  });
}

function clearMarks() {
  rosterInputs.value.forEach((item) => {
    item.marks_obtained = null;
    item.remarks = '';
  });
}

async function submitGrades() {
  const validGrades = rosterInputs.value
    .filter((r) => r.marks_obtained !== null && r.marks_obtained !== undefined && r.marks_obtained !== '')
    .map((r) => ({
      student_id: r.student_id,
      marks_obtained: Number(r.marks_obtained),
      max_marks: Number(r.max_marks),
      remarks: r.remarks || null,
    }));

  if (validGrades.length === 0) {
    modalStore.alert('Please enter marks for at least one student before saving.', { type: 'warning' });
    return;
  }

  const payload = {
    exam_id: selectedExamId.value,
    subject_id: selectedSubjectId.value,
    section_id: selectedSectionId.value,
    grades: validGrades,
  };

  const res = await gradingStore.recordGrades(payload);
  if (res.success) {
    modalStore.toast(`Saved grades for ${validGrades.length} students & updated standings!`, 'success');
  } else {
    modalStore.alert(res.error || 'Failed to save grades.', { type: 'error' });
  }
}

async function loadSectionReportCards() {
  if (!reportSectionId.value) return;
  const res = await gradingStore.fetchSectionReportCards(reportSectionId.value, reportTermId.value);
  if (!res.success) {
    modalStore.toast(res.error || 'Failed to load report cards.', 'error');
  }
}

function calculateClassAverage() {
  const cards = gradingStore.sectionReportCards?.report_cards || [];
  if (cards.length === 0) return '0.0';
  const sum = cards.reduce((acc, c) => acc + Number(c.average_percentage || 0), 0);
  return (sum / cards.length).toFixed(1);
}

function getTopStudentName() {
  const cards = gradingStore.sectionReportCards?.report_cards || [];
  if (cards.length === 0) return 'None';
  const top = cards.find((c) => c.rank_in_section === 1) || cards[0];
  return top?.student?.user?.name || 'Top Student';
}

function countSubmittedCards() {
  const cards = gradingStore.sectionReportCards?.report_cards || [];
  return cards.filter((c) => c.all_teachers_submitted).length;
}

function countPublishedCards() {
  const cards = gradingStore.sectionReportCards?.report_cards || [];
  return cards.filter((c) => c.status === 'published').length;
}

async function publishCard(cardId) {
  const confirmed = await modalStore.confirm({
    title: 'Publish Report Card',
    message: 'Publishing this report card will immediately make it visible to the student and linked parents. Proceed?',
    confirmText: 'Yes, Publish',
  });

  if (confirmed) {
    const res = await gradingStore.publishReportCard(cardId, 'Approved by academic board.');
    if (res.success) {
      modalStore.toast('Report card published successfully!', 'success');
      await loadSectionReportCards();
      if (detailReportCard.value && detailReportCard.value.id === cardId) {
        detailReportCard.value.status = 'published';
      }
    } else {
      modalStore.alert(res.error || 'Failed to publish report card.', { type: 'error' });
    }
  }
}

async function bulkPublish() {
  if (!reportSectionId.value) return;

  const count = gradingStore.sectionReportCards?.report_cards?.length || 0;
  const confirmed = await modalStore.confirm({
    title: 'Bulk Publish Section Report Cards',
    message: `Are you sure you want to publish all ${count} report cards for this section? Students and parents will be notified.`,
    confirmText: 'Publish All',
  });

  if (confirmed) {
    const res = await gradingStore.bulkPublishSection(reportSectionId.value, reportTermId.value);
    if (res.success) {
      modalStore.toast(res.message || 'Section report cards published successfully!', 'success');
      await loadSectionReportCards();
    } else {
      modalStore.alert(res.error || 'Failed to bulk publish report cards.', { type: 'error' });
    }
  }
}

async function viewReportCardDetails(cardId) {
  const res = await gradingStore.fetchReportCard(cardId);
  if (res.success) {
    detailReportCard.value = res.data;
  } else {
    modalStore.alert(res.error || 'Failed to load report card details.', { type: 'error' });
  }
}

async function downloadPdf(rc) {
  const filename = `ReportCard_${rc.student?.admission_number || 'Student'}.pdf`;
  modalStore.toast('Generating report card PDF...', 'info');
  const res = await gradingStore.downloadReportCardPdf(rc.id, filename);
  if (!res.success) {
    modalStore.alert(res.error || 'Failed to download report card PDF.', { type: 'error' });
  }
}

async function handleCreateExam() {
  if (!newExam.name || !newExam.grade_level_id || !newExam.term_id || !newExam.academic_year_id) {
    modalStore.alert('Please fill out all required fields for the assessment.', { type: 'warning' });
    return;
  }

  const res = await gradingStore.createExam({
    ...newExam,
    name: newExam.name.trim(),
    weight: Number(newExam.weight),
    max_marks: Number(newExam.max_marks),
  });

  if (res.success) {
    modalStore.toast(`Assessment "${newExam.name}" created successfully!`, 'success');
    showCreateExamModal.value = false;
    selectedExamId.value = res.data.id;
    newExam.name = '';
    if (selectedSectionId.value && selectedSubjectId.value) {
      await loadGradingRoster();
    }
  } else {
    modalStore.alert(res.error || 'Failed to create assessment.', { type: 'error' });
  }
}

async function handleDeleteExam(exam) {
  const confirmed = await modalStore.confirm({
    title: 'Delete Assessment',
    message: `Are you sure you want to delete "${exam.name}"? Recorded grades for this exam may be removed.`,
    confirmText: 'Delete Assessment',
    destructive: true,
  });

  if (confirmed) {
    const res = await gradingStore.deleteExam(exam.id);
    if (res.success) {
      modalStore.toast('Assessment removed successfully.', 'info');
      if (selectedExamId.value === exam.id) {
        selectedExamId.value = gradingStore.exams[0]?.id || null;
      }
    } else {
      modalStore.alert(res.error || 'Failed to delete assessment.', { type: 'error' });
    }
  }
}

function addScaleRule() {
  const last = newScale.rules[newScale.rules.length - 1];
  const nextMin = last ? Math.max(0, last.min_score - 10) : 50;
  const nextMax = last ? last.min_score - 0.1 : 59.9;
  newScale.rules.push({
    min_score: nextMin,
    max_score: nextMax,
    grade: 'E',
    gpa_point: 0.5,
    description: 'Below Average',
  });
}

function removeScaleRule(index) {
  if (newScale.rules.length > 1) {
    newScale.rules.splice(index, 1);
  }
}

async function handleCreateGradingScale() {
  if (!newScale.name) {
    modalStore.alert('Please provide a name for the grading scale.', { type: 'warning' });
    return;
  }

  const res = await gradingStore.createGradingScale({
    name: newScale.name.trim(),
    scale_type: newScale.scale_type,
    is_default: !!newScale.is_default,
    rules: newScale.rules,
  });

  if (res.success) {
    modalStore.toast(`Grading scale "${newScale.name}" configured successfully!`, 'success');
    showCreateScaleModal.value = false;
    newScale.name = '';
  } else {
    modalStore.alert(res.error || 'Failed to create grading scale.', { type: 'error' });
  }
}

async function handleDeleteScale(scale) {
  const confirmed = await modalStore.confirm({
    title: 'Delete Grading Scale',
    message: `Are you sure you want to remove "${scale.name}"?`,
    confirmText: 'Delete Scale',
    destructive: true,
  });

  if (confirmed) {
    const res = await gradingStore.deleteGradingScale(scale.id);
    if (res.success) {
      modalStore.toast('Grading scale removed.', 'info');
    } else {
      modalStore.alert(res.error || 'Failed to delete grading scale.', { type: 'error' });
    }
  }
}
</script>
