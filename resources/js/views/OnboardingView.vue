<template>
  <div class="space-y-8">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white flex items-center gap-2">
        <span>Onboarding &amp; Registration</span>
        <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
          Invitations &amp; Claim Codes
        </span>
      </h1>
      <p class="text-sm text-slate-400 mt-1">
        Invite-based staff onboarding and claim-code registration for students and parents.
      </p>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-slate-800 text-sm font-semibold">
      <button 
        @click="activeTab = 'invitation'"
        class="py-3 px-6 border-b-2 transition"
        :class="activeTab === 'invitation' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-slate-200'"
      >
        ✉️ Staff Email Invitations
      </button>
      <button 
        @click="activeTab = 'claim'"
        class="py-3 px-6 border-b-2 transition"
        :class="activeTab === 'claim' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-slate-200'"
      >
        🎟️ Student/Parent Claim Codes
      </button>
    </div>

    <!-- Tab 1: Staff Invitations -->
    <div v-if="activeTab === 'invitation'" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Admin Invitation Form -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <div>
          <h2 class="text-base font-bold text-white">1. Admin: Send Staff Invitation</h2>
          <p class="text-xs text-slate-400">School admins can invite teachers or administrative staff.</p>
        </div>

        <form @submit.prevent="sendInvite" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 mb-1 font-medium">Candidate Email</label>
            <input 
              v-model="inviteForm.email"
              type="email"
              required
              placeholder="e.g. teacher.smith@greenwood.edu"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1 font-medium">Assigned Role</label>
            <select 
              v-model="inviteForm.role"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option value="teacher">Teacher</option>
              <option value="school_admin">School Admin</option>
            </select>
          </div>

          <button 
            type="submit"
            :disabled="inviteLoading || !authStore.isSchoolAdmin"
            class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-semibold rounded-lg transition"
          >
            {{ inviteLoading ? 'Sending...' : 'Generate & Send Invitation' }}
          </button>

          <div v-if="!authStore.isSchoolAdmin" class="text-[11px] text-amber-400 italic">
            * You must be signed in as School Admin (e.g. Principal Skinner) to send invitations.
          </div>
        </form>

        <div v-if="lastInvitation" class="p-4 bg-slate-950 border border-indigo-500/30 rounded-lg space-y-2 text-xs font-mono">
          <div class="text-indigo-400 font-bold">✓ Invitation Generated:</div>
          <div class="text-slate-300 truncate">Token: {{ lastInvitation.token }}</div>
          <button 
            @click="acceptForm.token = lastInvitation.token" 
            class="text-[11px] text-indigo-400 underline hover:text-indigo-300"
          >
            Auto-fill Acceptance Form →
          </button>
        </div>
      </div>

      <!-- Accept Invitation Form -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <div>
          <h2 class="text-base font-bold text-white">2. Candidate: Accept &amp; Onboard</h2>
          <p class="text-xs text-slate-400">Complete registration using the invitation token.</p>
        </div>

        <form @submit.prevent="acceptInvite" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 mb-1 font-medium">Invitation Token (64-chars)</label>
            <input 
              v-model="acceptForm.token"
              type="text"
              required
              placeholder="Paste invitation token here"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1 font-medium">Full Name</label>
            <input 
              v-model="acceptForm.name"
              type="text"
              required
              placeholder="e.g. Eleanor Vance"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1 font-medium">Set Password</label>
            <input 
              v-model="acceptForm.password"
              type="password"
              required
              placeholder="Minimum 8 characters"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
          </div>

          <button 
            type="submit"
            :disabled="acceptLoading"
            class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-semibold rounded-lg transition"
          >
            {{ acceptLoading ? 'Activating...' : 'Activate Account & Sign In' }}
          </button>
        </form>

        <div v-if="acceptSuccess" class="p-4 bg-emerald-950/40 border border-emerald-700 rounded-lg text-emerald-300 text-xs">
          {{ acceptSuccess }}
        </div>
      </div>
    </div>

    <!-- Tab 2: Claim Codes -->
    <div v-if="activeTab === 'claim'" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Generate Claim Code -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <div>
          <h2 class="text-base font-bold text-white">1. Admin: Generate Claim Code</h2>
          <p class="text-xs text-slate-400">Generate single-use claim codes for students or parents.</p>
        </div>

        <form @submit.prevent="generateCode" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 mb-1 font-medium">Account Type</label>
            <select 
              v-model="claimCodeForm.role"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            >
              <option value="student">Student</option>
              <option value="parent">Parent</option>
            </select>
          </div>

          <button 
            type="submit"
            :disabled="claimCodeLoading || !authStore.isSchoolAdmin"
            class="w-full py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-40 text-white font-semibold rounded-lg transition"
          >
            {{ claimCodeLoading ? 'Generating...' : 'Generate Claim Code' }}
          </button>

          <div v-if="!authStore.isSchoolAdmin" class="text-[11px] text-amber-400 italic">
            * Must be logged in as School Admin to issue claim codes.
          </div>
        </form>

        <div v-if="lastClaimCode" class="p-4 bg-slate-950 border border-purple-500/30 rounded-lg space-y-2 text-xs font-mono">
          <div class="text-purple-400 font-bold">✓ Claim Code Generated:</div>
          <div class="text-xl font-black text-white tracking-widest">{{ lastClaimCode.code }}</div>
          <button 
            @click="redeemForm.code = lastClaimCode.code" 
            class="text-[11px] text-purple-400 underline hover:text-purple-300"
          >
            Auto-fill Claim Form →
          </button>
        </div>
      </div>

      <!-- Redeem Claim Code -->
      <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-6 space-y-4">
        <div>
          <h2 class="text-base font-bold text-white">2. Claim Account Flow</h2>
          <p class="text-xs text-slate-400">Students and parents register using their assigned code.</p>
        </div>

        <form @submit.prevent="redeemCode" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-300 mb-1 font-medium">Claim Code</label>
            <input 
              v-model="redeemForm.code"
              type="text"
              required
              placeholder="e.g. BINA-XXXX-YYYY"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono uppercase focus:ring-2 focus:ring-purple-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1 font-medium">Full Name</label>
            <input 
              v-model="redeemForm.name"
              type="text"
              required
              placeholder="e.g. Maggie Simpson"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1 font-medium">Email Address</label>
            <input 
              v-model="redeemForm.email"
              type="email"
              required
              placeholder="e.g. maggie@greenwood.edu"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-slate-300 mb-1 font-medium">Password</label>
            <input 
              v-model="redeemForm.password"
              type="password"
              required
              placeholder="Minimum 8 characters"
              class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-purple-500 focus:outline-none"
            />
          </div>

          <button 
            type="submit"
            :disabled="redeemLoading"
            class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-semibold rounded-lg transition"
          >
            {{ redeemLoading ? 'Claiming...' : 'Claim Code & Register' }}
          </button>
        </form>

        <div v-if="redeemSuccess" class="p-4 bg-emerald-950/40 border border-emerald-700 rounded-lg text-emerald-300 text-xs">
          {{ redeemSuccess }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const activeTab = ref('invitation');

const inviteForm = reactive({ email: '', role: 'teacher' });
const inviteLoading = ref(false);
const lastInvitation = ref(null);

const acceptForm = reactive({ token: '', name: '', password: '', phone: '' });
const acceptLoading = ref(false);
const acceptSuccess = ref(null);

const claimCodeForm = reactive({ role: 'student' });
const claimCodeLoading = ref(false);
const lastClaimCode = ref(null);

const redeemForm = reactive({ code: '', name: '', email: '', password: '' });
const redeemLoading = ref(false);
const redeemSuccess = ref(null);

async function sendInvite() {
  inviteLoading.value = true;
  try {
    const res = await axios.post('/admin/invitations', inviteForm);
    lastInvitation.value = res.data.data;
  } catch (err) {
    alert(err.response?.data?.error?.message || 'Failed to send invitation.');
  } finally {
    inviteLoading.value = false;
  }
}

async function acceptInvite() {
  acceptLoading.value = true;
  acceptSuccess.value = null;
  try {
    const res = await axios.post('/invitations/accept', acceptForm);
    const { token, user } = res.data.data;
    authStore.token = token;
    authStore.user = user;
    localStorage.setItem('auth_token', token);
    localStorage.setItem('auth_user', JSON.stringify(user));
    acceptSuccess.value = `Success! Account created for ${user.name} (${user.role}). Automatically signed in.`;
  } catch (err) {
    alert(err.response?.data?.error?.message || 'Failed to accept invitation.');
  } finally {
    acceptLoading.value = false;
  }
}

async function generateCode() {
  claimCodeLoading.value = true;
  try {
    const res = await axios.post('/admin/claim-codes', claimCodeForm);
    lastClaimCode.value = res.data.data;
  } catch (err) {
    alert(err.response?.data?.error?.message || 'Failed to generate claim code.');
  } finally {
    claimCodeLoading.value = false;
  }
}

async function redeemCode() {
  redeemLoading.value = true;
  redeemSuccess.value = null;
  try {
    const res = await axios.post('/claim-codes/claim', redeemForm);
    const { token, user } = res.data.data;
    authStore.token = token;
    authStore.user = user;
    localStorage.setItem('auth_token', token);
    localStorage.setItem('auth_user', JSON.stringify(user));
    redeemSuccess.value = `Success! Claimed account for ${user.name} (${user.role}). Automatically signed in.`;
  } catch (err) {
    alert(err.response?.data?.error?.message || 'Failed to claim code.');
  } finally {
    redeemLoading.value = false;
  }
}
</script>
