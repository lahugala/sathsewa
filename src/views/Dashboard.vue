<template>
  <DashboardLayout>
    <div class="dashboard-content animate-fade-in">
      <div class="header-bar mb-4">
        <h2>Dashboard</h2>
      </div>

      <!-- Stat Cards -->
      <div class="stat-cards-grid mb-4">
        <div class="stat-card glass-panel">
          <div class="stat-icon">👥</div>
          <div class="stat-details">
            <h3>Total Members</h3>
            <p class="stat-number">{{ stats.totalMembers }}</p>
          </div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-icon">👨‍👩‍👧‍👦</div>
          <div class="stat-details">
            <h3>Total Dependents</h3>
            <p class="stat-number">{{ stats.totalDependents }}</p>
          </div>
        </div>
      </div>

      <!-- Recent Members Table -->
      <div class="glass-panel" style="overflow: hidden;">
        <div class="header-bar" style="border-radius: 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
          <h3>Recently Added Members</h3>
        </div>
        <div class="table-responsive" style="padding: 1rem;">
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
          <p v-else class="text-muted text-center py-4">No members found yet.</p>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import DashboardLayout from '../components/DashboardLayout.vue'

const stats = ref({ totalMembers: 0, totalDependents: 0 })
const recentMembers = ref([])

onMounted(async () => {
  try {
    const res = await fetch('/api/get_dashboard_stats.php')
    const data = await res.json()
    if (data.success) {
      stats.value = data.stats
      recentMembers.value = data.recentMembers
    }
  } catch (error) {
    console.error('Error fetching dashboard stats:', error)
  }
})
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.text-center { text-align: center; }

.stat-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

.stat-icon {
  font-size: 3rem;
  margin-right: 1.5rem;
  background: rgba(128, 0, 0, 0.1);
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
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--primary-color);
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th, .data-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.data-table th {
  font-weight: 600;
  color: var(--primary-color);
  background: rgba(128, 0, 0, 0.03);
}

.data-table tbody tr:hover {
  background: rgba(0,0,0,0.02);
}
</style>
