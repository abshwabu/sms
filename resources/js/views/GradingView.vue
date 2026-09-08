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
          Subject-level mark entry, automatic report card aggregation, class rankings, and PDF generation.
        </p>
      </div>

      <!-- Tab Switcher -->
      <div class="flex items-center gap-2 bg-slate-900 border border-slate-800 p-1 rounded-xl">
        <button
          @click="activeTab = 'entry'"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'entry' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
        >
          📝 Grade Entry
        </button>
        <button
          @click="activeTab = 'reports'"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'reports' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
        >
          📊 Report Cards &amp; Standings
        </button>
        <button
          @click="activeTab = 'scales'"
          class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition"
          :class="activeTab === 'scales' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
        >
          ⚙️ Grading Scales &amp; Exams
        </button>
      </div>
    </div>

    <!-- Alert Banners -->
    <div v-if="gradingStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
      <span>{{ gradingStore.error }}</span>
      <button @click="gradingStore.clearMessages" class="text-rose-400 hover:text-rose-200">✕</button>
    </div>
    <div v-if="gradingStore.successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
      <span>{{ gradingStore.successMessage }}</span>
      <button @click="gradingStore.clearMessages" class="text-emerald-400 hover:text-emerald-200">✕</button>
    </div>

    <!-- TAB 1: GRADE ENTRY ROSTER -->
    <div v-if="activeTab === 'entry'" class="space-y-6">
      <!-- Selector Card -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <!-- Section Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Section / Class</label>
            <select
              v-model="selectedSectionId"
              @change="onSectionChange"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null" disabled>Select section...</option>
              <option v-for="sec in gradingStore.sections" :key="sec.id" :value="sec.id">
                {{ sec.name }} ({{ sec.grade_level?.name || 'Grade' }})
              </option>
            </select>
          </div>

          <!-- Subject Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Subject</label>
            <select
              v-model="selectedSubjectId"
              @change="onSubjectChange"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null" disabled>Select subject...</option>
              <option v-for="sub in gradingStore.subjects" :key="sub.id" :value="sub.id">
                {{ sub.name }} ({{ sub.code }})
              </option>
            </select>
          </div>

          <!-- Exam / Assessment Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Assessment / Exam</label>
            <select
              v-model="selectedExamId"
              @change="loadGradingRoster"
              class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null" disabled>Select exam...</option>
              <option v-for="ex in gradingStore.exams" :key="ex.id" :value="ex.id">
                {{ ex.name }} (Weight: {{ ex.weight }}%)
              </option>
            </select>
          </div>
        </div>

        <div class="mt-4 flex items-center justify-between pt-4 border-t border-slate-800 text-xs">
          <div class="text-slate-400">
            Select a section, subject, and assessment to load the roster and enter student scores.
          </div>
          <button
            @click="loadGradingRoster"
            :disabled="!selectedSectionId || !selectedSubjectId || !selectedExamId || gradingStore.loading"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold rounded-lg transition"
          >
            {{ gradingStore.loading ? 'Loading Roster...' : 'Fetch Roster' }}
          </button>
        </div>
      </div>

      <!-- Grading Table -->
      <div v-if="gradingRoster" class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
              <span>{{ gradingRoster.subject.name }} ({{ gradingRoster.subject.code }})</span>
              <span class="text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                {{ gradingRoster.exam ? gradingRoster.exam.name : 'Assessment' }}
              </span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Section: {{ gradingRoster.section.name }} &bull; Max Marks: {{ gradingRoster.exam?.max_marks || 100 }}
            </p>
          </div>

          <!-- Quick Fill Tools -->
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="quickFill(100)"
              class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
            >
              Fill 100
            </button>
            <button
              type="button"
              @click="quickFill(85)"
              class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
            >
              Fill 85
            </button>
            <button
              type="button"
              @click="clearMarks"
              class="px-2.5 py-1 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
            >
              Clear All
            </button>
          </div>
        </div>

        <form @submit.prevent="submitGrades">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
              <thead class="bg-slate-950 text-slate-400 font-semibold border-b border-slate-800 uppercase tracking-wider">
                <tr>
                  <th class="p-3.5">Student</th>
                  <th class="p-3.5">Admission No</th>
                  <th class="p-3.5">Marks Obtained</th>
                  <th class="p-3.5">Max Marks</th>
                  <th class="p-3.5">Percentage</th>
                  <th class="p-3.5">Teacher Remarks</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-for="item in rosterInputs" :key="item.student_id" class="hover:bg-slate-850/50">
                  <td class="p-3.5 font-medium text-white">{{ item.name }}</td>
                  <td class="p-3.5 font-mono text-slate-400">{{ item.admission_number }}</td>
                  <td class="p-3.5">
                    <input
                      v-model.number="item.marks_obtained"
                      type="number"
                      step="0.5"
                      min="0"
                      :max="item.max_marks"
                      required
                      placeholder="0 - 100"
                      class="w-24 bg-slate-950 border border-slate-700 rounded px-2 py-1 text-sm text-white font-mono focus:ring-2 focus:ring-indigo-500"
                    />
                  </td>
                  <td class="p-3.5 font-mono text-slate-400">{{ item.max_marks }}</td>
                  <td class="p-3.5">
                    <span
                      class="px-2 py-0.5 rounded font-mono font-bold text-xs"
                      :class="getPercentageBadgeClass(item)"
                    >
                      {{ calculatePct(item) }}%
                    </span>
                  </td>
                  <td class="p-3.5">
                    <input
                      v-model="item.remarks"
                      type="text"
                      placeholder="Optional feedback..."
                      class="w-full bg-slate-950 border border-slate-700 rounded px-2.5 py-1 text-xs text-white focus:ring-2 focus:ring-indigo-500"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="p-5 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between">
            <div class="text-xs text-slate-400">
              Saving triggers automatic report card re-aggregation and section ranking recalculation.
            </div>
            <button
              type="submit"
              :disabled="gradingStore.actionLoading"
              class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center gap-2"
            >
              <span>{{ gradingStore.actionLoading ? 'Saving & Aggregating...' : '💾 Save Grades & Aggregate Report Cards' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- TAB 2: REPORT CARDS & STANDINGS -->
    <div v-if="activeTab === 'reports'" class="space-y-6">
      <!-- Section & Term Selector -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col sm:flex-row items-end justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full sm:w-auto">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Section</label>
            <select
              v-model="reportSectionId"
              class="w-64 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-indigo-500"
            >
              <option v-for="sec in gradingStore.sections" :key="sec.id" :value="sec.id">
                {{ sec.name }} ({{ sec.grade_level?.name || 'Grade' }})
              </option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="loadSectionReportCards"
            :disabled="!reportSectionId || gradingStore.loading"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition"
          >
            {{ gradingStore.loading ? 'Loading...' : 'Load Report Cards' }}
          </button>
          <button
            @click="bulkPublish"
            :disabled="!reportSectionId || gradingStore.actionLoading"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg transition"
          >
            📢 Bulk Publish Section
          </button>
        </div>
      </div>

      <!-- Report Cards List -->
      <div v-if="gradingStore.sectionReportCards" class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold text-white">
              Official Report Cards &amp; Ranks: {{ gradingStore.sectionReportCards.section.name }}
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Term: {{ gradingStore.sectionReportCards.term.name }} &bull; Total Students: {{ gradingStore.sectionReportCards.report_cards.length }}
            </p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-semibold border-b border-slate-800 uppercase tracking-wider">
              <tr>
                <th class="p-3.5">Rank</th>
                <th class="p-3.5">Student</th>
                <th class="p-3.5">Admission No</th>
                <th class="p-3.5">Average %</th>
                <th class="p-3.5">Grade</th>
                <th class="p-3.5">GPA</th>
                <th class="p-3.5">All Submitted</th>
                <th class="p-3.5">Status</th>
                <th class="p-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-for="rc in gradingStore.sectionReportCards.report_cards" :key="rc.id" class="hover:bg-slate-850/50">
                <td class="p-3.5">
                  <span class="font-bold text-sm px-2 py-0.5 rounded bg-slate-800 text-indigo-400 font-mono">
                    #{{ rc.rank_in_section || '-' }}
                  </span>
                </td>
                <td class="p-3.5 font-medium text-white">{{ rc.student?.user?.name }}</td>
                <td class="p-3.5 font-mono text-slate-400">{{ rc.student?.admission_number }}</td>
                <td class="p-3.5 font-bold font-mono text-white">{{ rc.average_percentage }}%</td>
                <td class="p-3.5">
                  <span class="font-bold text-sm px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    {{ rc.overall_grade || 'N/A' }}
                  </span>
                </td>
                <td class="p-3.5 font-mono">{{ rc.gpa ? rc.gpa.toFixed(2) : '-' }}</td>
                <td class="p-3.5">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase"
                    :class="rc.all_teachers_submitted ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400'"
                  >
                    {{ rc.all_teachers_submitted ? 'Complete' : 'Pending Teachers' }}
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
                <td class="p-3.5 text-right space-x-2">
                  <button
                    v-if="rc.status === 'draft'"
                    @click="publishCard(rc.id)"
                    class="px-2.5 py-1 text-xs rounded bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition"
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
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Configured Grading Scale -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <h2 class="text-base font-bold text-white mb-3 flex items-center justify-between">
            <span>Configured Grading Scales</span>
            <span class="text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
              School Configurable
            </span>
          </h2>
          <div v-for="scale in gradingStore.gradingScales" :key="scale.id" class="mb-4 last:mb-0">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-300 mb-2">
              <span>{{ scale.name }} ({{ scale.scale_type }})</span>
              <span v-if="scale.is_default" class="text-emerald-400 font-mono text-[10px] bg-emerald-500/10 px-1.5 py-0.5 rounded">Default Scale</span>
            </div>
            <table class="w-full text-left text-xs text-slate-300 border border-slate-800 rounded overflow-hidden">
              <thead class="bg-slate-950 text-slate-400">
                <tr>
                  <th class="p-2">Score Range</th>
                  <th class="p-2">Letter</th>
                  <th class="p-2">GPA</th>
                  <th class="p-2">Description</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-for="rule in scale.rules" :key="rule.grade">
                  <td class="p-2 font-mono">{{ rule.min_score }}% - {{ rule.max_score }}%</td>
                  <td class="p-2 font-bold text-white">{{ rule.grade }}</td>
                  <td class="p-2 font-mono">{{ rule.gpa_point }}</td>
                  <td class="p-2 text-slate-400">{{ rule.description }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Assessments / Exams -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm">
          <h2 class="text-base font-bold text-white mb-3">Assessments &amp; Weights</h2>
          <table class="w-full text-left text-xs text-slate-300 border border-slate-800 rounded overflow-hidden">
            <thead class="bg-slate-950 text-slate-400">
              <tr>
                <th class="p-2.5">Name</th>
                <th class="p-2.5">Type</th>
                <th class="p-2.5">Weight</th>
                <th class="p-2.5">Max Marks</th>
                <th class="p-2.5">Term</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-for="ex in gradingStore.exams" :key="ex.id">
                <td class="p-2.5 font-medium text-white">{{ ex.name }}</td>
                <td class="p-2.5 uppercase font-mono text-[10px] text-indigo-400">{{ ex.type }}</td>
                <td class="p-2.5 font-mono font-bold">{{ ex.weight }}%</td>
                <td class="p-2.5 font-mono">{{ ex.max_marks }}</td>
                <td class="p-2.5 text-slate-400">{{ ex.term?.name || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useGradingStore } from '../stores/grading';

const gradingStore = useGradingStore();

const activeTab = ref('entry');
const selectedSectionId = ref(null);
const selectedSubjectId = ref(null);
const selectedExamId = ref(null);
const reportSectionId = ref(null);

const gradingRoster = ref(null);
const rosterInputs = ref([]);

onMounted(async () => {
    await gradingStore.fetchSections();
    await gradingStore.fetchSubjects();
    await gradingStore.fetchExams();
    await gradingStore.fetchGradingScales();

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

    if (selectedSectionId.value && selectedSubjectId.value && selectedExamId.value) {
        loadGradingRoster();
    }
});

function onSectionChange() {
    // Optionally refresh subjects by section grade level
    const sec = gradingStore.sections.find(s => s.id === selectedSectionId.value);
    if (sec && sec.grade_level_id) {
        gradingStore.fetchSubjects(sec.grade_level_id);
    }
}

function onSubjectChange() {
    // Reload roster if exam also selected
    if (selectedSectionId.value && selectedExamId.value) {
        loadGradingRoster();
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
        rosterInputs.value = (data.roster || []).map(r => ({
            student_id: r.student_id,
            name: r.name,
            admission_number: r.admission_number,
            marks_obtained: r.marks_obtained !== null ? Number(r.marks_obtained) : 0,
            max_marks: Number(r.max_marks || data.exam?.max_marks || 100),
            remarks: r.remarks || '',
        }));
    } catch (e) {
        // error in store
    }
}

function calculatePct(item) {
    if (!item.max_marks || item.max_marks <= 0) return '0.0';
    return ((item.marks_obtained / item.max_marks) * 100).toFixed(1);
}

function getPercentageBadgeClass(item) {
    const pct = Number(calculatePct(item));
    if (pct >= 90) return 'bg-emerald-500/10 text-emerald-400';
    if (pct >= 75) return 'bg-blue-500/10 text-blue-400';
    if (pct >= 60) return 'bg-amber-500/10 text-amber-400';
    return 'bg-rose-500/10 text-rose-400';
}

function quickFill(score) {
    rosterInputs.value.forEach(item => {
        item.marks_obtained = score;
    });
}

function clearMarks() {
    rosterInputs.value.forEach(item => {
        item.marks_obtained = 0;
        item.remarks = '';
    });
}

async function submitGrades() {
    const payload = {
        exam_id: selectedExamId.value,
        subject_id: selectedSubjectId.value,
        section_id: selectedSectionId.value,
        grades: rosterInputs.value.map(r => ({
            student_id: r.student_id,
            marks_obtained: r.marks_obtained,
            max_marks: r.max_marks,
            remarks: r.remarks || null,
        })),
    };

    await gradingStore.recordGrades(payload);
}

async function loadSectionReportCards() {
    if (!reportSectionId.value) return;
    await gradingStore.fetchSectionReportCards(reportSectionId.value);
}

async function publishCard(cardId) {
    await gradingStore.publishReportCard(cardId, 'Approved by academic board.');
    await loadSectionReportCards();
}

async function bulkPublish() {
    if (!reportSectionId.value) return;
    await gradingStore.bulkPublishSection(reportSectionId.value);
    await loadSectionReportCards();
}

async function downloadPdf(rc) {
    await gradingStore.downloadReportCardPdf(rc.id, `ReportCard_${rc.student?.admission_number}.pdf`);
}
</script>
