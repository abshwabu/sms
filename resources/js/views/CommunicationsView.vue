<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Communications &amp; Announcements</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            Messaging &amp; Telegram Bot
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          School-wide and grade-targeted bulletins, private teacher-parent student messaging, and Telegram bot dispatches.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button
          v-if="canPostAnnouncement"
          @click="openNewAnnouncementModal"
          class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition flex items-center gap-1.5 shadow-sm active:scale-[0.98]"
        >
          <span>📢</span>
          <span>New Announcement</span>
        </button>
        <button
          v-if="canMessage"
          @click="openNewThreadModal"
          class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs border border-slate-700 transition flex items-center gap-1.5 active:scale-[0.98]"
        >
          <span>💬</span>
          <span>Message Teacher/Parent</span>
        </button>
      </div>
    </div>

    <!-- Alert / Feedback Banner -->
    <div v-if="commStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>⚠️</span>
        <span>{{ commStore.error }}</span>
      </div>
      <button @click="commStore.clearMessages" class="text-rose-400 hover:text-rose-200">✕</button>
    </div>

    <div v-if="commStore.successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>✓</span>
        <span>{{ commStore.successMessage }}</span>
      </div>
      <button @click="commStore.clearMessages" class="text-emerald-400 hover:text-emerald-200">✕</button>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
      <button
        @click="activeTab = 'announcements'"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
        :class="activeTab === 'announcements' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white bg-slate-900 border border-slate-800'"
      >
        📢 Announcements ({{ commStore.announcements.length }})
      </button>
      <button
        v-if="canMessage"
        @click="activeTab = 'messages'"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
        :class="activeTab === 'messages' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white bg-slate-900 border border-slate-800'"
      >
        💬 Direct Messaging Threads ({{ commStore.threads.length }})
      </button>
      <button
        @click="openTelegramTab"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
        :class="activeTab === 'telegram' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-400 hover:text-white bg-slate-900 border border-slate-800'"
      >
        <span>✈️</span>
        <span>Telegram Bot Settings</span>
        <span
          v-if="commStore.telegramStatus?.is_linked"
          class="w-2 h-2 rounded-full bg-emerald-400 inline-block ml-1"
          title="Linked"
        ></span>
      </button>
    </div>

    <!-- TAB 1: ANNOUNCEMENTS -->
    <div v-if="activeTab === 'announcements'" class="space-y-4">
      <div v-if="commStore.loading" class="text-center py-12 text-slate-500 text-sm">
        Loading announcements...
      </div>

      <div v-else-if="commStore.announcements.length === 0" class="text-center py-12 bg-slate-900/60 border border-slate-800 rounded-2xl">
        <div class="text-3xl mb-2">📢</div>
        <h3 class="text-sm font-semibold text-white">No announcements published yet</h3>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          School-wide notices and grade-targeted bulletins will appear here.
        </p>
      </div>

      <div v-else class="grid grid-cols-1 gap-4">
        <div
          v-for="ann in commStore.announcements"
          :key="ann.id"
          class="p-5 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-sm space-y-3 relative"
          :class="ann.priority === 'urgent' ? 'border-rose-500/30 ring-1 ring-rose-500/20' : ''"
        >
          <!-- Header row -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-base font-bold text-white">{{ ann.title }}</h3>

              <!-- Priority badge -->
              <span
                class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full"
                :class="{
                  'bg-rose-500/10 text-rose-400 border border-rose-500/20': ann.priority === 'urgent',
                  'bg-amber-500/10 text-amber-400 border border-amber-500/20': ann.priority === 'high',
                  'bg-slate-800 text-slate-300 border border-slate-700': ann.priority === 'normal',
                  'bg-slate-800 text-slate-400': ann.priority === 'low'
                }"
              >
                {{ ann.priority }} priority
              </span>

              <!-- Audience badge -->
              <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
                Audience:
                <span v-if="ann.audience_type === 'grade_level'">Grade: {{ ann.grade_level?.name || 'Grade' }}</span>
                <span v-else-if="ann.audience_type === 'section'">Section: {{ ann.section?.name || 'Section' }}</span>
                <span v-else-if="ann.audience_type === 'role'">Role: {{ ann.target_role }}</span>
                <span v-else>All School Members</span>
              </span>

              <!-- Published Status -->
              <span v-if="!ann.published_at" class="text-[10px] px-2 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-semibold">
                DRAFT
              </span>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0">
              <span class="text-[11px] font-mono text-slate-500">
                {{ ann.published_at ? formatDate(ann.published_at) : 'Unpublished' }}
              </span>
              <button
                v-if="canPostAnnouncement && !ann.published_at"
                @click="publishAnnouncement(ann.id)"
                class="px-2.5 py-1 text-[11px] font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition"
              >
                Publish Now
              </button>
              <button
                v-if="canManageAnnouncement(ann)"
                @click="deleteAnnouncement(ann.id)"
                class="text-slate-500 hover:text-rose-400 text-xs p-1"
                title="Delete Announcement"
              >
                🗑
              </button>
            </div>
          </div>

          <!-- Body -->
          <p class="text-xs text-slate-300 whitespace-pre-line leading-relaxed">
            {{ ann.body }}
          </p>

          <!-- Footer & Meta -->
          <div class="flex items-center justify-between pt-3 border-t border-slate-800/80 text-[11px] text-slate-400">
            <div class="flex items-center gap-3">
              <span>Author: <strong class="text-slate-300">{{ ann.author?.name || 'Admin' }}</strong></span>
              <span v-if="ann.channels?.length" class="text-slate-500">
                Channels: {{ ann.channels.join(', ') }}
              </span>
            </div>
            <div v-if="ann.dispatches_count !== undefined" class="font-mono text-slate-500">
              {{ ann.dispatches_count }} delivery dispatches
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: DIRECT TEACHER-PARENT MESSAGES (ACCEPTANCE CRITERION 2) -->
    <div v-if="activeTab === 'messages'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Threads List Sidebar -->
      <div class="space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Conversations</span>
          <button
            @click="openNewThreadModal"
            class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold"
          >
            + Start Thread
          </button>
        </div>

        <div v-if="commStore.threads.length === 0" class="p-6 text-center text-slate-500 text-xs bg-slate-900/60 rounded-xl border border-slate-800">
          No message threads yet. Click "+ Start Thread" to message a teacher or parent regarding a student.
        </div>

        <div
          v-for="t in commStore.threads"
          :key="t.id"
          @click="selectThread(t)"
          class="p-3.5 rounded-xl border transition cursor-pointer"
          :class="commStore.currentThread?.id === t.id ? 'bg-slate-800 border-indigo-500/50 shadow-md ring-1 ring-indigo-500/20' : 'bg-slate-900/80 border-slate-800 hover:border-slate-700'"
        >
          <div class="flex items-start justify-between gap-1">
            <h4 class="text-xs font-bold text-white truncate">{{ t.subject }}</h4>
            <span class="text-[10px] text-slate-500 shrink-0 font-mono">
              {{ formatRelativeTime(t.last_message_at) }}
            </span>
          </div>

          <div class="text-[11px] text-indigo-400 font-medium mt-1">
            Student: {{ t.student?.user?.name || 'Student' }}
          </div>

          <p v-if="t.latest_message" class="text-[11px] text-slate-400 mt-1 truncate">
            {{ t.latest_message.sender?.name }}: {{ t.latest_message.body }}
          </p>
        </div>
      </div>

      <!-- Chat Messages Conversation View (Right 2 cols) -->
      <div class="lg:col-span-2">
        <div v-if="commStore.currentThread" class="bg-slate-900/90 border border-slate-800 rounded-2xl flex flex-col h-[600px] shadow-sm overflow-hidden">
          <!-- Thread Header -->
          <div class="p-4 border-b border-slate-800 bg-slate-950/60 flex items-center justify-between">
            <div>
              <h3 class="text-sm font-bold text-white">{{ commStore.currentThread.subject }}</h3>
              <p class="text-xs text-slate-400 mt-0.5">
                Regarding: <strong class="text-emerald-400">{{ commStore.currentThread.student?.user?.name }}</strong>
                ({{ commStore.currentThread.student?.admission_number }})
              </p>
            </div>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700 font-mono">
              Thread #{{ commStore.currentThread.id }}
            </span>
          </div>

          <!-- Messages Container -->
          <div class="flex-1 p-4 overflow-y-auto space-y-3">
            <div
              v-for="msg in commStore.currentThread.messages"
              :key="msg.id"
              class="flex flex-col"
              :class="msg.sender_id === authStore.user?.id ? 'items-end' : 'items-start'"
            >
              <div class="flex items-center gap-1.5 text-[10px] text-slate-500 mb-1 px-1">
                <span class="font-semibold text-slate-300">{{ msg.sender?.name }}</span>
                <span class="capitalize text-[9px] px-1 py-0.2 rounded bg-slate-800 border border-slate-700">
                  {{ msg.sender?.role }}
                </span>
                <span>&bull; {{ formatRelativeTime(msg.created_at) }}</span>
              </div>
              <div
                class="max-w-md p-3 rounded-2xl text-xs leading-relaxed"
                :class="msg.sender_id === authStore.user?.id 
                  ? 'bg-indigo-600 text-white rounded-br-none' 
                  : 'bg-slate-800 text-slate-200 rounded-bl-none border border-slate-700/80'"
              >
                {{ msg.body }}
              </div>
            </div>
          </div>

          <!-- Reply Composer -->
          <form @submit.prevent="submitReply" class="p-3 border-t border-slate-800 bg-slate-950/80 flex items-center gap-2">
            <input
              v-model="replyText"
              required
              placeholder="Type your reply to teachers and parents..."
              class="flex-1 bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"
            />
            <button
              type="submit"
              :disabled="!replyText.trim() || commStore.actionLoading"
              class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold text-xs transition active:scale-[0.98]"
            >
              Send
            </button>
          </form>
        </div>

        <div v-else class="p-12 text-center text-slate-500 text-xs bg-slate-900/40 rounded-2xl border border-slate-800">
          Select a thread from the left or start a new conversation.
        </div>
      </div>
    </div>

    <!-- TAB 3: TELEGRAM BOT INTEGRATION (ACCEPTANCE CRITERION 3) -->
    <div v-if="activeTab === 'telegram'" class="max-w-2xl mx-auto space-y-6">
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
        <div class="border-b border-slate-800 pb-4">
          <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
              <span>✈️ Telegram School Bot Delivery</span>
            </h2>
            <span
              class="text-xs px-2.5 py-0.5 rounded-full font-semibold border"
              :class="commStore.telegramStatus?.is_linked 
                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' 
                : 'bg-slate-800 text-slate-400 border-slate-700'"
            >
              {{ commStore.telegramStatus?.is_linked ? 'Connected' : 'Not Linked' }}
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-1">
            Receive instant school bulletins, child attendance alerts, and academic updates directly on Telegram with zero spam.
          </p>
        </div>

        <!-- Connection State -->
        <div v-if="commStore.telegramStatus?.is_linked" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 space-y-3">
          <div class="flex items-start justify-between">
            <div>
              <span class="text-[10px] uppercase font-bold text-emerald-400 tracking-wider">Account Linked</span>
              <div class="text-sm font-bold text-white mt-0.5">
                Telegram: @{{ commStore.telegramStatus.telegram_username || 'User' }}
              </div>
              <p class="text-xs text-emerald-200/80 mt-0.5">
                Active since {{ formatDate(commStore.telegramStatus.linked_at) }}. Direct messages and emergency alerts are enabled.
              </p>
            </div>
            <button
              @click="unlinkTelegram"
              :disabled="commStore.actionLoading"
              class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-semibold transition"
            >
              Disconnect
            </button>
          </div>
        </div>

        <!-- Not Linked: Setup Flow -->
        <div v-else class="space-y-4 text-xs">
          <p class="text-slate-300">
            To link your Telegram account to this school, generate a secure link code below and send it to the school's official bot.
          </p>

          <div v-if="commStore.telegramLinkData" class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
            <div class="text-slate-400">Step 1: Open Telegram Bot</div>
            <a
              :href="commStore.telegramLinkData.deep_link"
              target="_blank"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs transition"
            >
              <span>✈️</span>
              <span>Open @{{ commStore.telegramLinkData.bot_username }} in Telegram</span>
            </a>

            <div class="pt-2 text-slate-400">Step 2: Or send this command directly:</div>
            <div class="p-2.5 bg-slate-900 rounded-lg font-mono text-emerald-400 text-xs border border-slate-800 select-all flex items-center justify-between">
              <span>/start {{ commStore.telegramLinkData.link_code }}</span>
              <span class="text-[10px] text-slate-500">Copy &amp; send</span>
            </div>
            <p class="text-[10px] text-slate-500 italic">
              Link code expires on {{ formatDate(commStore.telegramLinkData.expires_at) }}.
            </p>
          </div>

          <button
            v-else
            @click="commStore.generateTelegramLink()"
            :disabled="commStore.actionLoading"
            class="w-full py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold transition flex items-center justify-center gap-2"
          >
            <span>✈️ Generate Telegram Connection Link</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 1: CREATE ANNOUNCEMENT -->
    <div v-if="showNewAnnouncementModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-sm text-white">Create New Announcement</h3>
          <button @click="showNewAnnouncementModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitCreateAnnouncement" class="space-y-3">
          <div>
            <label class="block font-medium text-slate-300 mb-1">Title</label>
            <input
              v-model="announcementForm.title"
              required
              placeholder="e.g. Campus Early Dismissal or Science Fair Notice"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block font-medium text-slate-300 mb-1">Audience Target</label>
              <select
                v-model="announcementForm.audience_type"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                <option value="all">Whole School (All)</option>
                <option value="grade_level">Specific Grade Level</option>
                <option value="section">Specific Section</option>
                <option value="role">Specific User Role</option>
              </select>
            </div>

            <div>
              <label class="block font-medium text-slate-300 mb-1">Priority</label>
              <select
                v-model="announcementForm.priority"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
                <option value="low">Low</option>
              </select>
            </div>
          </div>

          <!-- Dynamic target picker -->
          <div v-if="announcementForm.audience_type === 'grade_level'">
            <label class="block font-medium text-slate-300 mb-1">Select Grade Level</label>
            <select
              v-model="announcementForm.grade_level_id"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option :value="null">-- Choose Grade Level --</option>
              <option v-for="g in gradeLevels" :key="g.id" :value="g.id">
                {{ g.name }} ({{ g.code }})
              </option>
            </select>
          </div>

          <div v-if="announcementForm.audience_type === 'section'">
            <label class="block font-medium text-slate-300 mb-1">Select Section</label>
            <select
              v-model="announcementForm.section_id"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option :value="null">-- Choose Section --</option>
              <option v-for="s in sections" :key="s.id" :value="s.id">
                {{ s.name }} (Grade: {{ s.grade_level?.name }})
              </option>
            </select>
          </div>

          <div v-if="announcementForm.audience_type === 'role' || announcementForm.audience_type === 'all'">
            <label class="block font-medium text-slate-300 mb-1">Target Role (Optional)</label>
            <select
              v-model="announcementForm.target_role"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option :value="null">All Roles (Students, Parents, Teachers)</option>
              <option value="parent">Parents Only</option>
              <option value="student">Students Only</option>
              <option value="teacher">Teachers Only</option>
            </select>
          </div>

          <div>
            <label class="block font-medium text-slate-300 mb-1">Announcement Body</label>
            <textarea
              v-model="announcementForm.body"
              required
              rows="4"
              placeholder="Write the full announcement details here..."
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            ></textarea>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input
              type="checkbox"
              id="pub_now"
              v-model="announcementForm.publish_now"
              class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-0"
            />
            <label for="pub_now" class="text-xs text-slate-300 font-medium">
              Publish immediately and dispatch to In-App, Email, and Telegram
            </label>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="showNewAnnouncementModal = false"
              class="px-3.5 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="commStore.actionLoading"
              class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition"
            >
              Save Announcement
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: START MESSAGE THREAD -->
    <div v-if="showNewThreadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-sm text-white">Start Student Direct Thread</h3>
          <button @click="showNewThreadModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitCreateThread" class="space-y-3">
          <div>
            <label class="block font-medium text-slate-300 mb-1">Student Context</label>
            <select
              v-model="threadForm.student_id"
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
            <label class="block font-medium text-slate-300 mb-1">Subject</label>
            <input
              v-model="threadForm.subject"
              required
              placeholder="e.g. Math Homework or Project Update"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block font-medium text-slate-300 mb-1">Initial Message</label>
            <textarea
              v-model="threadForm.message"
              required
              rows="3"
              placeholder="Write your message to the student's teachers and parents..."
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="showNewThreadModal = false"
              class="px-3.5 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="commStore.actionLoading"
              class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition"
            >
              Send Message
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useCommunicationsStore } from '../stores/communications';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const commStore = useCommunicationsStore();
const authStore = useAuthStore();

