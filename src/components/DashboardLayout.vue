<template>
  <div class="dashboard-container">
    <!-- Navbar -->
    <header class="dashboard-navbar glass-panel">
      <div class="navbar-brand">
        <img src="/logo.png" alt="Sathsewa Welfare Society logo" class="navbar-logo">
        <h2>Sathsewa Welfare Society</h2>
      </div>
      <div class="navbar-actions">
        <button @click="logout" class="btn btn-outline btn-sm">Logout</button>
      </div>
    </header>

    <div class="dashboard-body">
      <!-- Sidebar -->
      <aside class="dashboard-sidebar glass-panel">
        <nav class="sidebar-nav">
          <router-link to="/dashboard" class="nav-link" active-class="active">
            <span class="nav-icon icon-dashboard">
              <LayoutDashboard :size="17" />
            </span>
            Dashboard
          </router-link>
          <router-link to="/members" class="nav-link" active-class="active">
            <span class="nav-icon icon-members">
              <Users :size="17" />
            </span>
            Members
          </router-link>

          <button
            class="nav-link nav-link-toggle"
            :class="{ active: isReportsRoute }"
            type="button"
            @click="toggleReports"
          >
            <span class="nav-icon icon-reports">
              <FileBarChart2 :size="17" />
            </span>
            Reports
            <span class="caret">
              <ChevronDown v-if="reportsOpen" :size="16" />
              <ChevronRight v-else :size="16" />
            </span>
          </button>

          <div v-show="reportsOpen" class="sub-nav">
            <router-link to="/reports/members" class="sub-nav-link" active-class="active-sub">
              <UserSquare2 :size="14" />
              Member Report
            </router-link>
            <router-link to="/reports/financial" class="sub-nav-link" active-class="active-sub">
              <Wallet2 :size="14" />
              Financial Report
            </router-link>
          </div>
        </nav>
      </aside>

      <!-- Main Content -->
      <main class="dashboard-main">
        <div class="main-content-wrapper animate-fade-in">
          <slot></slot>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard,
  Users,
  FileBarChart2,
  ChevronDown,
  ChevronRight,
  UserSquare2,
  Wallet2
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const reportsOpen = ref(false)

const isReportsRoute = computed(() => route.path.startsWith('/reports'))

watch(
  () => route.path,
  (path) => {
    if (path.startsWith('/reports')) {
      reportsOpen.value = true
    }
  },
  { immediate: true }
)

const toggleReports = () => {
  reportsOpen.value = !reportsOpen.value
}

const logout = () => {
  // Add logout logic here (e.g. clearing tokens)
  router.push('/')
}
</script>

<style scoped>
.dashboard-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  width: 100%;
}

.dashboard-navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background: linear-gradient(135deg, #0f172a 0%, #14532d 100%);
  border-color: rgba(255, 255, 255, 0.12);
  border-radius: 0;
  border-left: none;
  border-right: none;
  border-top: none;
  position: sticky;
  top: 0;
  z-index: 10;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.22);
}

.navbar-brand h2 {
  margin: 0;
  font-size: 1.35rem;
  color: #ffffff;
  line-height: 1.1;
}

.navbar-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.navbar-logo {
  width: 46px;
  height: 46px;
  object-fit: contain;
  flex: 0 0 auto;
  filter: drop-shadow(0 3px 8px rgba(0, 0, 0, 0.25));
}

.navbar-actions .btn-outline {
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.7);
}

.navbar-actions .btn-outline:hover {
  background-color: rgba(255, 255, 255, 0.12);
  border-color: #ffffff;
}

.dashboard-body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.dashboard-sidebar {
  width: clamp(220px, 18vw, 280px);
  border-radius: 0;
  border-top: none;
  border-bottom: none;
  border-left: none;
  padding: clamp(1rem, 2vw, 2rem) 1rem;
  display: flex;
  flex-direction: column;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  text-decoration: none;
  color: var(--text-main);
  border-radius: 8px;
  transition: var(--transition);
  font-weight: 500;
}

.nav-link-toggle {
  width: 100%;
  background: transparent;
  border: none;
  text-align: left;
}

.nav-link:hover {
  background-color: rgba(37, 99, 235, 0.05);
  color: var(--primary-color);
}

.nav-link.active {
  background-color: var(--primary-color);
  color: white;
}

.nav-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 0.75rem;
  color: white;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12);
}

.icon-dashboard {
  background: linear-gradient(135deg, #2f80ed, #56ccf2);
}

.icon-members {
  background: linear-gradient(135deg, #16a085, #2ecc71);
}

.icon-reports {
  background: linear-gradient(135deg, #8e44ad, #c0392b);
}

.nav-link.active .nav-icon,
.nav-link-toggle.active .nav-icon {
  background: rgba(255, 255, 255, 0.22);
  box-shadow: none;
}

.caret {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.sub-nav {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-top: 0.25rem;
  margin-left: 0.5rem;
  margin-bottom: 0.5rem;
}

.sub-nav-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  color: var(--text-muted);
  padding: 0.5rem 0.75rem 0.5rem 2.55rem;
  border-radius: 6px;
  font-size: 0.92rem;
  transition: var(--transition);
}

.sub-nav-link:hover {
  color: var(--primary-color);
  background-color: rgba(37, 99, 235, 0.05);
}

.sub-nav-link.active-sub {
  color: var(--primary-color);
  background-color: rgba(37, 99, 235, 0.1);
  font-weight: 600;
}

.dashboard-main {
  flex: 1;
  padding: clamp(1rem, 2.2vw, 2rem);
  overflow-y: auto;
}

.main-content-wrapper {
  width: min(100%, 1280px);
  margin: 0 auto;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .dashboard-navbar {
    padding: 0.85rem 1rem;
    gap: 1rem;
  }

  .navbar-brand h2 {
    font-size: 1.05rem;
  }

  .navbar-logo {
    width: 40px;
    height: 40px;
  }

  .dashboard-body {
    flex-direction: column;
  }

  .dashboard-sidebar {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--surface-border);
    padding: 1rem;
  }

  .dashboard-main {
    padding: 1rem;
  }
}

@media (min-width: 1600px) {
  .main-content-wrapper {
    width: min(100%, 1440px);
  }
}
</style>

