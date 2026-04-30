<template>
  <div class="dashboard-container">
    <!-- Navbar -->
    <header class="dashboard-navbar glass-panel">
      <div class="navbar-left">
        <button class="sidebar-toggle" type="button" :aria-label="sidebarToggleLabel" @click="toggleSidebar">
          <PanelLeftOpen v-if="sidebarCollapsed && !mobileSidebarOpen" :size="21" />
          <PanelLeftClose v-else :size="21" />
        </button>
        <div class="navbar-brand">
          <img src="/logo.png" alt="Sathsewa Welfare Society logo" class="navbar-logo">
          <h2>Sathsewa Welfare Society</h2>
        </div>
      </div>
      <div class="navbar-actions">
        <button @click="logout" class="btn btn-outline btn-sm">Logout</button>
      </div>
    </header>

    <div class="dashboard-body">
      <div v-if="mobileSidebarOpen" class="sidebar-backdrop" @click="closeMobileSidebar"></div>

      <!-- Sidebar -->
      <aside class="dashboard-sidebar glass-panel" :class="{ collapsed: sidebarCollapsed, 'mobile-open': mobileSidebarOpen }">
        <nav class="sidebar-nav">
          <router-link to="/dashboard" class="nav-link" active-class="active" title="Dashboard" @click="closeMobileSidebar">
            <span class="nav-icon icon-dashboard">
              <LayoutDashboard :size="17" />
            </span>
            <span class="nav-label">Dashboard</span>
          </router-link>
          <router-link to="/members" class="nav-link" active-class="active" title="Members" @click="closeMobileSidebar">
            <span class="nav-icon icon-members">
              <Users :size="17" />
            </span>
            <span class="nav-label">Members</span>
          </router-link>
          <router-link to="/charges" class="nav-link" active-class="active" title="Special Charges" @click="closeMobileSidebar">
            <span class="nav-icon icon-charges">
              <ReceiptText :size="17" />
            </span>
            <span class="nav-label">Special Charges</span>
          </router-link>

          <button
            class="nav-link nav-link-toggle"
            :class="{ active: isReportsRoute }"
            type="button"
            title="Reports"
            @click="toggleReports"
          >
            <span class="nav-icon icon-reports">
              <FileBarChart2 :size="17" />
            </span>
            <span class="nav-label">Reports</span>
            <span class="caret">
              <ChevronDown v-if="reportsOpen" :size="16" />
              <ChevronRight v-else :size="16" />
            </span>
          </button>

          <div v-show="reportsOpen" class="sub-nav">
            <router-link to="/reports/members" class="sub-nav-link" active-class="active-sub" @click="closeMobileSidebar">
              <UserSquare2 :size="14" />
              <span class="nav-label">Member Report</span>
            </router-link>
            <router-link to="/reports/financial" class="sub-nav-link" active-class="active-sub" @click="closeMobileSidebar">
              <Wallet2 :size="14" />
              <span class="nav-label">Financial Report</span>
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
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard,
  Users,
  FileBarChart2,
  ReceiptText,
  ChevronDown,
  ChevronRight,
  UserSquare2,
  Wallet2,
  PanelLeftClose,
  PanelLeftOpen
} from 'lucide-vue-next'
import { apiFetch } from '../utils/api'

const router = useRouter()
const route = useRoute()
const reportsOpen = ref(false)
const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)

const isReportsRoute = computed(() => route.path.startsWith('/reports'))
const sidebarToggleLabel = computed(() => {
  if (mobileSidebarOpen.value) return 'Close side menu'
  return sidebarCollapsed.value ? 'Expand side menu' : 'Collapse side menu'
})

const isMobileViewport = () => typeof window !== 'undefined' && window.matchMedia('(max-width: 768px)').matches

watch(
  () => route.path,
  (path) => {
    if (path.startsWith('/reports')) {
      reportsOpen.value = true
    }
    mobileSidebarOpen.value = false
  },
  { immediate: true }
)

const toggleReports = () => {
  if (sidebarCollapsed.value && !isMobileViewport()) {
    sidebarCollapsed.value = false
  }
  reportsOpen.value = !reportsOpen.value
}

const toggleSidebar = () => {
  if (isMobileViewport()) {
    mobileSidebarOpen.value = !mobileSidebarOpen.value
    return
  }

  sidebarCollapsed.value = !sidebarCollapsed.value
  localStorage.setItem('sathsewa-sidebar-collapsed', sidebarCollapsed.value ? '1' : '0')
}

const closeMobileSidebar = () => {
  mobileSidebarOpen.value = false
}

onMounted(() => {
  sidebarCollapsed.value = localStorage.getItem('sathsewa-sidebar-collapsed') === '1'
})

