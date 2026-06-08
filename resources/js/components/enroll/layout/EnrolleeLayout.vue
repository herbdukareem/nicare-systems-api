<template>
  <div class="el-wrap">
    <!-- TOP NAV -->
    <header class="el-nav">
      <div class="el-nav__inner">
        <div class="el-nav__brand" @click="$router.push('/enroll/dashboard')">
          <img v-if="org.logo_url && !logoErr" :src="org.logo_url" alt="Organization logo" class="el-nav__logo" @error="logoErr = true" />
          <div v-else class="el-nav__logo-fb"><v-icon color="white" size="20">mdi-hospital-box</v-icon></div>
          <div>
            <span class="el-nav__name">{{ org.scheme_name }}</span>
            <span class="el-nav__sub tw-hidden sm:tw-inline"> · Enrollee Portal</span>
          </div>
        </div>

        <!-- Desktop links -->
        <nav class="el-nav__links tw-hidden md:tw-flex">
          <router-link v-for="l in links" :key="l.to" :to="l.to" class="el-nav__link" active-class="el-nav__link--active">
            <v-icon size="16">{{ l.icon }}</v-icon> {{ l.label }}
          </router-link>
        </nav>

        <div class="el-nav__right">
          <div class="el-nav__user tw-hidden sm:tw-flex">
            <v-avatar size="32" color="rgba(255,255,255,0.15)">
              <v-icon color="white" size="20">mdi-account</v-icon>
            </v-avatar>
            <div class="tw-hidden md:tw-block">
              <div class="el-nav__user-name">{{ enrolleeAuth.fullName || 'Enrollee' }}</div>
              <div class="el-nav__user-id">{{ enrolleeAuth.enrolleeId }}</div>
            </div>
          </div>
          <v-btn icon variant="text" color="rgba(255,255,255,0.8)" size="small" @click="handleLogout" :loading="loggingOut">
            <v-icon>mdi-logout</v-icon>
            <v-tooltip activator="parent">Sign Out</v-tooltip>
          </v-btn>
          <!-- Mobile menu toggle -->
          <v-btn icon variant="text" color="rgba(255,255,255,0.8)" size="small" class="md:tw-hidden" @click="drawerOpen = !drawerOpen">
            <v-icon>mdi-menu</v-icon>
          </v-btn>
        </div>
      </div>
    </header>

    <!-- Mobile drawer -->
    <v-navigation-drawer v-model="drawerOpen" temporary location="left" width="240">
      <div class="tw-p-4 tw-bg-primary tw-text-white">
        <div class="tw-font-bold">{{ enrolleeAuth.fullName || 'Enrollee' }}</div>
        <div class="tw-text-xs tw-opacity-75">{{ enrolleeAuth.enrolleeId }}</div>
      </div>
      <v-list density="compact" nav>
        <v-list-item
          v-for="l in links"
          :key="l.to"
          :to="l.to"
          :prepend-icon="l.icon"
          :title="l.label"
          @click="drawerOpen = false"
          rounded="lg"
        />
        <v-divider class="my-2" />
        <v-list-item prepend-icon="mdi-logout" title="Sign Out" @click="handleLogout" color="error" rounded="lg" />
      </v-list>
    </v-navigation-drawer>

    <!-- PAGE CONTENT -->
    <main class="el-main">
      <div class="el-main__inner">
        <!-- Password change nudge -->
        <AppAlert
          v-if="!enrolleeAuth.hasCustomPassword"
          class="tw-mb-5"
          tone="warning"
          title="Secure your portal account"
        >
          Your default password is your NIN.
          <router-link to="/enroll/change-password" class="tw-font-semibold tw-underline">Set a secure password →</router-link>
        </AppAlert>

        <slot />
      </div>
    </main>

    <!-- FOOTER -->
    <footer class="el-footer">
      <div class="el-footer__inner">
        <span>© {{ new Date().getFullYear() }} {{ org.agency_name }}</span>
        <span class="tw-hidden sm:tw-inline">·</span>
        <span class="tw-hidden sm:tw-inline">Hotline: {{ org.hotline }}</span>
        <span class="tw-hidden sm:tw-inline">·</span>
        <span class="tw-hidden sm:tw-inline">{{ org.website }}</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useEnrolleeAuthStore } from '../../../stores/enrolleeAuth';
import { useToast } from '../../../composables/useToast';
import { useOrganizationSettings } from '../../../composables/useOrganizationSettings';
import AppAlert from '../../common/AppAlert.vue';

const router = useRouter();
const enrolleeAuth = useEnrolleeAuthStore();
const { success } = useToast();
const { settings: org, fetchSettings } = useOrganizationSettings();

onMounted(fetchSettings);

const logoErr = ref(false);
const drawerOpen = ref(false);
const loggingOut = ref(false);

const links = [
  { to: '/enroll/dashboard',       label: 'Dashboard',       icon: 'mdi-view-dashboard-outline' },
  { to: '/enroll/plans',           label: 'Premium Plans',   icon: 'mdi-shield-star-outline' },
  { to: '/enroll/profile',         label: 'My Profile',      icon: 'mdi-account-outline' },
  { to: '/enroll/change-password', label: 'Change Password', icon: 'mdi-lock-outline' },
];

const handleLogout = async () => {
  loggingOut.value = true;
  await enrolleeAuth.logout();
  success('Signed out successfully.');
  router.replace('/enroll/login');
  loggingOut.value = false;
};
</script>

<style scoped>
.el-wrap {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f1f5f9;
}

.el-nav {
  background: linear-gradient(90deg, #0885ab 0%, #0d3b6e 100%);
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  position: sticky;
  top: 0;
  z-index: 40;
}
.el-nav__inner {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 20px;
  height: 60px;
  display: flex;
  align-items: center;
  gap: 20px;
}
.el-nav__brand {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  flex-shrink: 0;
}
.el-nav__logo {
  height: 36px;
  width: 36px;
  object-fit: contain;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
}
.el-nav__logo-fb {
  height: 36px;
  width: 36px;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
  display: grid;
  place-items: center;
}
.el-nav__name {
  font-size: 17px;
  font-weight: 800;
  color: white;
}
.el-nav__sub {
  font-size: 13px;
  color: rgba(255,255,255,0.7);
}
.el-nav__links {
  display: flex;
  gap: 4px;
  flex: 1;
  justify-content: center;
}
.el-nav__link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  color: rgba(255,255,255,0.75);
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}
.el-nav__link:hover { background: rgba(255,255,255,0.1); color: white; }
.el-nav__link--active { background: rgba(255,255,255,0.18); color: white; }
.el-nav__right {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 8px;
}
.el-nav__user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 12px 4px 4px;
  background: rgba(255,255,255,0.1);
  border-radius: 999px;
}
.el-nav__user-name {
  font-size: 13px;
  font-weight: 600;
  color: white;
  line-height: 1.2;
}
.el-nav__user-id {
  font-size: 11px;
  color: rgba(255,255,255,0.65);
}

.el-main {
  flex: 1;
  padding: 32px 20px;
}
.el-main__inner {
  max-width: 1280px;
  margin: 0 auto;
}

.el-footer {
  background: #0f172a;
  padding: 16px 20px;
}
.el-footer__inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #64748b;
  flex-wrap: wrap;
}
</style>
