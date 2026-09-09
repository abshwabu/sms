<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Academic Structure &amp; Terms</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            Calendar &amp; Hierarchy
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Model academic years, terms, ordered grade levels, and homeroom sections with full lifecycle control.
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <button 
          v-if="authStore.isSchoolAdmin"
          @click="showCreateTermModal = true"
          class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-md shadow-indigo-600/20 inline-flex items-center gap-1.5"
        >
          <span>🗓️ + Add Academic Term</span>
        </button>
        <button 
          v-if="authStore.isSchoolAdmin"
          @click="showCreateYearModal = true"
          class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl transition border border-slate-700/80 inline-flex items-center gap-1.5"
        >
          <span>📅 + Add Year</span>
        </button>
        <button 
          @click="academicStore.fetchAll()"
          class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white text-xs font-semibold rounded-xl border border-slate-800 transition"
        >
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Active Academic Session Banner -->
    <div 
      class="rounded-2xl p-6 border flex flex-col lg:flex-row lg:items-center justify-between gap-6 shadow-sm"
      :class="academicStore.activeYear ? 'bg-indigo-950/40 border-indigo-500/30' : 'bg-slate-900/90 border-slate-800'"
    >
      <div class="space-y-2">
        <div class="text-xs font-mono uppercase text-indigo-400 font-semibold tracking-wider flex items-center gap-2">
          <span>Active Academic Session</span>
          <span v-if="academicStore.activeYear" class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        </div>
        
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
          <div v-if="academicStore.activeYear" class="text-xl font-bold text-white flex items-center gap-2">
            <span>📅 {{ academicStore.activeYear.name }}</span>
            <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-medium">Active Year</span>
          </div>
          <div v-else class="text-lg font-bold text-slate-400">No Active Academic Year</div>

          <!-- Active Term Badge -->
          <div v-if="academicStore.activeTerm" class="flex items-center gap-1.5 px-3 py-1 rounded-xl bg-indigo-500/20 border border-indigo-500/30 text-indigo-200 text-xs font-semibold">
            <span>★ Active Term:</span>
            <span class="text-white">{{ academicStore.activeTerm.name }}</span>
            <span class="text-[11px] text-indigo-300/80 font-mono">({{ formatDate(academicStore.activeTerm.start_date) }} &rarr; {{ formatDate(academicStore.activeTerm.end_date) }})</span>
          </div>
          <div v-else-if="academicStore.activeYear" class="text-xs text-amber-400 bg-amber-500/10 border border-amber-500/20 px-3 py-1 rounded-xl">
            ⚠️ No active term set. Click "Activate" on a term below.
          </div>
        </div>

        <div v-if="academicStore.activeYear" class="text-xs text-slate-400 flex items-center gap-3 flex-wrap">
          <span>{{ formatDate(academicStore.activeYear.start_date) }} &rarr; {{ formatDate(academicStore.activeYear.end_date) }}</span>
          <span>&bull;</span>
          <span class="text-slate-300 font-semibold">{{ academicStore.terms.length }} Total Terms</span>
          <span>&bull;</span>
          <span class="text-slate-300 font-semibold">{{ academicStore.gradeLevels.length }} Grade Levels</span>
          <span>&bull;</span>
          <span class="text-slate-300 font-semibold">{{ academicStore.sections.length }} Sections</span>
        </div>
      </div>

      <div v-if="academicStore.activeYear && authStore.isSchoolAdmin" class="flex items-center gap-3">
        <button 
          @click="showCreateTermModal = true"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-sm whitespace-nowrap"
        >
          + Add Term to Year
        </button>
        <button 
          @click="handleCloseYear(academicStore.activeYear.id)"
          class="px-4 py-2 bg-amber-950/50 hover:bg-amber-900/70 border border-amber-700/60 text-amber-200 text-xs font-semibold rounded-xl transition whitespace-nowrap"
        >
          🔒 Close Year
        </button>
      </div>
    </div>

    <!-- Academic Structure Grid (4 Columns) -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
      
      <!-- 1. ACADEMIC YEARS -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm flex flex-col">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-base">📅</span>
            <h2 class="text-sm font-bold text-white">Academic Years</h2>
          </div>
          <span class="text-xs font-mono text-slate-400">{{ academicStore.academicYears.length }} Total</span>
        </div>

        <!-- Add Year Form -->
        <div v-if="authStore.isSchoolAdmin" class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2.5">
          <div class="text-xs font-bold text-slate-300">Add Academic Year</div>
          <div class="space-y-2 text-xs">
            <input 
              v-model="newYear.name"
              type="text" 
              placeholder="Year Name (e.g. 2026/2027)" 
              class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] text-slate-500 mb-0.5">Start Date</label>
                <input v-model="newYear.start_date" type="date" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 text-white text-xs focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-[10px] text-slate-500 mb-0.5">End Date</label>
                <input v-model="newYear.end_date" type="date" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 text-white text-xs focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <button 
              @click="handleCreateYear"
              class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-semibold transition"
            >
              + Save Year
            </button>
          </div>
        </div>

        <!-- Years List -->
        <div class="space-y-2.5 flex-1 overflow-y-auto max-h-[500px] pr-1">
          <div 
            v-for="year in academicStore.academicYears" 
            :key="year.id"
            class="p-3.5 rounded-xl border flex flex-col gap-2 transition"
            :class="year.is_closed ? 'bg-slate-950/40 border-slate-800/80 text-slate-400' : (year.is_active ? 'bg-indigo-950/20 border-indigo-500/30' : 'bg-slate-950 border-slate-800 text-slate-200')"
          >
            <div class="flex items-center justify-between">
              <div class="font-bold text-sm text-white flex items-center gap-1.5">
                <span>{{ year.name }}</span>
                <span 
                  v-if="year.is_closed" 
                  class="text-[10px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-400 border border-slate-700 font-mono"
                >
                  🔒 Closed
                </span>
                <span 
                  v-else-if="year.is_active" 
                  class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono"
                >
                  Active
                </span>
              </div>

              <button 
                v-if="!year.is_closed && !year.is_active && authStore.isSchoolAdmin"
                @click="handleActivateYear(year.id)"
                class="px-2 py-1 text-[11px] bg-slate-800 hover:bg-indigo-600 text-white rounded transition"
              >
                Activate
              </button>
            </div>

            <div class="text-[11px] text-slate-400 flex items-center justify-between">
              <span>{{ formatDate(year.start_date) }} &rarr; {{ formatDate(year.end_date) }}</span>
              <span class="text-indigo-400 font-mono">{{ year.terms_count || 0 }} terms</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. ACADEMIC TERMS (FIRST-CLASS) -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm flex flex-col">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-base">🗓️</span>
            <h2 class="text-sm font-bold text-white">Academic Terms</h2>
          </div>
          <button
            v-if="authStore.isSchoolAdmin"
            @click="showCreateTermModal = true"
            class="text-indigo-400 hover:text-indigo-300 text-xs font-semibold"
          >
            + Add Term
          </button>
        </div>

        <!-- Add Term Form -->
        <div v-if="authStore.isSchoolAdmin" class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2.5">
          <div class="text-xs font-bold text-slate-300">Add Academic Term</div>
          <div class="space-y-2 text-xs">
            <select 
              v-model="newTerm.academic_year_id"
              class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white focus:outline-none focus:border-indigo-500"
            >
              <option :value="''" disabled>Select Academic Year...</option>
              <option v-for="y in academicStore.academicYears" :key="y.id" :value="y.id">
                {{ y.name }} {{ y.is_active ? '(Active)' : '' }} {{ y.is_closed ? '(Closed)' : '' }}
              </option>
            </select>

            <input 
              v-model="newTerm.name"
              type="text" 
              placeholder="Term Name (e.g. Fall Semester, Term 1)" 
              class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] text-slate-500 mb-0.5">Start Date</label>
                <input v-model="newTerm.start_date" type="date" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 text-white text-xs focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-[10px] text-slate-500 mb-0.5">End Date</label>
                <input v-model="newTerm.end_date" type="date" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 text-white text-xs focus:outline-none focus:border-indigo-500" />
              </div>
            </div>

            <div class="flex items-center gap-2 pt-0.5">
              <input v-model="newTerm.is_active" type="checkbox" id="termActiveCheckbox" class="w-3.5 h-3.5 rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500" />
              <label for="termActiveCheckbox" class="text-[11px] text-slate-300">Set as active term</label>
            </div>

            <button 
              @click="handleCreateTerm"
              class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-semibold transition"
            >
              + Save Academic Term
            </button>
          </div>
        </div>

        <!-- Term Filter -->
        <div class="flex items-center justify-between text-xs text-slate-400">
          <span>Filter by Year:</span>
          <select 
            v-model="termFilterYearId"
            class="bg-slate-950 border border-slate-800 rounded px-2 py-1 text-white text-xs focus:outline-none"
          >
            <option :value="null">All Years</option>
            <option v-for="y in academicStore.academicYears" :key="y.id" :value="y.id">
              {{ y.name }}
            </option>
          </select>
        </div>

        <!-- Terms List -->
        <div class="space-y-2.5 flex-1 overflow-y-auto max-h-[500px] pr-1">
          <div v-if="filteredTerms.length === 0" class="p-6 text-center text-xs text-slate-500 bg-slate-950/40 rounded-xl border border-slate-800/60">
            No terms found. Add one above.
          </div>

          <div 
            v-for="term in filteredTerms" 
            :key="term.id"
            class="p-3.5 rounded-xl border flex flex-col gap-2 transition"
            :class="term.is_active ? 'bg-indigo-950/20 border-indigo-500/30' : 'bg-slate-950 border-slate-800 text-slate-200'"
          >
            <div class="flex items-center justify-between">
              <div class="font-bold text-xs text-white flex items-center gap-1.5">
                <span>{{ term.name }}</span>
                <span 
                  v-if="term.is_active" 
                  class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono"
                >
                  Active
                </span>
              </div>

              <div class="flex items-center gap-1.5">
                <button 
                  v-if="!term.is_active && authStore.isSchoolAdmin"
                  @click="handleActivateTerm(term.id)"
                  class="px-2 py-0.5 text-[10px] bg-slate-800 hover:bg-indigo-600 text-white rounded transition"
                  title="Make this term active"
                >
                  Activate
                </button>
                <button 
                  v-if="authStore.isSchoolAdmin"
                  @click="handleDeleteTerm(term)"
                  class="text-rose-400 hover:text-rose-300 text-xs px-1"
                  title="Delete term"
                >
                  ✕
                </button>
              </div>
            </div>

            <div class="text-[11px] text-slate-400 flex items-center justify-between">
              <span>{{ formatDate(term.start_date) }} &rarr; {{ formatDate(term.end_date) }}</span>
              <span class="text-slate-400 font-mono text-[10px] bg-slate-800 px-1.5 py-0.5 rounded">
                {{ term.academic_year?.name || 'Year' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. GRADE LEVELS -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm flex flex-col">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-base">🏫</span>
            <h2 class="text-sm font-bold text-white">Grade Levels</h2>
          </div>
          <span class="text-xs font-mono text-slate-400">{{ academicStore.gradeLevels.length }} Total</span>
        </div>

        <!-- Add Grade Level Form -->
        <div v-if="authStore.isSchoolAdmin" class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2.5">
          <div class="text-xs font-bold text-slate-300">Add Grade Level</div>
          <div class="space-y-2 text-xs">
            <input 
              v-model="newGrade.name"
              type="text" 
              placeholder="Grade Name (e.g. Grade 9)" 
              class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
            <div class="grid grid-cols-2 gap-2">
              <input v-model="newGrade.code" type="text" placeholder="Code (G9)" class="bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500" />
              <input v-model.number="newGrade.sequence" type="number" placeholder="Order" class="bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500" />
            </div>
            <button 
              @click="handleCreateGrade"
              class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-semibold transition"
            >
              + Save Grade Level
            </button>
          </div>
        </div>

        <!-- Grade Levels List -->
        <div class="space-y-2 flex-1 overflow-y-auto max-h-[500px] pr-1">
          <div 
            v-for="grade in academicStore.gradeLevels" 
            :key="grade.id"
            class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-lg bg-slate-800 text-xs font-bold text-indigo-300 flex items-center justify-center font-mono border border-slate-700">
                {{ grade.sequence }}
              </span>
              <div>
                <div class="text-xs font-bold text-white">{{ grade.name }}</div>
                <div class="text-[11px] text-slate-400 font-mono">Code: {{ grade.code }}</div>
              </div>
            </div>
            <span class="text-[11px] px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-300 font-mono">
              {{ grade.sections_count || 0 }} sections
            </span>
          </div>
        </div>
      </div>

      <!-- 4. SECTIONS & HOMEROOMS -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm flex flex-col">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-base">👥</span>
            <h2 class="text-sm font-bold text-white">Sections &amp; Classes</h2>
          </div>
          <span class="text-xs font-mono text-slate-400">{{ academicStore.sections.length }} Total</span>
        </div>

        <!-- Add Section Form -->
        <div v-if="authStore.isSchoolAdmin" class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2.5">
          <div class="text-xs font-bold text-slate-300">Create Section</div>
          <div class="space-y-2 text-xs">
            <select v-model="newSection.academic_year_id" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white focus:outline-none focus:border-indigo-500">
              <option :value="''" disabled>Select Academic Year...</option>
              <option v-for="y in academicStore.academicYears" :key="y.id" :value="y.id">
                {{ y.name }} {{ y.is_closed ? '(Closed)' : '' }}
              </option>
            </select>
            <select v-model="newSection.grade_level_id" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white focus:outline-none focus:border-indigo-500">
              <option :value="''" disabled>Select Grade Level...</option>
              <option v-for="g in academicStore.gradeLevels" :key="g.id" :value="g.id">
                {{ g.name }} ({{ g.code }})
              </option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <input 
                v-model="newSection.name"
                type="text" 
                placeholder="Name (e.g. Section A)" 
                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
              <input 
                v-model.number="newSection.capacity" 
                type="number" 
                placeholder="Capacity" 
                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              />
            </div>
            <button 
              @click="handleCreateSection"
              class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-semibold transition"
            >
              + Save Section
            </button>
          </div>
        </div>

        <!-- Sections List -->
        <div class="space-y-2.5 flex-1 overflow-y-auto max-h-[500px] pr-1">
          <div 
            v-for="sec in academicStore.sections" 
            :key="sec.id"
            class="p-3.5 bg-slate-950 border border-slate-800 rounded-xl space-y-1.5"
          >
            <div class="flex items-center justify-between">
              <div class="text-xs font-bold text-white">{{ sec.name }}</div>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
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
              <span>Capacity:</span>
              <span class="font-mono text-slate-300">{{ sec.student_assignments_count || 0 }} / {{ sec.capacity }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD ACADEMIC TERM -->
    <div v-if="showCreateTermModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
              <span>🗓️ Add Academic Term</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Define a semester, term, or trimester for an academic year.</p>
          </div>
          <button @click="showCreateTermModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitCreateTermModal" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Academic Year *</label>
            <select
              v-model="modalTerm.academic_year_id"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            >
              <option :value="''" disabled>Select academic year...</option>
              <option v-for="y in academicStore.academicYears" :key="y.id" :value="y.id">
                {{ y.name }} {{ y.is_active ? '(Active Year)' : '' }} {{ y.is_closed ? '(Closed)' : '' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Term Name *</label>
            <input
              v-model="modalTerm.name"
              type="text"
              placeholder="e.g. Fall Semester, Spring Semester, Term 1"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Start Date *</label>
              <input
                v-model="modalTerm.start_date"
                type="date"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500 text-xs"
              />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">End Date *</label>
              <input
                v-model="modalTerm.end_date"
                type="date"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500 text-xs"
              />
            </div>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input
              v-model="modalTerm.is_active"
              type="checkbox"
              id="modalTermActive"
              class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500"
            />
            <label for="modalTermActive" class="text-slate-300 text-xs">Set as currently active term for the academic year</label>
          </div>

          <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showCreateTermModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-lg transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition shadow-md shadow-indigo-600/20"
            >
              Save Academic Term
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: ADD ACADEMIC YEAR -->
    <div v-if="showCreateYearModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
              <span>📅 Add Academic Year</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Define a new annual school session.</p>
          </div>
          <button @click="showCreateYearModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitCreateYearModal" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Academic Year Name *</label>
            <input
              v-model="modalYear.name"
              type="text"
              placeholder="e.g. 2026/2027"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Start Date *</label>
              <input
                v-model="modalYear.start_date"
                type="date"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500 text-xs"
              />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">End Date *</label>
              <input
                v-model="modalYear.end_date"
                type="date"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500 text-xs"
              />
            </div>
          </div>

          <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showCreateYearModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-lg transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition shadow-md shadow-indigo-600/20"
            >
              Save Academic Year
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAcademicStore } from '../stores/academic';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';

const academicStore = useAcademicStore();
const authStore = useAuthStore();
const modalStore = useModalStore();

const showCreateTermModal = ref(false);
const showCreateYearModal = ref(false);
const termFilterYearId = ref(null);

const newYear = reactive({ name: '', start_date: '', end_date: '' });
const newGrade = reactive({ name: '', code: '', sequence: 1 });
const newSection = reactive({ academic_year_id: '', grade_level_id: '', name: '', capacity: 30 });

const newTerm = reactive({
  academic_year_id: '',
  name: '',
  start_date: '',
  end_date: '',
  is_active: false,
});

const modalTerm = reactive({
  academic_year_id: '',
  name: '',
  start_date: '',
  end_date: '',
  is_active: true,
});

const modalYear = reactive({
  name: '',
  start_date: '',
  end_date: '',
});

const filteredTerms = computed(() => {
  if (!termFilterYearId.value) return academicStore.terms;
  return academicStore.terms.filter((t) => t.academic_year_id === termFilterYearId.value);
});

function formatDate(d) {
  if (!d) return '—';
  try {
    return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  } catch (e) {
    return d;
  }
}

async function handleCreateYear() {
  if (!newYear.name || !newYear.start_date || !newYear.end_date) {
    modalStore.alert('Please provide year name, start date, and end date.', { type: 'warning' });
    return;
  }
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

async function submitCreateYearModal() {
  if (!modalYear.name || !modalYear.start_date || !modalYear.end_date) {
    modalStore.alert('Please fill out all required fields.', { type: 'warning' });
    return;
  }
  try {
    await academicStore.createAcademicYear({ ...modalYear });
    modalYear.name = '';
    modalYear.start_date = '';
    modalYear.end_date = '';
    showCreateYearModal.value = false;
    modalStore.toast('Academic year created successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to create academic year.', { type: 'error' });
  }
}

async function handleCreateTerm() {
  if (!newTerm.academic_year_id || !newTerm.name || !newTerm.start_date || !newTerm.end_date) {
    modalStore.alert('Please select an academic year and provide term name and date range.', { type: 'warning' });
    return;
  }
  try {
    await academicStore.createTerm({ ...newTerm });
    newTerm.name = '';
    newTerm.start_date = '';
    newTerm.end_date = '';
    newTerm.is_active = false;
    modalStore.toast('Academic term created successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to create academic term.', { type: 'error' });
  }
}

async function submitCreateTermModal() {
  if (!modalTerm.academic_year_id || !modalTerm.name || !modalTerm.start_date || !modalTerm.end_date) {
    modalStore.alert('Please fill out all required fields for the term.', { type: 'warning' });
    return;
  }
  try {
    await academicStore.createTerm({ ...modalTerm });
    modalTerm.name = '';
    modalTerm.start_date = '';
    modalTerm.end_date = '';
    showCreateTermModal.value = false;
    modalStore.toast('Academic term created successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to create academic term.', { type: 'error' });
  }
}

async function handleActivateTerm(termId) {
  try {
    await academicStore.activateTerm(termId);
    modalStore.toast('Academic term activated successfully!', 'success');
  } catch (err) {
    modalStore.alert(err.response?.data?.error?.message || 'Failed to activate term.', { type: 'error' });
  }
}

async function handleDeleteTerm(term) {
  const confirmed = await modalStore.confirm({
    title: 'Delete Academic Term',
    message: `Are you sure you want to delete "${term.name}"? Assessments and timetables tied to this term may be affected.`,
    confirmText: 'Delete Term',
    destructive: true,
  });

  if (confirmed) {
    try {
      await academicStore.deleteTerm(term.id);
      modalStore.toast('Academic term removed.', 'info');
    } catch (err) {
      modalStore.alert(err.response?.data?.error?.message || 'Failed to delete term.', { type: 'error' });
    }
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

onMounted(async () => {
  await academicStore.fetchAll();

  if (academicStore.activeYear) {
    newTerm.academic_year_id = academicStore.activeYear.id;
    modalTerm.academic_year_id = academicStore.activeYear.id;
    newSection.academic_year_id = academicStore.activeYear.id;
  } else if (academicStore.academicYears.length > 0) {
    newTerm.academic_year_id = academicStore.academicYears[0].id;
    modalTerm.academic_year_id = academicStore.academicYears[0].id;
    newSection.academic_year_id = academicStore.academicYears[0].id;
  }

  if (academicStore.gradeLevels.length > 0) {
    newSection.grade_level_id = academicStore.gradeLevels[0].id;
  }
});
</script>
