<template>
  <div class="gov">
    <!-- ── TOP STRIP ───────────────────────────────────────────────────── -->
    <div class="gov__topbar">
      <div class="gov__topbar-inner">
        <span><v-icon size="14">mdi-flag-outline</v-icon> Niger State Government</span>
        <span class="gov__topbar-sep">|</span>
        <span>An official agency website</span>
        <span class="gov__topbar-spacer" />
        <span>Hotline: {{ org.hotline }}</span>
      </div>
    </div>

    <!-- ── HEADER / NAV ────────────────────────────────────────────────── -->
    <header class="gov__header">
      <div class="gov__header-inner">
        <div class="gov__brand">
          <img v-if="org.logo_url && !logoErr" :src="org.logo_url" alt="Organization logo" class="gov__logo" @error="logoErr = true" />
          <div v-else class="gov__logo-fallback">
            <v-icon color="white" size="26">mdi-hospital-box</v-icon>
          </div>
          <div>
            <div class="gov__brand-name">{{ org.agency_name }}</div>
            <div class="gov__brand-sub">{{ org.scheme_name }} — {{ org.scheme_tagline }}</div>
          </div>
        </div>
        <nav class="gov__nav">
          <v-btn variant="text" class="gov__nav-link" @click="$router.push('/login')">
            <v-icon start size="18">mdi-shield-account-outline</v-icon> Staff Login
          </v-btn>
          <v-btn variant="flat" color="primary" class="gov__nav-cta" @click="$router.push('/enroll/login')">
            <v-icon start size="18">mdi-account-circle-outline</v-icon> Enrollee Portal
          </v-btn>
        </nav>
      </div>
    </header>

    <!-- ── NOTICE BAR ──────────────────────────────────────────────────── -->
    <div class="gov__notice">
      <div class="gov__notice-inner">
        <span class="gov__notice-tag">Notice</span>
        <span>Self-service enrollment is open. New applicants can register online and track approval status from the enrollee portal.</span>
      </div>
    </div>

    <!-- ── INTRO BANNER ────────────────────────────────────────────────── -->
    <section class="gov__banner">
      <div class="gov__banner-inner">
        <h1 class="gov__banner-title">{{ org.hero_title }}</h1>
        <p class="gov__banner-sub">{{ org.hero_description }}</p>
        <div class="gov__banner-actions">
          <v-btn color="primary" variant="flat" size="large" @click="router.push('/enroll/start')">
            Apply for enrollment
          </v-btn>
          <v-btn variant="outlined" color="primary" size="large" @click="router.push('/enroll/login')">
            Check application status
          </v-btn>
          <v-btn variant="text" color="primary" size="large" @click="router.push('/enroll/pins')">
            Purchase Premium PINs
          </v-btn>
        </div>
      </div>
      <div class="gov__banner-figures">
        <div v-for="s in stats" :key="s.label" class="gov__figure">
          <div class="gov__figure-num">{{ s.value }}</div>
          <div class="gov__figure-lbl">{{ s.label }}</div>
        </div>
      </div>
    </section>

    <!-- ── SERVICES ────────────────────────────────────────────────────── -->
    <section class="gov__section">
      <div class="gov__section-inner">
        <h2 class="gov__section-title">Services</h2>
        <div class="gov__divider" />

        <div class="gov__services">
          <button
            v-for="card in actionCards"
            :key="card.title"
            type="button"
            class="gov__service"
            @click="handleCard(card)"
          >
            <div class="qds-icon-shell" :class="card.toneClass">
              <v-icon :icon="card.icon" size="20" />
            </div>
            <div class="gov__service-body">
              <div class="gov__service-tag">{{ card.tag }}</div>
              <h3 class="gov__service-title">{{ card.title }}</h3>
              <p class="gov__service-desc">{{ card.desc }}</p>
            </div>
            <v-icon size="18" class="gov__service-arrow">mdi-chevron-right</v-icon>
          </button>
        </div>
      </div>
    </section>

    <!-- ── HOW ENROLLMENT WORKS ────────────────────────────────────────── -->
    <section class="gov__section gov__section--muted">
      <div class="gov__section-inner">
        <h2 class="gov__section-title">How Enrollment Works</h2>
        <div class="gov__divider" />

        <ol class="gov__steps">
          <li v-for="(step, i) in steps" :key="i" class="gov__step">
            <span class="gov__step-num">{{ i + 1 }}</span>
            <div>
              <h4 class="gov__step-title">{{ step.title }}</h4>
              <p class="gov__step-desc">{{ step.desc }}</p>
            </div>
          </li>
        </ol>
      </div>
    </section>

    <!-- ── FOOTER ──────────────────────────────────────────────────────── -->
    <footer class="gov__footer">
      <div class="gov__footer-inner">
        <div class="gov__footer-col">
          <div class="gov__footer-brand">
            <img v-if="org.logo_url && !logoErr" :src="org.logo_url" alt="Organization logo" class="gov__footer-logo" />
            <span>{{ org.about_title }}</span>
          </div>
          <p class="gov__footer-text">{{ org.about_description }}</p>
        </div>
        <div class="gov__footer-col">
          <h5 class="gov__footer-heading">Contact</h5>
          <ul class="gov__footer-list">
            <li>Hotline: {{ org.hotline }}</li>
            <li>{{ org.website }}</li>
            <li>{{ org.address }}</li>
          </ul>
        </div>
        <div class="gov__footer-col">
          <h5 class="gov__footer-heading">Quick Links</h5>
          <ul class="gov__footer-list gov__footer-list--links">
            <li><a @click="router.push('/enroll/start')">Apply for enrollment</a></li>
            <li><a @click="router.push('/enroll/pins')">Purchase Premium PINs</a></li>
            <li><a @click="router.push('/enroll/login')">Enrollee portal</a></li>
            <li><a @click="router.push('/login')">Staff &amp; admin login</a></li>
          </ul>
        </div>
      </div>
      <div class="gov__footer-bottom">
        <span>&copy; {{ new Date().getFullYear() }} {{ org.agency_name }}. All rights reserved.</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useOrganizationSettings } from '../../composables/useOrganizationSettings';

