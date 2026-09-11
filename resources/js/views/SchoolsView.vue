<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Schools Directory</h1>
        <p class="text-sm text-slate-400">Available tenant entities in the single-database platform.</p>
      </div>
      <button 
        @click="tenantStore.fetchSchools()" 
        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg border border-slate-700 transition"
      >
        Refresh Schools
      </button>
    </div>

    <div v-if="tenantStore.loading" class="text-center py-12 text-slate-400">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500 mb-2"></div>
      <div>Loading schools...</div>
    </div>

    <div v-else-if="tenantStore.error" class="p-4 bg-red-900/30 border border-red-700 rounded-lg text-red-200 text-sm">
      {{ tenantStore.error }}
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div 
        v-for="school in tenantStore.schools" 
        :key="school.id"
        class="bg-slate-900/70 border rounded-xl p-6 transition flex flex-col justify-between"
        :class="tenantStore.activeSchoolId === school.id ? 'border-indigo-500 ring-2 ring-indigo-500/20 shadow-lg shadow-indigo-500/5' : 'border-slate-800 hover:border-slate-700'"
      >
        <div>
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center overflow-hidden">
                <img v-if="school.logo" :src="school.logo" :alt="school.name" class="w-full h-full object-cover" />
                <span v-else class="text-xl font-bold text-indigo-400">{{ school.name.charAt(0) }}</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">{{ school.name }}</h2>
                <div class="text-xs font-mono text-indigo-400">
                  {{ school.subdomain }}.localhost
                </div>
              </div>
            </div>
            <span 
              class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider"
              :class="school.subscription_status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400'"
            >
              {{ school.subscription_status }}
            </span>
          </div>

          <div class="mt-6 space-y-2 text-xs text-slate-300">
            <div class="flex items-center gap-2">
              <span class="text-slate-500 w-24">Tenant ID:</span>
              <code class="text-slate-200 font-mono">{{ school.id }}</code>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-slate-500 w-24">Timezone:</span>
              <span>{{ school.timezone }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-slate-500 w-24">Courses:</span>
              <span class="px-2 py-0.5 rounded bg-slate-800 font-semibold text-slate-200">{{ school.courses_count ?? 0 }} scoped courses</span>
            </div>
            <!-- Telegram Bot Status -->
            <div class="flex items-center gap-2">
              <span class="text-slate-500 w-24">Telegram Bot:</span>
              <span 
                v-if="school.telegram_bot_username" 
                class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/20 font-mono flex items-center gap-1.5"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                @{{ school.telegram_bot_username }}
              </span>
              <span v-else class="text-slate-500 italic">Not configured</span>
            </div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between flex-wrap gap-2">
          <span v-if="tenantStore.activeSchoolId === school.id" class="text-xs font-semibold text-indigo-400 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
            Active Tenant
          </span>
          <span v-else class="text-xs text-slate-500">Not active</span>

          <div class="flex items-center gap-2">
            <!-- Configure Bot Button -->
            <button
              v-if="canManageSchoolBot(school)"
              @click="openConfigureBotModal(school)"
              type="button"
              class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center gap-1 active:scale-95"
              title="Configure Telegram Bot for this school"
            >
              <span>🤖</span>
              <span>Bot Settings</span>
            </button>

            <!-- Switch Context Button -->
            <button 
              @click="selectAndSwitch(school)"
              class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition"
              :class="tenantStore.activeSchoolId === school.id ? 'bg-indigo-600/30 text-indigo-200 border border-indigo-500/30' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm shadow-indigo-600/30'"
            >
              {{ tenantStore.activeSchoolId === school.id ? 'Selected' : 'Switch Context' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Configure School Telegram Bot -->
    <div v-if="editingSchool" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-6 space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2.5">
            <span class="text-2xl p-1.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20">🤖</span>
            <div>
              <h3 class="text-base font-bold text-white">Configure Telegram Bot</h3>
              <p class="text-xs text-slate-400">{{ editingSchool.name }} ({{ editingSchool.subdomain }}.localhost)</p>
            </div>
          </div>
          <button @click="closeBotModal" class="text-slate-500 hover:text-white p-1 rounded-lg">✕</button>
        </div>

        <!-- Alert / Feedback -->
        <div v-if="modalError" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
          {{ modalError }}
        </div>
        <div v-if="modalSuccess" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">
          {{ modalSuccess }}
        </div>

        <form @submit.prevent="saveSchoolBot" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 font-semibold mb-1 flex items-center justify-between">
              <span>Telegram Bot Username</span>
              <span class="text-[11px] text-slate-500">From @BotFather</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 select-none">@</span>
              <input
                v-model="botForm.telegram_bot_username"
                type="text"
                placeholder="e.g. OakridgeAcademyBot"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-8 pr-3 py-2.5 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 transition"
              />
            </div>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1 flex items-center justify-between">
              <span>Bot API Token (Key)</span>
              <button type="button" @click="showModalToken = !showModalToken" class="text-indigo-400 hover:text-indigo-300 text-[11px]">
                {{ showModalToken ? 'Hide Token' : 'Reveal Token' }}
              </button>
            </label>
            <input
              v-model="botForm.telegram_bot_token"
              :type="showModalToken ? 'text' : 'password'"
              placeholder="e.g. 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white font-mono placeholder-slate-600 focus:outline-none focus:border-indigo-500 transition"
            />
            <p class="text-[11px] text-slate-500 mt-1">
              Leave blank to keep existing token. Paste new key from BotFather to update.
            </p>
          </div>

          <!-- Webhook URL -->
          <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-1.5">
            <span class="text-[11px] font-semibold text-slate-400">Webhook Receiver URL</span>
            <div class="flex items-center gap-2">
              <input
                :value="editingSchoolWebhookUrl"
                readonly
                class="flex-1 bg-slate-900 border border-slate-800 rounded px-2.5 py-1.5 text-[11px] font-mono text-slate-300 select-all"
              />
              <button type="button" @click="copyModalWebhook" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded border border-slate-700 text-xs">
                Copy
              </button>
            </div>
            <label class="flex items-center gap-2 cursor-pointer text-slate-400 text-[11px] pt-1">
              <input type="checkbox" v-model="botForm.register_webhook" class="rounded border-slate-700 text-indigo-600 focus:ring-0 bg-slate-900" />
              <span>Register webhook with Telegram API automatically on save</span>
            </label>
          </div>

          <!-- Test Connection Feedback -->
          <div v-if="testResult" class="p-2.5 rounded-xl text-xs" :class="testResult.success ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-300 border border-rose-500/20'">
            {{ testResult.message }}
          </div>

          <!-- Footer Buttons -->
          <div class="flex items-center justify-between pt-2 border-t border-slate-800">
            <button
              type="button"
              @click="testModalBot"
              :disabled="isTesting || isSaving"
              class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold disabled:opacity-50 transition"
            >
              {{ isTesting ? 'Testing...' : 'Test Connection' }}
            </button>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="closeBotModal"
                class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="isSaving"
                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm transition disabled:opacity-50 flex items-center gap-1"
              >
                <span v-if="isSaving" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>Save Configuration</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useTenantStore } from '../stores/tenant';
import { useAuthStore } from '../stores/auth';
import { useModalStore } from '../stores/modal';

const router = useRouter();
const tenantStore = useTenantStore();
const authStore = useAuthStore();
const modalStore = useModalStore();

const editingSchool = ref(null);
const showModalToken = ref(false);
const isSaving = ref(false);
const isTesting = ref(false);
const modalError = ref(null);
const modalSuccess = ref(null);
const testResult = ref(null);

const botForm = ref({
  telegram_bot_username: '',
  telegram_bot_token: '',
  register_webhook: true,
});

const editingSchoolWebhookUrl = computed(() => {
  if (!editingSchool.value) return '';
  return `${window.location.origin}/api/telegram/webhook/${editingSchool.value.id}`;
});

function canManageSchoolBot(school) {
  if (authStore.isSuperAdmin) return true;
  if (authStore.isSchoolAdmin && authStore.schoolContext?.id === school.id) return true;
  return false;
}

function openConfigureBotModal(school) {
  editingSchool.value = school;
  botForm.value = {
    telegram_bot_username: school.telegram_bot_username || '',
    telegram_bot_token: '',
    register_webhook: true,
  };
  showModalToken.value = false;
  modalError.value = null;
  modalSuccess.value = null;
  testResult.value = null;
}

function closeBotModal() {
  editingSchool.value = null;
  modalError.value = null;
  modalSuccess.value = null;
  testResult.value = null;
}

function copyModalWebhook() {
  if (editingSchoolWebhookUrl.value) {
    navigator.clipboard?.writeText(editingSchoolWebhookUrl.value);
    modalStore.toast('Webhook URL copied!', 'info');
  }
}

async function testModalBot() {
  if (!editingSchool.value) return;
  isTesting.value = true;
  testResult.value = null;
  try {
    const res = await tenantStore.testSchoolTelegram(editingSchool.value.id, botForm.value.telegram_bot_token || null);
    if (res.success) {
      testResult.value = {
        success: true,
        message: `✓ Telegram Verified! Bot: ${res.data?.result?.first_name} (@${res.data?.result?.username})`,
      };
    } else {
      testResult.value = {
        success: false,
        message: `⚠️ ${res.error}`,
      };
    }
  } finally {
    isTesting.value = false;
  }
}

async function saveSchoolBot() {
  if (!editingSchool.value) return;
  isSaving.value = true;
  modalError.value = null;
  modalSuccess.value = null;
  try {
    const payload = {
      telegram_bot_username: botForm.value.telegram_bot_username,
      register_webhook: botForm.value.register_webhook,
    };
    if (botForm.value.telegram_bot_token) {
      payload.telegram_bot_token = botForm.value.telegram_bot_token;
    }

    const res = await tenantStore.updateSchoolTelegram(editingSchool.value.id, payload);
    if (res.success) {
      modalSuccess.value = res.message || 'Telegram bot settings updated successfully!';
      setTimeout(() => {
        closeBotModal();
      }, 1200);
    } else {
      modalError.value = res.error;
    }
  } catch (err) {
    modalError.value = 'Failed to update bot configuration.';
  } finally {
    isSaving.value = false;
  }
}

function selectAndSwitch(school) {
  tenantStore.selectSchool(school);
}
</script>
