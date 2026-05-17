<template>
  <div class="landing">
    <!-- ── NAV ─────────────────────────────────────────────────────────── -->
    <nav class="landing__nav">
      <div class="landing__nav-inner">
        <div class="landing__brand">
          <img :src="'/logo.png'" alt="NiCare Logo" class="landing__logo" @error="logoErr = true" v-if="!logoErr" />
          <div v-else class="landing__logo-fallback">
            <v-icon color="white" size="28">mdi-hospital-box</v-icon>
          </div>
          <div>
            <div class="landing__brand-name">NiCare</div>
            <div class="landing__brand-sub">Niger State Health Insurance</div>
          </div>
        </div>
        <div class="landing__nav-actions">
          <v-btn variant="text" color="white" class="tw-hidden sm:tw-flex" @click="$router.push('/login')">
            <v-icon start>mdi-shield-account</v-icon> Staff Login
          </v-btn>
          <v-btn color="white" variant="outlined" rounded @click="$router.push('/enroll/login')">
            <v-icon start>mdi-account-circle</v-icon> Enrollee Portal
          </v-btn>
        </div>
      </div>
    </nav>

    <!-- ── HERO ─────────────────────────────────────────────────────────── -->
    <section class="landing__hero">
      <div class="landing__hero-inner">
        <div class="landing__hero-badge">
          <v-icon size="16" color="white">mdi-shield-check</v-icon>
          Official Government Health Insurance System
        </div>
        <h1 class="landing__hero-title">
          Niger State Contributory<br>
          <span class="landing__hero-accent">Health Agency</span>
        </h1>
        <p class="landing__hero-sub">
          A reliable scheme that ensures access to affordable quality healthcare to all the people of Niger State.
        </p>
        <div class="landing__hero-btns">
          <v-btn size="large" color="white" rounded class="landing__btn-primary" @click="scrollToActions">
            <v-icon start>mdi-arrow-right-circle</v-icon> Get Started
          </v-btn>
          <v-btn size="large" variant="outlined" color="white" rounded @click="$router.push('/enroll/login')">
            My Portal
          </v-btn>
        </div>

        <!-- Stats bar -->
        <div class="landing__stats">
          <div v-for="s in stats" :key="s.label" class="landing__stat">
            <div class="landing__stat-num">{{ s.value }}</div>
            <div class="landing__stat-lbl">{{ s.label }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── ACTION CARDS ──────────────────────────────────────────────────── -->
    <section id="actions" class="landing__actions">
      <div class="landing__actions-inner">
        <div class="landing__section-head">
          <h2 class="landing__section-title">How can we help you today?</h2>
          <p class="landing__section-sub">Choose the option that applies to you below</p>
        </div>

        <div class="landing__cards">
          <div
            v-for="card in actionCards"
            :key="card.title"
            class="landing__card"
            @click="handleCard(card)"
          >
            <div class="landing__card-icon" :style="{ background: card.iconBg }">
              <v-icon :color="card.iconColor" size="32">{{ card.icon }}</v-icon>
            </div>
            <div class="landing__card-tag" :style="{ background: card.tagBg, color: card.tagColor }">
              {{ card.tag }}
            </div>
            <h3 class="landing__card-title">{{ card.title }}</h3>
            <p class="landing__card-desc">{{ card.desc }}</p>
            <div class="landing__card-action">
              {{ card.cta }} <v-icon size="18">mdi-arrow-right</v-icon>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── HOW IT WORKS ─────────────────────────────────────────────────── -->
    <section class="landing__how">
      <div class="landing__how-inner">
        <div class="landing__section-head">
          <h2 class="landing__section-title">How Enrollment Works</h2>
          <p class="landing__section-sub">Three simple steps to get covered</p>
        </div>
        <div class="landing__steps">
          <div v-for="(step, i) in steps" :key="i" class="landing__step">
            <div class="landing__step-num">{{ i + 1 }}</div>
            <v-icon size="36" :color="step.color" class="tw-mb-3">{{ step.icon }}</v-icon>
            <h4 class="landing__step-title">{{ step.title }}</h4>
            <p class="landing__step-desc">{{ step.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── FOOTER ────────────────────────────────────────────────────────── -->
    <footer class="landing__footer">
      <div class="landing__footer-inner">
        <div class="tw-flex tw-items-center tw-gap-3 tw-mb-3">
          <img :src="'/logo.png'" alt="Logo" class="tw-h-8 tw-w-8 tw-object-contain" v-if="!logoErr" />
          <span class="tw-font-bold tw-text-white">NiCare — Niger State Contributory Health Agency</span>
        </div>
        <p class="tw-text-slate-400 tw-text-sm tw-mb-4">
          Providing quality, affordable healthcare access for all Niger State residents.
        </p>
        <div class="tw-flex tw-flex-wrap tw-gap-4 tw-text-sm tw-text-slate-400">
          <span>Hotline: 08162653801</span>
          <span>·</span>
          <span>nicare.nigerstate.gov.ng</span>
          <span>·</span>
          <span>© {{ new Date().getFullYear() }} NGSCHA</span>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const logoErr = ref(false);

const stats = [
  { value: '100K+', label: 'Enrollees' },
  { value: '200+',  label: 'Facilities' },
  { value: '36',    label: 'LGAs Covered' },
  { value: '24/7',  label: 'Support' },
];

const actionCards = [
  {
    icon: 'mdi-card-account-details-star',
    iconBg: '#eff6ff',
    iconColor: '#2563eb',
    tag: 'New Enrollee',
    tagBg: '#dbeafe',
    tagColor: '#1d4ed8',
    title: 'Buy Premium PIN & Enroll',
    desc: 'New to NiCare? Purchase a premium PIN, register as a beneficiary, and gain access to quality healthcare.',
    cta: 'Start enrollment',
    action: 'enroll',
  },
  {
    icon: 'mdi-refresh-circle',
    iconBg: '#f0fdf4',
    iconColor: '#16a34a',
    tag: 'Renewal',
    tagBg: '#dcfce7',
    tagColor: '#15803d',
    title: 'Renew My Premium Plan',
    desc: 'Already enrolled? Renew your premium plan to keep your health coverage active and up to date.',
    cta: 'Renew plan',
    action: 'renew',
  },
  {
    icon: 'mdi-account-circle',
    iconBg: '#faf5ff',
    iconColor: '#9333ea',
    tag: 'Enrollee Portal',
    tagBg: '#f3e8ff',
    tagColor: '#7e22ce',
    title: 'Access My Enrollee Portal',
    desc: 'Log in to view your coverage status, ID card, profile details, and manage your account.',
    cta: 'Log in to portal',
    action: 'portal',
  },
  {
    icon: 'mdi-shield-account',
    iconBg: '#fff7ed',
    iconColor: '#ea580c',
    tag: 'Staff / Admin',
    tagBg: '#ffedd5',
    tagColor: '#c2410c',
    title: 'Staff & Admin Login',
    desc: 'Agency staff, facility users, enrollment officers, and administrators — access the management system.',
    cta: 'Staff login',
    action: 'admin',
  },
];

const steps = [
  {
    icon: 'mdi-cash-multiple',
    color: '#2563eb',
    title: 'Purchase a Premium PIN',
    desc: 'Visit any accredited NiCare agent or facility to purchase your premium PIN for your chosen health plan.',
  },
  {
    icon: 'mdi-account-edit',
    color: '#16a34a',
    title: 'Complete Registration',
    desc: 'Provide your personal details, NIN, and choose your preferred healthcare facility during enrollment.',
  },
  {
    icon: 'mdi-hospital-box',
    color: '#9333ea',
    title: 'Access Healthcare',
    desc: 'Once approved, visit your designated facility and access quality healthcare services with your NiCare ID.',
  },
];

const handleCard = (card) => {
  if (card.action === 'portal' || card.action === 'renew') {
    router.push('/enroll/login');
  } else if (card.action === 'admin') {
    router.push('/login');
  } else {
    // enroll: for now redirect to enrollee portal login where they can start
    router.push('/enroll/login');
  }
};

const scrollToActions = () => {
  document.getElementById('actions')?.scrollIntoView({ behavior: 'smooth' });
};
</script>

<style scoped>
.landing {
  min-height: 100vh;
  background: #f8fafc;
  font-family: 'Inter', system-ui, sans-serif;
}

/* NAV */
.landing__nav {
  position: sticky;
  top: 0;
  z-index: 50;
  background: rgba(8, 133, 171, 0.97);
  backdrop-filter: blur(8px);
  box-shadow: 0 1px 3px rgba(0,0,0,.15);
}
.landing__nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 12px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.landing__brand {
  display: flex;
  align-items: center;
  gap: 12px;
}
.landing__logo {
  height: 40px;
  width: 40px;
  object-fit: contain;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
}
.landing__logo-fallback {
  height: 40px;
  width: 40px;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
  display: grid;
  place-items: center;
}
.landing__brand-name {
  font-size: 18px;
  font-weight: 800;
  color: white;
  line-height: 1;
}
.landing__brand-sub {
  font-size: 10px;
  color: rgba(255,255,255,0.75);
  margin-top: 2px;
}
.landing__nav-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* HERO */
.landing__hero {
  background: linear-gradient(135deg, #0885ab 0%, #0d3b6e 60%, #1a1a2e 100%);
  padding: 80px 24px 100px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.landing__hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
                    radial-gradient(circle at 80% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
}
.landing__hero-inner {
  max-width: 900px;
  margin: 0 auto;
  position: relative;
}
.landing__hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.2);
  color: white;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.4px;
  margin-bottom: 28px;
}
.landing__hero-title {
  font-size: clamp(32px, 5vw, 56px);
  font-weight: 800;
  color: white;
  line-height: 1.15;
  margin-bottom: 20px;
}
.landing__hero-accent {
  color: #7dd3fc;
}
.landing__hero-sub {
  font-size: 18px;
  color: rgba(255,255,255,0.8);
  max-width: 640px;
  margin: 0 auto 36px;
  line-height: 1.6;
}
.landing__hero-btns {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 56px;
}
.landing__btn-primary {
  box-shadow: 0 4px 24px rgba(0,0,0,0.15);
}

/* STATS */
.landing__stats {
  display: flex;
  gap: 0;
  justify-content: center;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 16px;
  padding: 24px;
  flex-wrap: wrap;
}
.landing__stat {
  flex: 1;
  min-width: 120px;
  text-align: center;
  padding: 0 16px;
  border-right: 1px solid rgba(255,255,255,0.12);
}
.landing__stat:last-child { border-right: none; }
.landing__stat-num {
  font-size: 28px;
  font-weight: 800;
  color: white;
}
.landing__stat-lbl {
  font-size: 12px;
  color: rgba(255,255,255,0.65);
  margin-top: 4px;
}

/* ACTIONS */
.landing__actions {
  padding: 80px 24px;
  background: #f8fafc;
}
.landing__actions-inner {
  max-width: 1200px;
  margin: 0 auto;
}
.landing__section-head {
  text-align: center;
  margin-bottom: 48px;
}
.landing__section-title {
  font-size: clamp(24px, 3vw, 36px);
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 12px;
}
.landing__section-sub {
  font-size: 16px;
  color: #64748b;
}
.landing__cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 24px;
}
.landing__card {
  background: white;
  border-radius: 20px;
  padding: 32px 28px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04);
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  display: flex;
  flex-direction: column;
}
.landing__card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 32px rgba(8,133,171,0.12);
  border-color: #0885ab;
}
.landing__card-icon {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  display: grid;
  place-items: center;
  margin-bottom: 20px;
}
.landing__card-tag {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 12px;
  width: fit-content;
}
.landing__card-title {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 10px;
}
.landing__card-desc {
  font-size: 14px;
  color: #64748b;
  line-height: 1.6;
  flex: 1;
}
.landing__card-action {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 20px;
  font-size: 14px;
  font-weight: 600;
  color: #0885ab;
}

/* HOW */
.landing__how {
  padding: 80px 24px;
  background: linear-gradient(135deg, #0d3b6e 0%, #0885ab 100%);
}
.landing__how-inner {
  max-width: 1100px;
  margin: 0 auto;
}
.landing__how .landing__section-title,
.landing__how .landing__section-sub {
  color: white;
}
.landing__how .landing__section-sub {
  color: rgba(255,255,255,0.75);
}
.landing__steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 24px;
}
.landing__step {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 20px;
  padding: 36px 28px;
  text-align: center;
}
.landing__step-num {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255,255,255,0.2);
  color: white;
  font-size: 16px;
  font-weight: 700;
  display: grid;
  place-items: center;
  margin: 0 auto 16px;
}
.landing__step-title {
  font-size: 17px;
  font-weight: 700;
  color: white;
  margin-bottom: 10px;
}
.landing__step-desc {
  font-size: 14px;
  color: rgba(255,255,255,0.75);
  line-height: 1.6;
}

/* FOOTER */
.landing__footer {
  background: #0f172a;
  padding: 48px 24px;
}
.landing__footer-inner {
  max-width: 1200px;
  margin: 0 auto;
}
</style>