const router = useRouter();
const logoErr = ref(false);

const { settings: org, fetchSettings } = useOrganizationSettings();
onMounted(fetchSettings);

const stats = [
  { value: '100,000+', label: 'Enrollees registered' },
  { value: '200+', label: 'Approved facilities' },
  { value: '25', label: 'Local Government Areas' },
  { value: '24/7', label: 'Support hotline' },
];

const actionCards = [
  {
    icon: 'mdi-card-account-details-star-outline',
    tag: 'New applicant',
    toneClass: 'qds-tone-primary',
    title: 'Apply for enrollment',
    desc: 'Select an approved premium plan, submit your biodata and facility preference, and track your application online.',
    action: 'enroll',
  },
  {
    icon: 'mdi-refresh',
    tag: 'Existing enrollee',
    toneClass: 'qds-tone-success',
    title: 'Renew your premium plan',
    desc: 'Renew an active or expired plan to keep your health coverage in good standing.',
    action: 'renew',
  },
  {
    icon: 'mdi-account-circle-outline',
    tag: 'Enrollee portal',
    toneClass: 'qds-tone-secondary',
    title: 'Manage your account',
    desc: 'Sign in to view your coverage status, download your ID card, and update your profile.',
    action: 'portal',
  },
  {
    icon: 'mdi-shield-account-outline',
    tag: 'Staff & admin',
    toneClass: 'qds-tone-neutral',
    title: 'Staff and admin login',
    desc: 'Agency staff, facility users, enrollment officers, and administrators sign in to the management system.',
    action: 'admin',
  },
];

const steps = [
  {
    title: 'Choose a plan',
    desc: 'Review the public premium plans open for self-enrollment and select the one that fits your household.',
  },
  {
    title: 'Submit your application',
    desc: 'Provide your biodata, National Identification Number, and preferred healthcare facility.',
  },
  {
    title: 'Approval and verification',
    desc: 'An enrollment officer reviews your application and verifies your identity before approval.',
  },
  {
    title: 'Coverage activated',
    desc: 'Once approved, your coverage is activated and you may begin accessing care at your chosen facility.',
  },
];

const handleCard = (card) => {
  if (card.action === 'portal' || card.action === 'renew') {
    router.push('/enroll/login');
  } else if (card.action === 'admin') {
    router.push('/login');
  } else {
    router.push('/enroll/start');
  }
};
</script>

<style scoped>
.gov {
  min-height: 100vh;
  background: var(--qds-color-surface);
  color: var(--qds-color-text);
  font-family: var(--qds-font-sans);
}

/* TOP STRIP */
.gov__topbar {
  background: #0b1f33;
  color: rgba(255, 255, 255, 0.78);
  font-size: 12px;
}
.gov__topbar-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 6px 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.gov__topbar-inner > span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.gov__topbar-sep { opacity: 0.4; }
.gov__topbar-spacer { flex: 1; }

/* HEADER */
.gov__header {
  background: var(--qds-color-surface);
  border-bottom: 1px solid var(--qds-color-border);
}
.gov__header-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 14px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.gov__brand {
  display: flex;
  align-items: center;
  gap: 12px;
}
.gov__logo,
.gov__logo-fallback {
  height: 44px;
  width: 44px;
  object-fit: contain;
  border: 1px solid var(--qds-color-border);
  background: var(--qds-color-surface-muted);
  display: grid;
  place-items: center;
}
.gov__logo-fallback {
  background: var(--qds-color-primary);
  border-color: var(--qds-color-primary);
}
.gov__brand-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--qds-color-text);
  line-height: 1.25;
}
.gov__brand-sub {
  font-size: 12px;
  color: var(--qds-color-text-secondary);
  margin-top: 1px;
}
.gov__nav {
  display: flex;
  align-items: center;
  gap: 8px;
}
.gov__nav-link {
  color: var(--qds-color-text-secondary) !important;
}
.gov__nav-cta {
  letter-spacing: 0;
}

