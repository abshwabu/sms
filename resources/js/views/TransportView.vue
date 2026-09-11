<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Transport Management</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-mono">
            Bus Routes &amp; Stops
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Manage school bus routes, configure ordered pickup/dropoff stops, and assign students individually or by section.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button
          v-if="canManage"
          @click="openBulkAssignModal"
          class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs transition flex items-center gap-1.5 shadow-sm active:scale-[0.98]"
        >
          <span>👥</span>
          <span>Bulk Assign Section</span>
        </button>
        <button
          v-if="canManage"
          @click="openAssignStudentModal"
          class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs border border-slate-700 transition flex items-center gap-1.5 active:scale-[0.98]"
        >
          <span>👤</span>
          <span>Assign Student</span>
        </button>
        <button
          v-if="canManage"
          @click="openCreateRouteModal"
          class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition flex items-center gap-1.5 shadow-sm active:scale-[0.98]"
        >
          <span>+</span>
          <span>New Route</span>
        </button>
      </div>
    </div>

    <!-- Alert / Feedback Banner -->
    <div v-if="transportStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>⚠️</span>
        <span>{{ transportStore.error }}</span>
      </div>
      <button @click="transportStore.clearMessages" class="text-rose-400 hover:text-rose-200">✕</button>
    </div>

    <div v-if="transportStore.successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>✓</span>
        <span>{{ transportStore.successMessage }}</span>
      </div>
      <button @click="transportStore.clearMessages" class="text-emerald-400 hover:text-emerald-200">✕</button>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-slate-400">Total Routes</span>
        <div class="text-xl font-bold text-white mt-1">{{ totalRoutes }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-slate-400">Configured Stops</span>
        <div class="text-xl font-bold text-white mt-1">{{ totalStops }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-amber-400">Assigned Riders</span>
        <div class="text-xl font-bold text-amber-400 mt-1">{{ totalAssigned }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-emerald-400">Available Capacity</span>
        <div class="text-xl font-bold text-emerald-400 mt-1">{{ totalCapacityRemaining }} seats</div>
      </div>
    </div>

    <!-- Mode Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
      <button
        @click="activeTab = 'routes'"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
        :class="activeTab === 'routes' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white bg-slate-900 border border-slate-800'"
      >
        🚌 Bus Routes &amp; Stops
      </button>
      <button
        v-if="canManage"
        @click="activeTab = 'assignments'"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
        :class="activeTab === 'assignments' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white bg-slate-900 border border-slate-800'"
      >
        📋 Student Assignments ({{ transportStore.assignments.length }})
      </button>
      <button
        v-if="canManage"
        @click="activeTab = 'bulk'"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
        :class="activeTab === 'bulk' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white bg-slate-900 border border-slate-800'"
      >
        👥 Bulk Assign Section
      </button>
      <button
        @click="loadMyTransport"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
        :class="activeTab === 'my-bus' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white bg-slate-900 border border-slate-800'"
      >
        {{ authStore.isParent ? '🚏 My Children\'s Bus Route' : '🚏 My Bus Schedule' }}
      </button>
    </div>

    <!-- TAB 1: ROUTES & STOPS -->
    <div v-if="activeTab === 'routes'" class="space-y-6">
      <div v-if="transportStore.loading" class="text-center py-12 text-slate-500 text-sm">
        Loading transport routes...
      </div>

      <div v-else-if="transportStore.routes.length === 0" class="text-center py-12 bg-slate-900/60 border border-slate-800 rounded-2xl">
        <div class="text-3xl mb-2">🚌</div>
        <h3 class="text-sm font-semibold text-white">No bus routes configured yet</h3>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          Add your first school bus route to start organizing stops and assigning student commutes.
        </p>
        <button
          v-if="canManage"
          @click="openCreateRouteModal"
          class="mt-4 px-3.5 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-500 transition"
        >
          Create First Route
        </button>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Routes List Sidebar -->
        <div class="space-y-3">
          <div
            v-for="route in transportStore.routes"
            :key="route.id"
            @click="selectRoute(route)"
            class="p-4 rounded-xl border transition cursor-pointer"
            :class="selectedRoute?.id === route.id ? 'bg-slate-800 border-indigo-500/50 shadow-md ring-1 ring-indigo-500/20' : 'bg-slate-900/80 border-slate-800 hover:border-slate-700'"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <h3 class="font-bold text-sm text-white">{{ route.name }}</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ route.vehicle_info }}</p>
              </div>
              <span
                class="text-[10px] uppercase font-mono px-2 py-0.5 rounded-full border"
                :class="route.status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-slate-700 text-slate-400 border-slate-600'"
              >
                {{ route.status }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-800/80 text-xs">
              <div>
                <span class="text-slate-500 block text-[10px]">Driver</span>
                <span class="text-slate-300 font-medium">{{ route.driver_name }}</span>
              </div>
              <div>
                <span class="text-slate-500 block text-[10px]">Contact</span>
                <span class="text-slate-300 font-mono text-[11px]">{{ route.driver_contact }}</span>
              </div>
            </div>

            <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-800/40 text-[11px] text-slate-400">
              <span>{{ route.stops?.length || route.stops_count || 0 }} stop(s)</span>
              <span>{{ route.student_assignments_count || 0 }} / {{ route.capacity }} seats</span>
            </div>
          </div>
        </div>

        <!-- Route Detail & Ordered Stops (Right 2 cols) -->
        <div class="lg:col-span-2 space-y-4">
          <div v-if="selectedRoute" class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-5">
            <!-- Route Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-800">
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-lg font-bold text-white">{{ selectedRoute.name }}</h2>
                  <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                    Capacity: {{ selectedRoute.capacity }}
                  </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                  {{ selectedRoute.vehicle_info }} &bull; Driver: <strong class="text-slate-200">{{ selectedRoute.driver_name }}</strong> ({{ selectedRoute.driver_contact }})
                </p>
                <p v-if="selectedRoute.description" class="text-xs text-slate-500 mt-1 italic">
                  "{{ selectedRoute.description }}"
                </p>
              </div>

              <div class="flex items-center gap-2">
                <button
                  v-if="canManage"
                  @click="openAddStopModal(selectedRoute)"
                  class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition flex items-center gap-1"
                >
                  <span>+</span>
                  <span>Add Stop</span>
                </button>
                <button
                  v-if="canManage"
                  @click="deleteRoute(selectedRoute)"
                  class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs border border-rose-500/20 transition"
                  title="Delete Route"
                >
                  🗑
                </button>
              </div>
            </div>

            <!-- Ordered Stops Timeline / List -->
            <div>
              <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">
                Ordered Stops Schedule (Sequence 1 to N)
              </h3>

              <div v-if="!selectedRoute.stops || selectedRoute.stops.length === 0" class="p-6 text-center text-slate-500 text-xs bg-slate-950/60 rounded-xl border border-slate-800/80">
                No stops added to this route yet. Click "+ Add Stop" to specify pickup and dropoff locations.
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="(stop, index) in sortedStops"
                  :key="stop.id"
                  class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-950/80 border border-slate-800/90 relative"
                >
                  <!-- Sequence badge -->
                  <div class="w-7 h-7 rounded-full bg-indigo-500/20 border border-indigo-500/40 text-indigo-300 font-bold text-xs flex items-center justify-center shrink-0">
                    {{ stop.sequence }}
                  </div>

                  <!-- Stop info -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                      <h4 class="text-sm font-semibold text-white truncate">{{ stop.stop_name }}</h4>
                      <div class="flex items-center gap-2 shrink-0 text-xs font-mono">
                        <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                          Pickup: {{ stop.pickup_time }}
                        </span>
                        <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">
                          Dropoff: {{ stop.dropoff_time }}
                        </span>
                      </div>
                    </div>
                    <p v-if="stop.landmark" class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                      <span>📍</span>
                      <span>{{ stop.landmark }}</span>
                    </p>
                  </div>

                  <!-- Remove stop action -->
                  <button
                    v-if="canManage"
                    @click="deleteStop(stop.id)"
                    class="text-slate-500 hover:text-rose-400 text-xs transition p-1"
                    title="Delete Stop"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>

            <!-- Assigned Students in this Route -->
            <div class="pt-4 border-t border-slate-800">
              <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                Assigned Students ({{ selectedRoute.student_assignments?.length || 0 }})
              </h3>
              <div v-if="!selectedRoute.student_assignments || selectedRoute.student_assignments.length === 0" class="text-xs text-slate-500 py-3">
                No students currently assigned to this bus route.
              </div>
              <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto pr-1">
                <div
                  v-for="assignment in selectedRoute.student_assignments"
                  :key="assignment.id"
                  class="flex items-center justify-between p-2.5 rounded-lg bg-slate-950/60 border border-slate-800/80 text-xs"
                >
                  <div>
                    <div class="font-medium text-white">{{ assignment.student?.user?.name || 'Student' }}</div>
                    <div class="text-[11px] text-slate-400">
                      {{ assignment.student?.admission_number }} &bull; Stop: {{ assignment.stop?.stop_name }}
                    </div>
                  </div>
                  <button
                    v-if="canManage"
                    @click="unassignStudent(assignment.student_id)"
                    class="text-rose-400 hover:text-rose-300 text-[11px] px-1.5 py-0.5 rounded border border-rose-500/20 bg-rose-500/10"
                  >
                    Unassign
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="p-12 text-center text-slate-500 text-xs bg-slate-900/40 rounded-2xl border border-slate-800">
            Select a route from the left list to view stops and assignments.
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: STUDENT ASSIGNMENTS TABLE -->
    <div v-if="activeTab === 'assignments'" class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-900/90 border border-slate-800 rounded-xl p-4">
        <div class="text-sm font-semibold text-white">Active Student Transport Roster</div>
        <div class="flex items-center gap-2">
          <button
            @click="transportStore.fetchAssignments()"
            class="px-3 py-1.5 text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition"
          >
            ↻ Refresh
          </button>
        </div>
      </div>

      <div class="overflow-x-auto bg-slate-900/90 border border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-950/70 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
            <tr>
              <th class="px-4 py-3">Student</th>
              <th class="px-4 py-3">Admission #</th>
              <th class="px-4 py-3">Section</th>
              <th class="px-4 py-3">Assigned Route</th>
              <th class="px-4 py-3">Pickup / Dropoff Stop</th>
              <th class="px-4 py-3">Pickup Time</th>
              <th class="px-4 py-3">Dropoff Time</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr v-if="transportStore.assignments.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                No active student transport assignments found.
              </td>
            </tr>
            <tr
              v-for="a in transportStore.assignments"
              :key="a.id"
              class="hover:bg-slate-800/40 transition"
            >
              <td class="px-4 py-3 font-semibold text-white">
                {{ a.student?.user?.name || 'N/A' }}
              </td>
              <td class="px-4 py-3 font-mono text-slate-400">
                {{ a.student?.admission_number }}
              </td>
              <td class="px-4 py-3 text-slate-300">
                {{ a.student?.current_section?.name || 'Unassigned' }}
              </td>
              <td class="px-4 py-3 font-medium text-amber-300">
                {{ a.route?.name }}
              </td>
              <td class="px-4 py-3 text-slate-300">
                {{ a.stop?.stop_name }}
              </td>
              <td class="px-4 py-3 font-mono text-emerald-400">
                {{ a.stop?.pickup_time }}
              </td>
              <td class="px-4 py-3 font-mono text-amber-400">
                {{ a.stop?.dropoff_time }}
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  @click="unassignStudent(a.student_id)"
                  class="px-2 py-1 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition font-medium"
                >
                  Unassign
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 3: BULK ASSIGN SECTION (ACCEPTANCE CRITERION 1) -->
    <div v-if="activeTab === 'bulk'" class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-sm max-w-2xl mx-auto space-y-6">
      <div class="border-b border-slate-800 pb-4">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span>👥 Bulk Assign Entire Section Roster</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">
          Select an academic section, a target bus route, and a pickup/dropoff stop to assign all active students at once.
        </p>
      </div>

      <form @submit.prevent="submitBulkAssign" class="space-y-4 text-xs">
        <div>
          <label class="block font-medium text-slate-300 mb-1">Target Section</label>
          <select
            v-model="bulkForm.section_id"
            required
            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
          >
            <option :value="null">-- Select a Section --</option>
            <option v-for="sec in sections" :key="sec.id" :value="sec.id">
              {{ sec.name }} (Capacity: {{ sec.capacity }})
            </option>
          </select>
        </div>

        <div>
          <label class="block font-medium text-slate-300 mb-1">Transport Route</label>
          <select
            v-model="bulkForm.route_id"
            @change="bulkForm.stop_id = null"
            required
            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
          >
            <option :value="null">-- Select Route --</option>
            <option v-for="r in transportStore.routes" :key="r.id" :value="r.id">
              {{ r.name }} ({{ r.vehicle_info }})
            </option>
          </select>
        </div>

        <div v-if="bulkFormRouteStops.length > 0">
          <label class="block font-medium text-slate-300 mb-1">Target Stop</label>
          <select
            v-model="bulkForm.stop_id"
            required
            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
          >
            <option :value="null">-- Select Stop --</option>
            <option v-for="stop in bulkFormRouteStops" :key="stop.id" :value="stop.id">
              Stop #{{ stop.sequence }}: {{ stop.stop_name }} (Pickup: {{ stop.pickup_time }} / Dropoff: {{ stop.dropoff_time }})
            </option>
          </select>
        </div>

        <div v-else-if="bulkForm.route_id" class="p-3 bg-amber-500/10 border border-amber-500/20 text-amber-300 rounded-xl">
          Selected route has no stops configured yet. Please add stops to this route first.
        </div>

        <button
          type="submit"
          :disabled="!bulkForm.section_id || !bulkForm.route_id || !bulkForm.stop_id || transportStore.actionLoading"
          class="w-full py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white font-semibold transition flex items-center justify-center gap-2"
        >
          <span v-if="transportStore.actionLoading">Processing Bulk Assignment...</span>
          <span v-else>Bulk Assign Section Students</span>
        </button>
      </form>
    </div>

    <!-- TAB 4: MY BUS SCHEDULE (STUDENT & PARENT VIEW) -->
    <div v-if="activeTab === 'my-bus'" class="space-y-6 max-w-3xl mx-auto">
      <!-- Parent Linked Child Switcher -->
      <div v-if="authStore.isParent && parentChildren.length > 0" class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="text-xs font-bold text-white flex items-center gap-2">
            <span>👨‍👧‍👦 Child Transport Schedule</span>
            <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
              {{ parentChildren.length }} Linked Student(s)
            </span>
          </div>
          <p class="text-[11px] text-slate-400 mt-0.5">
            Select a child to view their assigned morning pickup and afternoon dropoff bus schedule.
          </p>
        </div>

        <div v-if="parentChildren.length > 1" class="flex items-center gap-1.5 flex-wrap">
          <button
            v-for="child in parentChildren"
            :key="child.id"
            type="button"
            @click="selectParentChild(child)"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5"
            :class="selectedChildId === child.id ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700'"
          >
            <span>👤</span>
            <span>{{ child.user?.name || child.name }}</span>
          </button>
        </div>
      </div>

      <div v-if="transportStore.loading" class="text-center py-12 text-slate-500 text-sm">
        Loading transport information...
      </div>

      <div v-else-if="!transportStore.myTransport || !transportStore.myTransport.has_transport" class="bg-slate-900/90 border border-slate-800 rounded-2xl p-8 text-center space-y-3">
        <div class="text-4xl">🚏</div>
        <h3 class="text-base font-bold text-white">
          {{ authStore.isParent ? `No Bus Route Assigned to ${activeChildName}` : 'No Bus Route Assigned' }}
        </h3>
        <p class="text-xs text-slate-400 max-w-md mx-auto">
          {{ authStore.isParent ? `${activeChildName} currently does not have a school transport route assigned.` : 'You currently do not have a school transport route assigned.' }}
          Please contact the school administration or homeroom teacher for route enrollment.
        </p>
      </div>

      <div v-else class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
          <div>
            <h2 class="text-lg font-bold text-white">{{ transportStore.myTransport.route?.name || transportStore.myTransport.route_name }}</h2>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ transportStore.myTransport.vehicle_info }} &bull; Driver: {{ transportStore.myTransport.driver_name }} ({{ transportStore.myTransport.driver_contact }})
            </p>
          </div>
          <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-semibold">
            {{ authStore.isParent ? `Active Commuter (${activeChildName})` : 'Active Commuter' }}
          </span>
        </div>

        <!-- Assigned Stop Highlight -->
        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <span class="text-[10px] uppercase font-bold text-amber-400 tracking-wider">Your Designated Stop</span>
            <h3 class="text-base font-bold text-white mt-0.5">{{ transportStore.myTransport.stop_name }}</h3>
            <p v-if="transportStore.myTransport.stop?.landmark" class="text-xs text-amber-200/80 mt-1">
              📍 {{ transportStore.myTransport.stop.landmark }}
            </p>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-center px-3 py-1.5 rounded-lg bg-slate-900/80 border border-slate-800">
              <span class="text-[10px] text-slate-400 block uppercase">Pickup Time</span>
              <span class="font-mono text-emerald-400 font-bold text-sm">{{ transportStore.myTransport.pickup_time }}</span>
            </div>
            <div class="text-center px-3 py-1.5 rounded-lg bg-slate-900/80 border border-slate-800">
              <span class="text-[10px] text-slate-400 block uppercase">Dropoff Time</span>
              <span class="font-mono text-amber-400 font-bold text-sm">{{ transportStore.myTransport.dropoff_time }}</span>
            </div>
          </div>
        </div>

        <!-- Full Route Stops Timeline -->
        <div>
          <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">
            Complete Route Stops Timeline
          </h3>
          <div class="space-y-2">
            <div
              v-for="s in transportStore.myTransport.all_route_stops"
              :key="s.id"
              class="flex items-center gap-3 p-3 rounded-xl border transition"
              :class="s.is_assigned_stop || s.is_child_stop ? 'bg-indigo-950/40 border-indigo-500/40 ring-1 ring-indigo-500/20' : 'bg-slate-950/40 border-slate-800/80'"
            >
              <div
                class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center shrink-0"
                :class="s.is_assigned_stop || s.is_child_stop ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400'"
              >
                {{ s.sequence }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-semibold text-white truncate">
                    {{ s.stop_name }}
                    <span v-if="s.is_assigned_stop || s.is_child_stop" class="ml-1 text-[10px] text-indigo-400 font-mono font-bold">(Your Stop)</span>
                  </span>
                  <span class="text-[11px] font-mono text-slate-400">
                    {{ s.pickup_time }} &rarr; {{ s.dropoff_time }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: CREATE ROUTE -->
    <div v-if="showCreateRouteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-sm text-white">Create New Transport Route</h3>
          <button @click="showCreateRouteModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitCreateRoute" class="space-y-3">
          <div>
            <label class="block font-medium text-slate-300 mb-1">Route Name</label>
            <input
              v-model="newRouteForm.name"
              required
              placeholder="e.g. Route 104 - Eastside Express"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>
          <div>
            <label class="block font-medium text-slate-300 mb-1">Vehicle Info</label>
            <input
              v-model="newRouteForm.vehicle_info"
              required
              placeholder="e.g. Yellow Bus #19 (Plate: SP-9988)"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block font-medium text-slate-300 mb-1">Driver Name</label>
              <input
                v-model="newRouteForm.driver_name"
                required
                placeholder="e.g. Otto Mann"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block font-medium text-slate-300 mb-1">Driver Contact</label>
              <input
                v-model="newRouteForm.driver_contact"
                required
                placeholder="e.g. +1-555-019-9999"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>
          <div>
            <label class="block font-medium text-slate-300 mb-1">Capacity (Seats)</label>
            <input
              type="number"
              v-model.number="newRouteForm.capacity"
              required
              min="1"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>
          <div>
            <label class="block font-medium text-slate-300 mb-1">Description / Notes</label>
            <textarea
              v-model="newRouteForm.description"
              rows="2"
              placeholder="Optional notes or operational quadrant..."
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="showCreateRouteModal = false"
              class="px-3.5 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="transportStore.actionLoading"
              class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition"
            >
              Create Route
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: ADD STOP -->
    <div v-if="showAddStopModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-sm text-white">Add Stop to {{ targetRouteForStop?.name }}</h3>
          <button @click="showAddStopModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitAddStop" class="space-y-3">
          <div>
            <label class="block font-medium text-slate-300 mb-1">Stop Name</label>
            <input
              v-model="newStopForm.stop_name"
              required
              placeholder="e.g. Elm Street Crossing"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block font-medium text-slate-300 mb-1">Pickup Time</label>
              <input
                v-model="newStopForm.pickup_time"
                required
                placeholder="e.g. 07:15"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block font-medium text-slate-300 mb-1">Dropoff Time</label>
              <input
                v-model="newStopForm.dropoff_time"
                required
                placeholder="e.g. 15:45"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block font-medium text-slate-300 mb-1">Sequence #</label>
              <input
                type="number"
                v-model.number="newStopForm.sequence"
                required
                min="1"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block font-medium text-slate-300 mb-1">Landmark</label>
              <input
                v-model="newStopForm.landmark"
                placeholder="e.g. Near town square fountain"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="showAddStopModal = false"
              class="px-3.5 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="transportStore.actionLoading"
              class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition"
            >
              Add Stop
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 3: ASSIGN INDIVIDUAL STUDENT -->
    <div v-if="showAssignStudentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-sm text-white">Assign Student to Route</h3>
          <button @click="showAssignStudentModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitAssignStudent" class="space-y-3">
          <div>
            <label class="block font-medium text-slate-300 mb-1">Student</label>
            <select
              v-model="assignForm.student_id"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option :value="null">-- Select Student --</option>
              <option v-for="stu in students" :key="stu.id" :value="stu.id">
                {{ stu.user?.name || stu.name }} ({{ stu.admission_number }})
              </option>
            </select>
          </div>

          <div>
            <label class="block font-medium text-slate-300 mb-1">Route</label>
            <select
              v-model="assignForm.transport_route_id"
              @change="assignForm.transport_stop_id = null"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option :value="null">-- Select Route --</option>
              <option v-for="r in transportStore.routes" :key="r.id" :value="r.id">
                {{ r.name }} ({{ r.vehicle_info }})
              </option>
            </select>
          </div>

          <div v-if="assignFormRouteStops.length > 0">
            <label class="block font-medium text-slate-300 mb-1">Stop</label>
            <select
              v-model="assignForm.transport_stop_id"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option :value="null">-- Select Stop --</option>
              <option v-for="stop in assignFormRouteStops" :key="stop.id" :value="stop.id">
                Stop #{{ stop.sequence }}: {{ stop.stop_name }} ({{ stop.pickup_time }} / {{ stop.dropoff_time }})
              </option>
            </select>
          </div>

          <div>
            <label class="block font-medium text-slate-300 mb-1">Notes (Optional)</label>
            <input
              v-model="assignForm.notes"
              placeholder="e.g. Front row seat requested"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="showAssignStudentModal = false"
              class="px-3.5 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="!assignForm.student_id || !assignForm.transport_route_id || !assignForm.transport_stop_id || transportStore.actionLoading"
              class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition"
            >
              Assign Student
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useTransportStore } from '../stores/transport';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';

const transportStore = useTransportStore();
const authStore = useAuthStore();
const modalStore = useModalStore();

const activeTab = ref('routes');
const selectedRoute = ref(null);
const sections = ref([]);
const students = ref([]);
const parentChildren = ref([]);
const selectedChildId = ref(null);

const activeChildName = computed(() => {
  const c = parentChildren.value.find(k => k.id === selectedChildId.value);
  return c ? (c.user?.name || c.name || `Student #${c.id}`) : 'Child';
});

// Modals
const showCreateRouteModal = ref(false);
const showAddStopModal = ref(false);
const showAssignStudentModal = ref(false);
const targetRouteForStop = ref(null);

// Forms
const newRouteForm = ref({
  name: '',
  vehicle_info: '',
  driver_name: '',
  driver_contact: '',
  capacity: 35,
  description: '',
});

const newStopForm = ref({
  stop_name: '',
  pickup_time: '07:30',
  dropoff_time: '15:30',
  sequence: 1,
  landmark: '',
});

const assignForm = ref({
  student_id: null,
  transport_route_id: null,
  transport_stop_id: null,
  notes: '',
});

const bulkForm = ref({
  section_id: null,
  route_id: null,
  stop_id: null,
});

const canManage = computed(() => {
  return ['super_admin', 'school_admin'].includes(authStore.role);
});

const totalRoutes = computed(() => transportStore.routes.length);

const totalStops = computed(() => {
  return transportStore.routes.reduce((acc, r) => acc + (r.stops?.length || r.stops_count || 0), 0);
});

const totalAssigned = computed(() => {
  return transportStore.assignments.length;
});

const totalCapacityRemaining = computed(() => {
  const totalSeats = transportStore.routes.reduce((acc, r) => acc + (r.capacity || 0), 0);
  return Math.max(0, totalSeats - totalAssigned.value);
});

const sortedStops = computed(() => {
  if (!selectedRoute.value?.stops) return [];
  return [...selectedRoute.value.stops].sort((a, b) => a.sequence - b.sequence);
});

const bulkFormRouteStops = computed(() => {
  if (!bulkForm.value.route_id) return [];
  const route = transportStore.routes.find(r => r.id === bulkForm.value.route_id);
  return (route?.stops || []).slice().sort((a, b) => a.sequence - b.sequence);
});

const assignFormRouteStops = computed(() => {
  if (!assignForm.value.transport_route_id) return [];
  const route = transportStore.routes.find(r => r.id === assignForm.value.transport_route_id);
  return (route?.stops || []).slice().sort((a, b) => a.sequence - b.sequence);
});

async function selectRoute(route) {
  selectedRoute.value = route;
  const detailed = await transportStore.fetchRoute(route.id);
  if (detailed) {
    selectedRoute.value = detailed;
  }
}

function openCreateRouteModal() {
  newRouteForm.value = {
    name: '',
    vehicle_info: '',
    driver_name: '',
    driver_contact: '',
    capacity: 35,
    description: '',
  };
  showCreateRouteModal.value = true;
}

async function submitCreateRoute() {
  try {
    const created = await transportStore.createRoute(newRouteForm.value);
    showCreateRouteModal.value = false;
    if (created) {
      await selectRoute(created);
    }
  } catch (err) {
    // Handled in store
  }
}

function openAddStopModal(route) {
  targetRouteForStop.value = route;
  const nextSeq = (route.stops?.length || 0) + 1;
  newStopForm.value = {
    stop_name: '',
    pickup_time: '07:30',
    dropoff_time: '15:30',
    sequence: nextSeq,
    landmark: '',
  };
  showAddStopModal.value = true;
}

async function submitAddStop() {
  if (!targetRouteForStop.value) return;
  try {
    await transportStore.addStop(targetRouteForStop.value.id, newStopForm.value);
    showAddStopModal.value = false;
    await selectRoute(targetRouteForStop.value);
  } catch (err) {
    // Handled in store
  }
}

async function deleteStop(stopId) {
  const confirmed = await modalStore.confirm({
    title: 'Delete Stop',
    message: 'Are you sure you want to delete this route stop? Existing student pickups at this stop may be affected.',
    confirmText: 'Delete Stop',
    destructive: true,
  });
  if (!confirmed) return;
  await transportStore.deleteStop(stopId, selectedRoute.value?.id);
  modalStore.toast('Stop deleted successfully.', 'info');
  if (selectedRoute.value) {
    await selectRoute(selectedRoute.value);
  }
}

async function deleteRoute(route) {
  const confirmed = await modalStore.confirm({
    title: 'Delete Route',
    message: `Delete route "${route.name}"? This will remove all associated stops and unassign all assigned students.`,
    confirmText: 'Delete Route',
    destructive: true,
  });
  if (!confirmed) return;
  await transportStore.deleteRoute(route.id);
  modalStore.toast('Route deleted.', 'info');
  selectedRoute.value = transportStore.routes[0] || null;
}

function openAssignStudentModal() {
  assignForm.value = {
    student_id: null,
    transport_route_id: selectedRoute.value?.id || null,
    transport_stop_id: null,
    notes: '',
  };
  showAssignStudentModal.value = true;
}

async function submitAssignStudent() {
  try {
    await transportStore.assignStudent(assignForm.value);
    showAssignStudentModal.value = false;
    modalStore.toast('Student assigned to transport route!', 'success');
    if (selectedRoute.value) {
      await selectRoute(selectedRoute.value);
    }
  } catch (err) {
    // Handled in store
  }
}

function openBulkAssignModal() {
  activeTab.value = 'bulk';
  bulkForm.value = {
    section_id: sections.value[0]?.id || null,
    route_id: selectedRoute.value?.id || transportStore.routes[0]?.id || null,
    stop_id: null,
  };
}

async function submitBulkAssign() {
  try {
    await transportStore.bulkAssignSection(
      bulkForm.value.section_id,
      bulkForm.value.route_id,
      { transport_stop_id: bulkForm.value.stop_id }
    );
    modalStore.toast('Section students assigned to route successfully!', 'success');
    activeTab.value = 'assignments';
  } catch (err) {
    // Handled in store
  }
}

async function unassignStudent(studentId) {
  const confirmed = await modalStore.confirm({
    title: 'Unassign Student',
    message: 'Unassign this student from school transport?',
    confirmText: 'Unassign',
    destructive: true,
  });
  if (!confirmed) return;
  await transportStore.unassignStudent(studentId);
  modalStore.toast('Student unassigned from transport.', 'info');
  if (selectedRoute.value) {
    await selectRoute(selectedRoute.value);
  }
}

async function fetchParentChildren() {
  try {
    const res = await axios.get('/parent/children');
    parentChildren.value = res.data.data || [];
    if (parentChildren.value.length > 0 && !selectedChildId.value) {
      selectedChildId.value = parentChildren.value[0].id;
    }
  } catch (err) {
    console.error('Failed to fetch parent children:', err);
  }
}

async function selectParentChild(child) {
  selectedChildId.value = child.id;
  transportStore.loading = true;
  try {
    const res = await transportStore.fetchChildTransport(child.id);
    transportStore.myTransport = res;
  } finally {
    transportStore.loading = false;
  }
}

async function loadMyTransport() {
  activeTab.value = 'my-bus';
  if (authStore.isParent) {
    if (parentChildren.value.length === 0) {
      await fetchParentChildren();
    }
    if (selectedChildId.value) {
      const child = parentChildren.value.find(k => k.id === selectedChildId.value) || parentChildren.value[0];
      if (child) {
        await selectParentChild(child);
      }
    }
  } else {
    await transportStore.fetchMyStudentTransport();
  }
}

async function fetchMetadata() {
  if (!canManage.value) return;
  try {
    const [secRes, stuRes] = await Promise.all([
      axios.get('/sections'),
      axios.get('/students'),
    ]);
    sections.value = secRes.data.data || [];
    students.value = stuRes.data.data || [];
  } catch (err) {
    console.error('Failed to load auxiliary metadata:', err);
  }
}

onMounted(async () => {
  const promises = [
    transportStore.fetchRoutes(),
  ];

  if (canManage.value) {
    promises.push(transportStore.fetchAssignments());
    promises.push(fetchMetadata());
  } else if (authStore.isParent) {
    promises.push(fetchParentChildren());
  } else if (authStore.isStudent) {
    promises.push(transportStore.fetchMyStudentTransport());
  }

  await Promise.all(promises);

  if (authStore.isParent) {
    activeTab.value = 'my-bus';
    if (parentChildren.value.length > 0) {
      await selectParentChild(parentChildren.value[0]);
    }
  } else if (authStore.isStudent) {
    activeTab.value = 'my-bus';
  } else if (transportStore.routes.length > 0) {
    await selectRoute(transportStore.routes[0]);
  }
});
</script>
