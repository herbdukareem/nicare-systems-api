<template>
  <div class="portal-assistant">
    <transition name="assistant-panel">
      <section
        v-if="isOpen"
        class="assistant-window"
        aria-label="NiCare portal assistant"
      >
        <header class="assistant-header">
          <div class="assistant-avatar">
            <v-icon size="20">mdi-creation-outline</v-icon>
          </div>
          <div class="assistant-title">
            <h2>NiCare A.I.</h2>
            <p>Ask me about enrollment, approvals, claims and payments</p>
          </div>
          <button class="assistant-close" type="button" aria-label="Close assistant" @click="isOpen = false">
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </header>

        <div ref="messagesEl" class="assistant-messages">
          <div
            v-for="message in messages"
            :key="message.id"
            class="assistant-message"
            :class="message.role === 'user' ? 'assistant-message--user' : 'assistant-message--bot'"
          >
            <p v-for="(line, index) in message.lines" :key="index">{{ line }}</p>
          </div>

          <div v-if="isTyping" class="assistant-message assistant-message--bot assistant-typing">
            <span />
            <span />
            <span />
          </div>
        </div>

        <div class="assistant-suggestions">
          <button
            v-for="suggestion in suggestions"
            :key="suggestion"
            type="button"
            @click="submitQuestion(suggestion)"
          >
            {{ suggestion }}
          </button>
        </div>

        <form class="assistant-input" @submit.prevent="submitQuestion(draft)">
          <input
            v-model="draft"
            type="text"
            placeholder="Type your question..."
            autocomplete="off"
          />
          <button type="submit" :disabled="!draft.trim() || isTyping" aria-label="Send question">
            <v-icon size="20">mdi-send</v-icon>
          </button>
        </form>
      </section>
    </transition>

    <button
      class="assistant-launcher"
      :class="{ 'assistant-launcher--hidden': isOpen }"
      type="button"
      aria-label="Open NiCare assistant"
      @click="isOpen = true"
    >
      <span class="assistant-launcher__icon">
        <v-icon size="21">mdi-creation-outline</v-icon>
      </span>
      <span>Ask NiCare A.I</span>
    </button>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { assistantAPI } from '../../utils/assistantApi';

const route = useRoute();
const isOpen = ref(false);
const isTyping = ref(false);
const draft = ref('');
const messagesEl = ref(null);
let isUnmounted = false;

const messages = ref([
  makeMessage(
    'assistant',
    'Hello. I can help with common NiCare portal questions on enrollment, approvals, NIN verification, claims, capitation, facilities, roles, reports and payments.'
  ),
]);

const suggestionSets = {
  dashboard: ['Explain active coverage', 'What is programme mix?', 'How is LGA reach calculated?'],
  enrollment: ['How do I approve an enrollee?', 'How do I verify NIN?', 'Where are pending enrollees?'],
  claims: ['How do claims work?', 'How do referrals work?', 'Where are payment batches?'],
  capitation: ['Explain capitation', 'Generated vs paid capitation', 'How do I view facilities?'],
  premium: ['How do premium payments work?', 'How do PINs work?', 'How do renewals work?'],
  default: ['How do I approve an enrollee?', 'Explain capitation', 'How do claims work?'],
};

const suggestions = computed(() => {
  const path = route.path.toLowerCase();

  if (path.includes('enrollee') || path.includes('enrollment')) return suggestionSets.enrollment;
  if (path.includes('claim') || path.includes('referral')) return suggestionSets.claims;
  if (path.includes('capitation')) return suggestionSets.capitation;
  if (path.includes('premium') || path.includes('payment')) return suggestionSets.premium;
  if (path.includes('dashboard')) return suggestionSets.dashboard;

  return suggestionSets.default;
});