const activeTab = ref('announcements');
const replyText = ref('');
const gradeLevels = ref([]);
const sections = ref([]);
const students = ref([]);

// Modals
const showNewAnnouncementModal = ref(false);
const showNewThreadModal = ref(false);

const announcementForm = ref({
  title: '',
  body: '',
  audience_type: 'all',
  grade_level_id: null,
  section_id: null,
  target_role: null,
  priority: 'normal',
  publish_now: true,
});

const threadForm = ref({
  student_id: null,
  subject: '',
  message: '',
});

const canPostAnnouncement = computed(() => {
  return ['super_admin', 'school_admin', 'teacher'].includes(authStore.role);
});

const canMessage = computed(() => {
  return ['super_admin', 'school_admin', 'teacher', 'parent'].includes(authStore.role);
});

function canManageAnnouncement(ann) {
  if (['super_admin', 'school_admin'].includes(authStore.role)) return true;
  return ann.author_id === authStore.user?.id;
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatRelativeTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const diffMins = Math.round((Date.now() - d.getTime()) / 60000);
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.round(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  return `${Math.round(diffHours / 24)}d ago`;
}

function openNewAnnouncementModal() {
  announcementForm.value = {
    title: '',
    body: '',
    audience_type: 'all',
    grade_level_id: null,
    section_id: null,
    target_role: null,
    priority: 'normal',
    publish_now: true,
  };
  showNewAnnouncementModal.value = true;
}

async function submitCreateAnnouncement() {
  try {
    await commStore.createAnnouncement(announcementForm.value);
    showNewAnnouncementModal.value = false;
  } catch (err) {
    // Handled in store
  }
}

async function publishAnnouncement(id) {
  await commStore.publishAnnouncement(id);
}

async function deleteAnnouncement(id) {
  if (!confirm('Are you sure you want to delete this announcement?')) return;
  await commStore.deleteAnnouncement(id);
}

function openNewThreadModal(studentId = null) {
  threadForm.value = {
    student_id: studentId || students.value[0]?.id || null,
    subject: '',
    message: '',
  };
  showNewThreadModal.value = true;
}

async function submitCreateThread() {
  try {
    const thread = await commStore.createThread(threadForm.value);
    showNewThreadModal.value = false;
    activeTab.value = 'messages';
    if (thread) {
      await commStore.fetchThread(thread.id);
    }
  } catch (err) {
    // Handled in store
  }
}

async function selectThread(thread) {
  await commStore.fetchThread(thread.id);
}

async function submitReply() {
  if (!commStore.currentThread || !replyText.value.trim()) return;
  try {
    await commStore.replyThread(commStore.currentThread.id, replyText.value);
    replyText.value = '';
  } catch (err) {
    // Handled in store
  }
}

async function openTelegramTab() {
  activeTab.value = 'telegram';
  await commStore.fetchTelegramStatus();
}

async function unlinkTelegram() {
  if (!confirm('Disconnect Telegram notifications?')) return;
  await commStore.unlinkTelegram();
}

async function loadMetadata() {
  try {
    const [gRes, sRes, stuRes] = await Promise.all([
      axios.get('/grade-levels'),
      axios.get('/sections'),
      axios.get('/students'),
    ]);
    gradeLevels.value = gRes.data.data || [];
    sections.value = sRes.data.data || [];
    students.value = stuRes.data.data || [];
  } catch (e) {
    // Silent
  }
}

onMounted(async () => {
  await Promise.all([
    commStore.fetchAnnouncements(),
    commStore.fetchThreads(),
    commStore.fetchTelegramStatus(),
    loadMetadata(),
  ]);

  if (route.query.tab) {
    activeTab.value = String(route.query.tab);
  }

  if (route.query.student_id) {
    const sId = parseInt(String(route.query.student_id), 10);
    const existing = commStore.threads.find(t => t.student_id === sId);
    activeTab.value = 'messages';
    if (existing) {
      await commStore.fetchThread(existing.id);
    } else {
      openNewThreadModal(sId);
    }
  } else if (commStore.threads.length > 0 && !commStore.currentThread) {
    await commStore.fetchThread(commStore.threads[0].id);
  }
});
</script>
