<template>
  <div class="dashboard-container">
    <!-- Navbar -->
    <header class="dashboard-navbar glass-panel">
      <div class="navbar-brand">
        <h2>Sathsewa Society</h2>
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
            <span class="nav-icon">📊</span>
            Dashboard
          </router-link>
          <router-link to="/members" class="nav-link" active-class="active">
            <span class="nav-icon">👥</span>
            Members
          </router-link>
          <!-- Additional links can go here -->
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
import { useRouter } from 'vue-router'

const router = useRouter()

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
  border-radius: 0;
  border-left: none;
  border-right: none;
  border-top: none;
  position: sticky;
  top: 0;
  z-index: 10;
}

.navbar-brand h2 {
  margin: 0;
  font-size: 1.5rem;
  color: var(--primary-color);
}

.dashboard-body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.dashboard-sidebar {
  width: 250px;
  border-radius: 0;
  border-top: none;
  border-bottom: none;
  border-left: none;
  padding: 2rem 1rem;
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

.nav-link:hover {
  background-color: rgba(128, 0, 0, 0.05);
  color: var(--primary-color);
}

.nav-link.active {
  background-color: var(--primary-color);
  color: white;
}

.nav-icon {
  margin-right: 0.75rem;
  font-size: 1.2rem;
}

.dashboard-main {
  flex: 1;
  padding: 2rem;
  overflow-y: auto;
}

.main-content-wrapper {
  max-width: 1000px;
  margin: 0 auto;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
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
</style>