const answers = [
  {
    keywords: ['approve', 'approval', 'pending', 'review'],
    answer: [
      'To approve an enrollee, open Enrollment, then Pending Approval or Enrollment Approval.',
      'Review the submitted profile, compare any available NIN/provider information, confirm facility and programme details, then use the approval action.',
      'If an approval button is unavailable, check that your role has the required permission and that the enrollee has the required data.',
    ],
  },
  {
    keywords: ['nin', 'verify', 'verification', 'identity'],
    answer: [
      'For NIN verification, open the enrollee record and use the NIN verification action where available.',
      'The comparison view helps you review differences between submitted enrollee data and provider-returned NIN data before you make an approval decision.',
    ],
  },
  {
    keywords: ['capitation', 'generated', 'paid', 'facility payment'],
    answer: [
      'Capitation shows monthly facility payment obligations.',
      'Generated is the amount calculated for a period. Paid is the amount already disbursed. Use the capitation workspace or payment report to review facilities and payment progress.',
    ],
  },
  {
    keywords: ['claim', 'claims', 'admission', 'review'],
    answer: [
      'Claims move through submission, review, approval and payment workflows.',
      'Facility users submit claims where permitted. Reviewers validate the service details, supporting records and eligibility before approval or rejection.',
    ],
  },
  {
    keywords: ['referral', 'utn', 'fupa', 'pas'],
    answer: [
      'Referral and PAS tools help manage authorization, UTN validation and facility assignment workflows.',
      'Open the PAS menu for FUPA requests, approvals, document requirements, UTN validation and referral tracking.',
    ],
  },
  {
    keywords: ['facility', 'provider', 'accredited', 'lga', 'ward'],
    answer: [
      'Facility records are managed from the Facilities menu.',
      'Use facility status, LGA, ward and accreditation details to keep provider records accurate for enrollment, claims and capitation workflows.',
    ],
  },
  {
    keywords: ['payment', 'premium', 'pin', 'renewal', 'invoice', 'gateway'],
    answer: [
      'Premium and payment tools are under Premium & Enrollment and related payment pages.',
      'You can manage premium plans, purchases, payment collection settings, PINs and coverage renewals depending on your permissions.',
    ],
  },
  {
    keywords: ['dashboard', 'coverage', 'programme mix', 'active coverage', 'lga reach', 'geo reach'],
    answer: [
      'The dashboard summarizes enrollee coverage, approval activity, programme distribution, geographic reach and financial activity.',
      'Active Coverage counts lives currently eligible. Programme Mix shows enrollee distribution by insurance programme. LGA Reach compares LGAs with enrollee records against total LGAs.',
    ],
  },
  {
    keywords: ['role', 'permission', 'access', 'user', 'users'],
    answer: [
      'If a menu or action is missing, it is usually controlled by role permissions.',
      'A super admin can review users, roles and permissions from the Setup or Security areas, depending on how the workspace is configured.',
    ],
  },
  {
    keywords: ['report', 'reports', 'analytics', 'export', 'download'],
    answer: [
      'Reports and analytics are available from the Reports, Dashboard, Claims, Capitation and Payments areas.',
      'Where export buttons are available, use them to download filtered records for reconciliation or review.',
    ],
  },
  {
    keywords: ['password', 'login', 'logout', 'sign in', 'account'],
    answer: [
      'For login or password issues, confirm the username and password first, then try resetting or changing the password where that option is available.',
      'If access still fails, contact the system administrator so they can confirm your account status and assigned role.',
    ],
  },
];

function makeMessage(role, text) {
  const lines = Array.isArray(text) ? text : String(text).split('\n');

  return {
    id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    role,
    lines,
  };
}

async function submitQuestion(question) {
  const cleanQuestion = String(question || '').trim();
  if (!cleanQuestion || isTyping.value) return;

  messages.value.push(makeMessage('user', cleanQuestion));
  draft.value = '';
  isTyping.value = true;

  try {
    const response = await assistantAPI.chat({
      message: cleanQuestion,
      context: {
        route: route.path,
        page_title: route.meta?.title || document.title || 'NiCare portal',
      },
    });

    const answer = response.data?.data?.answer || response.data?.message;
    if (!isUnmounted) {
      messages.value.push(makeMessage('assistant', answer || findAnswer(cleanQuestion)));
    }
  } catch (error) {
    if (!isUnmounted) {
      messages.value.push(makeMessage('assistant', [
        error.response?.data?.message || 'I could not reach the AI assistant right now.',
        ...findAnswer(cleanQuestion),
      ]));
    }
  } finally {
    if (!isUnmounted) {
      isTyping.value = false;
    }
  }
}

function findAnswer(question) {
  const normalized = question.toLowerCase();
  const match = answers.find((entry) => entry.keywords.some((keyword) => normalized.includes(keyword)));

  if (match) return match.answer;

  return [
    'I can help with common NiCare portal tasks like enrollment approval, NIN verification, claims, capitation, facilities, payments, reports and access.',
    'Try asking a specific question such as "How do I approve an enrollee?" or "Explain capitation generated vs paid".',
  ];
}

function scrollToBottom() {
  nextTick(() => {
    if (!messagesEl.value) return;
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
  });
}

