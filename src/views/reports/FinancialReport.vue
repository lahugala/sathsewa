<template>
  <DashboardLayout>
    <div class="report-page animate-fade-in">
      <div class="header-bar mb-4">
        <h2>Financial Report</h2>
      </div>

      <div class="glass-panel report-card">
        <h3>Summary</h3>
        <p>Use this section to track society income, payouts, and balances.</p>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '../../components/DashboardLayout.vue'
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.report-card { padding: 1.5rem; }
.report-card h3 {
  margin-bottom: 0.5rem;
  color: var(--primary-color);
}
.report-card p {
  color: var(--text-muted);
}
</style>
