<template>
  <DashboardLayout>
    <div class="dashboard-content animate-fade-in">
      <div class="header-bar mb-4 dashboard-header">
        <div>
          <h2>Dashboard</h2>
          <p class="header-subtitle">Quick overview of members, dependents, and latest registrations.</p>
        </div>
        <button class="btn btn-outline btn-refresh" type="button" :disabled="isLoading" @click="loadDashboard">
          {{ isLoading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>

      <!-- Stat Cards -->
      <div class="stat-cards-grid mb-4">
        <div class="stat-card glass-panel" :class="{ 'is-loading': isLoading }">
          <div class="stat-icon">
            <Users :size="34" />
          </div>
          <div class="stat-details">
            <h3>Total Members</h3>
            <p class="stat-number">{{ isLoading ? '--' : stats.totalMembers }}</p>
            <div class="trend-chip" :class="trendClass(stats.memberTrend)">
              <TrendingUp v-if="stats.memberTrend?.direction === 'up'" :size="14" />
              <TrendingDown v-else-if="stats.memberTrend?.direction === 'down'" :size="14" />
              <Minus v-else :size="14" />
              <span>{{ trendText(stats.memberTrend) }}</span>
            </div>
          </div>
        </div>

        <div class="stat-card glass-panel" :class="{ 'is-loading': isLoading }">
          <div class="stat-icon">
            <UserPlus :size="34" />
          </div>
          <div class="stat-details">
            <h3>Total Dependents</h3>
            <p class="stat-number">{{ isLoading ? '--' : stats.totalDependents }}</p>
            <div class="trend-chip" :class="trendClass(stats.dependentTrend)">
              <TrendingUp v-if="stats.dependentTrend?.direction === 'up'" :size="14" />
              <TrendingDown v-else-if="stats.dependentTrend?.direction === 'down'" :size="14" />
              <Minus v-else :size="14" />
              <span>{{ trendText(stats.dependentTrend) }}</span>
            </div>
          </div>
        </div>

        <div class="stat-card glass-panel" :class="{ 'is-loading': isLoading }">
          <div class="stat-icon">
            <BarChart3 :size="34" />
          </div>
          <div class="stat-details">
            <h3>Avg. Dependents / Member</h3>
            <p class="stat-number">{{ isLoading ? '--' : avgDependentsPerMember }}</p>
          </div>
        </div>
      </div>

      <!-- Recent Members Table -->
      <div class="glass-panel" style="overflow: hidden;">
        <div class="header-bar" style="border-radius: 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
          <h4>Recently Added Members</h4>
        </div>

        <div class="table-responsive" style="padding: 1rem;">
          <p v-if="isLoading" class="text-muted text-center py-4">Loading dashboard data...</p>

          <table class="data-table" v-if="recentMembers.length > 0">
            <thead>
              <tr>
                <th>Name</th>
                <th>NIC</th>
                <th>City</th>
                <th>Date Added</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in recentMembers" :key="member.id">
                <td>{{ member.name }}</td>
                <td>{{ member.nic }}</td>
                <td>{{ member.city }}</td>
                <td>{{ member.date_added }}</td>
              </tr>
            </tbody>
          </table>

          <p v-else-if="!isLoading" class="text-muted text-center py-4">No members found yet.</p>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Users, UserPlus, BarChart3, TrendingUp, TrendingDown, Minus } from 'lucide-vue-next'
import DashboardLayout from '../components/DashboardLayout.vue'
import { alertError } from '../utils/alerts'

const stats = ref({
  totalMembers: 0,
  totalDependents: 0,
  memberTrend: { direction: 'flat', change: 0, percent: 0 },
  dependentTrend: { direction: 'flat', change: 0, percent: 0 }
})
const recentMembers = ref([])
const isLoading = ref(false)

const avgDependentsPerMember = computed(() => {
  const members = Number(stats.value.totalMembers || 0)
  const dependents = Number(stats.value.totalDependents || 0)
  if (!members) return '0.00'
  return (dependents / members).toFixed(2)
})

const trendText = (trend) => {
  if (!trend) return 'No trend data'
  if (trend.direction === 'flat') return 'No change vs last month'
  const sign = trend.change > 0 ? '+' : ''
  return `${sign}${trend.change} (${sign}${trend.percent}%) vs last month`
}

const trendClass = (trend) => {
  if (!trend?.direction) return 'trend-flat'
  if (trend.direction === 'up') return 'trend-up'
  if (trend.direction === 'down') return 'trend-down'
  return 'trend-flat'
}

const loadDashboard = async () => {
  isLoading.value = true
  try {
    const res = await fetch('/api/get_dashboard_stats.php')
    const data = await res.json()
    if (data.success) {
      stats.value = data.stats
      recentMembers.value = data.recentMembers
    } else {
      alertError('Dashboard error', data.message || 'Failed to load dashboard data.')
    }
  } catch (error) {
    console.error('Error fetching dashboard stats:', error)
    alertError('Network error', 'Network error while loading dashboard data.')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.text-center { text-align: center; }

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.header-subtitle {
  margin: 0;
  opacity: 0.9;
  font-size: 0.92rem;
}

.btn-refresh {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  border-color: rgba(255, 255, 255, 0.45);
}

.btn-refresh:hover {
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
  border-color: rgba(255, 255, 255, 0.55);
}

.stat-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.5rem;
}

.stat-card {
  display: flex;
  align-items: center;
  padding: 1.5rem;
  transition: transform 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
}

.stat-card.is-loading {
  opacity: 0.75;
}

.stat-icon {
  margin-right: 1.5rem;
  background: rgba(37, 99, 235, 0.1);
  color: var(--primary-color);
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.stat-details h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.1rem;
  color: #555;
  font-weight: 500;
}

.stat-number {
  margin: 0;
  font-size: clamp(1.9rem, 3vw, 2.5rem);
  font-weight: 700;
  color: var(--primary-color);
}

.trend-chip {
  margin-top: 0.45rem;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.8rem;
  padding: 0.25rem 0.5rem;
  border-radius: 999px;
  border: 1px solid transparent;
}

.trend-up {
  color: #0f8a4f;
  background: rgba(25, 135, 84, 0.12);
  border-color: rgba(25, 135, 84, 0.3);
}

.trend-down {
  color: #b42332;
  background: rgba(220, 53, 69, 0.12);
  border-color: rgba(220, 53, 69, 0.3);
}

.trend-flat {
  color: #475467;
  background: rgba(71, 84, 103, 0.12);
  border-color: rgba(71, 84, 103, 0.22);
}

.table-responsive {
  overflow-x: auto;
  overflow-y: auto;
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 10px;
  max-height: 420px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}

.data-table th,
.data-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.data-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  font-weight: 600;
  color: var(--primary-color);
  background: #eff6ff;
}

.data-table tbody tr:nth-child(even) {
  background: rgba(37, 99, 235, 0.015);
}

.data-table tbody tr:hover {
  background: rgba(37, 99, 235, 0.07);
}

.data-table th:first-child,
.data-table td:first-child {
  position: sticky;
  left: 0;
  z-index: 3;
  box-shadow: 1px 0 0 rgba(0, 0, 0, 0.08);
}

.data-table th:last-child,
.data-table td:last-child {
  position: sticky;
  right: 0;
  z-index: 3;
  box-shadow: -1px 0 0 rgba(0, 0, 0, 0.08);
}

.data-table thead th:first-child,
.data-table thead th:last-child {
  z-index: 4;
  background: #eff6ff;
}

.data-table tbody td:first-child,
.data-table tbody td:last-child {
  background: #fff;
}

.data-table tbody tr:nth-child(even) td:first-child,
.data-table tbody tr:nth-child(even) td:last-child {
  background: rgba(37, 99, 235, 0.015);
}

.data-table tbody tr:hover td:first-child,
.data-table tbody tr:hover td:last-child {
  background: rgba(37, 99, 235, 0.07);
}

@media (max-width: 768px) {
  .dashboard-header {
    flex-direction: column;
    align-items: stretch;
  }

  .btn-refresh {
    width: 100%;
  }

  .data-table th:first-child,
  .data-table td:first-child,
  .data-table th:last-child,
  .data-table td:last-child {
    position: static;
    box-shadow: none;
  }
}
</style>