/* NOTICE BAR */
.gov__notice {
  background: rgba(11, 107, 121, 0.08);
  border-bottom: 1px solid rgba(11, 107, 121, 0.16);
}
.gov__notice-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 8px 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--qds-color-text);
}
.gov__notice-tag {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--qds-color-primary);
  border: 1px solid rgba(11, 107, 121, 0.3);
  padding: 2px 8px;
}

/* BANNER */
.gov__banner {
  background: var(--qds-color-surface-muted);
  border-bottom: 1px solid var(--qds-color-border);
  padding: 40px 24px;
}
.gov__banner-inner {
  max-width: 820px;
  margin: 0 auto;
  text-align: center;
}
.gov__banner-title {
  font-size: clamp(24px, 3.4vw, 34px);
  font-weight: 800;
  line-height: 1.25;
  color: var(--qds-color-text);
  margin-bottom: 14px;
}
.gov__banner-sub {
  font-size: 15px;
  color: var(--qds-color-text-secondary);
  line-height: 1.6;
  margin-bottom: 22px;
}
.gov__banner-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}
.gov__banner-figures {
  max-width: 1000px;
  margin: 32px auto 0;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border: 1px solid var(--qds-color-border);
  background: var(--qds-color-surface);
}
.gov__figure {
  text-align: center;
  padding: 16px 12px;
  border-right: 1px solid var(--qds-color-border);
}
.gov__figure:last-child { border-right: none; }
.gov__figure-num {
  font-size: 22px;
  font-weight: 800;
  color: var(--qds-color-primary);
}
.gov__figure-lbl {
  font-size: 12px;
  color: var(--qds-color-text-secondary);
  margin-top: 2px;
}

/* SECTIONS */
.gov__section {
  padding: 44px 24px;
}
.gov__section--muted {
  background: var(--qds-color-surface-muted);
  border-top: 1px solid var(--qds-color-border);
  border-bottom: 1px solid var(--qds-color-border);
}
.gov__section-inner {
  max-width: 1200px;
  margin: 0 auto;
}
.gov__section-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--qds-color-text);
}
.gov__divider {
  height: 3px;
  width: 56px;
  background: var(--qds-color-primary);
  margin: 10px 0 28px;
}

/* SERVICES */
.gov__services {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 0;
  border: 1px solid var(--qds-color-border);
}
.gov__service {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  text-align: left;
  background: var(--qds-color-surface);
  border: none;
  border-right: 1px solid var(--qds-color-border);
  border-bottom: 1px solid var(--qds-color-border);
  padding: 18px;
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.gov__service:hover {
  background: var(--qds-color-surface-muted);
}
.gov__service-body {
  flex: 1;
  min-width: 0;
}
.gov__service-tag {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--qds-color-text-muted);
  margin-bottom: 4px;
}
.gov__service-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--qds-color-text);
  margin-bottom: 4px;
}
.gov__service-desc {
  font-size: 13px;
  color: var(--qds-color-text-secondary);
  line-height: 1.55;
}
.gov__service-arrow {
  color: var(--qds-color-text-muted);
  margin-top: 4px;
  flex-shrink: 0;
}

/* STEPS */
.gov__steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 0;
  border: 1px solid var(--qds-color-border);
  background: var(--qds-color-surface);
}
.gov__step {
  display: flex;
  gap: 12px;
  padding: 18px;
  border-right: 1px solid var(--qds-color-border);
  border-bottom: 1px solid var(--qds-color-border);
}
.gov__step-num {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  font-size: 13px;
  font-weight: 800;
  color: var(--qds-color-primary);
  border: 1px solid var(--qds-color-primary);
}
.gov__step-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--qds-color-text);
  margin-bottom: 4px;
}
.gov__step-desc {
  font-size: 13px;
  color: var(--qds-color-text-secondary);
  line-height: 1.55;
}

/* FOOTER */
.gov__footer {
  background: #0b1f33;
  color: rgba(255, 255, 255, 0.72);
}
.gov__footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 36px 24px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 28px;
}
.gov__footer-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  color: white;
  font-size: 14px;
  margin-bottom: 10px;
}
.gov__footer-logo {
  height: 28px;
  width: 28px;
  object-fit: contain;
}
.gov__footer-text {
  font-size: 13px;
  line-height: 1.6;
}
.gov__footer-heading {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: white;
  margin-bottom: 10px;
}
.gov__footer-list {
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 13px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.gov__footer-list--links a {
  color: rgba(255, 255, 255, 0.72);
  cursor: pointer;
  text-decoration: none;
}
.gov__footer-list--links a:hover {
  color: white;
  text-decoration: underline;
}
.gov__footer-bottom {
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  padding: 14px 24px;
  text-align: center;
  font-size: 12px;
}

@media (max-width: 720px) {
  .gov__banner-figures {
    grid-template-columns: repeat(2, 1fr);
  }
  .gov__figure:nth-child(2) { border-right: none; }
}
</style>