const logout = async () => {
  try {
    await apiFetch('/api/logout.php', { method: 'POST' })
  } finally {
    router.push('/')
  }
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
  background:
    linear-gradient(135deg, rgba(5, 46, 39, 0.98) 0%, rgba(6, 78, 59, 0.98) 48%, rgba(15, 118, 110, 0.96) 100%);
  border-color: rgba(255, 255, 255, 0.12);
  border-radius: 0;
  border-left: none;
  border-right: none;
  border-top: none;
  position: sticky;
  top: 0;
  z-index: 10;
  box-shadow: 0 12px 34px rgba(15, 23, 42, 0.2);
}

.navbar-left {
  display: inline-flex;
  align-items: center;
  gap: 0.9rem;
  min-width: 0;
}

.sidebar-toggle {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
  transition: var(--transition);
}

.sidebar-toggle:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.45);
}

.sidebar-toggle:focus-visible {
  outline: none;
  box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.16);
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
  position: relative;
}

.dashboard-sidebar {
  width: clamp(220px, 18vw, 280px);
  flex: 0 0 clamp(220px, 18vw, 280px);
  border-radius: 0;
  border-top: none;
  border-bottom: none;
  border-left: none;
  padding: clamp(1rem, 2vw, 2rem) 1rem;
  display: flex;
  flex-direction: column;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.94) 0%, rgba(243, 247, 246, 0.9) 100%);
  transition: width 0.22s ease, flex-basis 0.22s ease, padding 0.22s ease, transform 0.22s ease;
  overflow-x: hidden;
}

.dashboard-sidebar.collapsed {
  width: 76px;
  flex-basis: 76px;
  padding-inline: 0.75rem;
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
  font-weight: 650;
  border: 1px solid transparent;
  min-width: 0;
}

.nav-link-toggle {
  width: 100%;
  background: transparent;
  border: none;
  text-align: left;
}

.nav-link:hover {
  background-color: rgba(20, 184, 166, 0.09);
  color: var(--primary-color);
  border-color: rgba(20, 184, 166, 0.16);
}

.nav-link.active {
  background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
  color: white;
  box-shadow: 0 10px 24px rgba(15, 118, 110, 0.22);
}

.nav-icon {
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 0.75rem;
  color: white;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12);
}

.nav-label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-sidebar.collapsed .nav-link {
  justify-content: center;
  padding-inline: 0.55rem;
}

.dashboard-sidebar.collapsed .nav-icon {
  margin-right: 0;
}

.dashboard-sidebar.collapsed .nav-label,
.dashboard-sidebar.collapsed .caret,
.dashboard-sidebar.collapsed .sub-nav {
  display: none;
}

.icon-dashboard {
  background: linear-gradient(135deg, #0f766e, #14b8a6);
}

.icon-members {
  background: linear-gradient(135deg, #15803d, #84cc16);
}

.icon-reports {
  background: linear-gradient(135deg, #d97706, #ef4444);
}

.icon-charges {
  background: linear-gradient(135deg, #0e7490, #2dd4bf);
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
  background-color: rgba(20, 184, 166, 0.08);
}

.sub-nav-link.active-sub {
  color: var(--primary-color);
  background-color: rgba(20, 184, 166, 0.13);
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

  .navbar-left {
    gap: 0.65rem;
  }

  .navbar-logo {
    width: 40px;
    height: 40px;
  }

  .dashboard-body {
    display: block;
    overflow: visible;
  }

  .dashboard-sidebar {
    position: fixed;
    top: 73px;
    left: 0;
    bottom: 0;
    z-index: 30;
    width: min(82vw, 310px);
    flex-basis: auto;
    border-right: none;
    border-bottom: none;
    padding: 1rem;
    transform: translateX(-110%);
    box-shadow: var(--shadow-lg);
  }

  .dashboard-sidebar.mobile-open {
    transform: translateX(0);
  }

  .dashboard-sidebar.collapsed {
    width: min(82vw, 310px);
    flex-basis: auto;
    padding: 1rem;
  }

  .dashboard-sidebar.collapsed .nav-link {
    justify-content: flex-start;
    padding: 0.75rem 1rem;
  }

  .dashboard-sidebar.collapsed .nav-icon {
    margin-right: 0.75rem;
  }

  .dashboard-sidebar.collapsed .nav-label,
  .dashboard-sidebar.collapsed .caret {
    display: inline-flex;
  }

  .dashboard-sidebar.collapsed .sub-nav {
    display: flex;
  }

  .sidebar-backdrop {
    position: fixed;
    top: 73px;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 25;
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(2px);
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