watch(messages, scrollToBottom, { deep: true });
watch(isTyping, scrollToBottom);
watch(isOpen, (open) => {
  if (open) scrollToBottom();
});

onBeforeUnmount(() => {
  isUnmounted = true;
});
</script>

<style scoped>
.portal-assistant {
  position: fixed;
  right: 24px;
  bottom: 22px;
  z-index: 90;
  font-family: inherit;
}

.assistant-window {
  width: min(390px, calc(100vw - 28px));
  height: min(560px, calc(100vh - 92px));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  box-shadow: 0 18px 44px rgba(15, 23, 42, 0.18);
}

.assistant-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
  color: #0f172a;
}

.assistant-avatar,
.assistant-launcher__icon {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 8px;
  background: #ecfeff;
  color: #0e7490;
}

.assistant-avatar {
  width: 42px;
  height: 42px;
  border: 1px solid #bae6fd;
}

.assistant-title {
  min-width: 0;
  flex: 1;
}

.assistant-title h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  line-height: 1.1;
  color: #0f172a;
}

.assistant-title p {
  margin: 3px 0 0;
  max-width: 280px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.25;
  color: #64748b;
}

.assistant-close {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  cursor: pointer;
}

.assistant-close:hover {
  background: #e2e8f0;
}

.assistant-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px 16px 10px;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.assistant-messages::-webkit-scrollbar {
  display: none;
}

.assistant-message {
  width: fit-content;
  max-width: 92%;
  margin-bottom: 10px;
  padding: 11px 13px;
  border-radius: 8px;
  font-size: 14px;
  line-height: 1.45;
}

.assistant-message p {
  margin: 0;
}

.assistant-message p + p {
  margin-top: 9px;
}

.assistant-message--bot {
  color: #0f172a;
  border: 1px solid #e2e8f0;
  background: #fff;
}

.assistant-message--user {
  margin-left: auto;
  color: #fff;
  background: #0f766e;
}

.assistant-typing {
  display: flex;
  gap: 5px;
  padding: 13px 14px;
}

.assistant-typing span {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: #64748b;
  animation: assistantTyping 0.9s infinite ease-in-out;
}

.assistant-typing span:nth-child(2) {
  animation-delay: 0.12s;
}

.assistant-typing span:nth-child(3) {
  animation-delay: 0.24s;
}

.assistant-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  overflow: visible;
  padding: 0 16px 12px;
}

.assistant-suggestions button {
  flex: 1 1 auto;
  border: 1px solid #bae6fd;
  border-radius: 8px;
  background: #ecfeff;
  color: #0e7490;
  padding: 7px 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.assistant-suggestions button:hover {
  background: #cffafe;
}

.assistant-input {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 14px;
  border-top: 1px solid #e2e8f0;
  background: #fff;
}

.assistant-input input {
  min-width: 0;
  flex: 1;
  height: 44px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0 16px;
  color: #0f172a;
  outline: none;
}

.assistant-input input:focus {
  border-color: #22d3ee;
  box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.18);
}

.assistant-input button {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 8px;
  background: #0e7490;
  color: #fff;
  cursor: pointer;
}

.assistant-input button:disabled {
  cursor: not-allowed;
  opacity: 0.48;
}

.assistant-launcher {
  display: flex;
  align-items: center;
  gap: 10px;
  border: 0;
  border-radius: 8px;
  padding: 13px 18px 13px 13px;
  background: #0e7490;
  color: #fff;
  font-size: 14px;
  font-weight: 800;
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
  cursor: pointer;
  transition: transform 0.18s ease, opacity 0.18s ease;
}

.assistant-launcher:hover {
  transform: translateY(-1px);
}

.assistant-launcher--hidden {
  pointer-events: none;
  opacity: 0;
  transform: translateY(8px);
}

.assistant-launcher__icon {
  width: 32px;
  height: 32px;
}

.assistant-panel-enter-active,
.assistant-panel-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.assistant-panel-enter-from,
.assistant-panel-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.98);
}

@keyframes assistantTyping {
  0%,
  80%,
  100% {
    opacity: 0.35;
    transform: translateY(0);
  }
  40% {
    opacity: 1;
    transform: translateY(-3px);
  }
}

@media (max-width: 640px) {
  .portal-assistant {
    right: 14px;
    bottom: 14px;
  }

  .assistant-window {
    width: calc(100vw - 28px);
    height: min(560px, calc(100vh - 72px));
    border-radius: 8px;
  }

  .assistant-launcher span:last-child {
    display: none;
  }

  .assistant-launcher {
    padding: 12px;
  }
}
</style>
