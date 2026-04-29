<template>
  <DashboardLayout>
    <div class="dashboard-content animate-fade-in">
      <div class="header-bar mb-4 dashboard-header">
        <div>
          <h2>Dashboard</h2>
          <p class="header-subtitle">Society health, cash movement, member status, and recent activity.</p>
        </div>
        <button class="btn btn-outline btn-refresh" type="button" :disabled="isLoading" @click="loadDashboard">
          {{ isLoading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>

      <div class="stat-cards-grid mb-4">
        <div v-for="card in statCards" :key="card.label" class="stat-card glass-panel" :class="{ 'is-loading': isLoading }">
          <div class="stat-icon" :class="card.iconClass">
            <component :is="card.icon" :size="30" />
          </div>
          <div class="stat-details">
            <h3>{{ card.label }}</h3>
            <p class="stat-number" :class="{ 'currency-value': card.isCurrency }">{{ isLoading ? '--' : card.value }}</p>
            <div v-if="card.trend" class="trend-chip" :class="trendClass(card.trend)">
              <TrendingUp v-if="card.trend?.direction === 'up'" :size="14" />
              <TrendingDown v-else-if="card.trend?.direction === 'down'" :size="14" />
              <Minus v-else :size="14" />
              <span>{{ trendText(card.trend) }}</span>
            </div>
            <p v-else class="stat-caption">{{ card.caption }}</p>
          </div>
        </div>
      </div>

      <div class="wide-cards-grid mb-4">
        <div v-for="card in wideStatCards" :key="card.label" class="wide-stat-card glass-panel" :class="{ 'is-loading': isLoading }">
          <div class="wide-stat-icon" :class="card.iconClass">
            <component :is="card.icon" :size="34" />
          </div>
          <div class="wide-stat-details">
            <div>
              <h3>{{ card.label }}</h3>
              <p>{{ card.caption }}</p>
            </div>
            <strong>{{ isLoading ? '--' : card.value }}</strong>
          </div>
        </div>
      </div>

      <div class="insight-grid mb-4">
        <section class="glass-panel insight-panel">
          <div class="panel-heading">
            <div>
              <h3>Member Status</h3>
              <p>Current member standing across the society.</p>
            </div>
            <BadgeCheck :size="22" />
          </div>
          <div class="status-list">
            <div v-for="item in statusBreakdown" :key="item.label" class="status-row">
              <div class="status-row-top">
                <span>{{ item.label }}</span>
                <strong>{{ item.count }}</strong>
              </div>
              <div class="progress-track">
                <span class="progress-fill" :class="item.className" :style="{ width: item.percent + '%' }"></span>
              </div>
              <small>{{ item.percent }}% of members</small>
            </div>
          </div>
        </section>

        <section class="glass-panel insight-panel">
          <div class="panel-heading">
            <div>
              <h3>Financial Snapshot</h3>
              <p>Income, benefits, and working balance.</p>
            </div>
            <WalletCards :size="22" />
          </div>
          <div class="metric-list">
            <div class="metric-row">
              <span>Total income</span>
              <strong>{{ formatCurrency(stats.finance.totalIncome) }}</strong>
            </div>
            <div class="metric-row">
              <span>Benefits paid</span>
              <strong>{{ formatCurrency(stats.finance.totalBenefits) }}</strong>
            </div>
            <div class="metric-row">
              <span>Net balance</span>
              <strong>{{ formatCurrency(stats.finance.netBalance) }}</strong>
            </div>
            <div class="metric-row">
              <span>This month income</span>
              <strong>{{ formatCurrency(stats.finance.monthIncome) }}</strong>
            </div>
          </div>
        </section>

        <section class="glass-panel insight-panel">
          <div class="panel-heading">
            <div>
              <h3>Outstanding Summary</h3>
              <p>Calculated up to {{ stats.outstanding.asOfDate || 'latest closed month' }}.</p>
            </div>
            <AlertTriangle :size="22" />
          </div>
          <div class="outstanding-summary">
            <strong>{{ formatCurrency(stats.outstanding.totalAmount) }}</strong>
            <span>{{ stats.outstanding.membersCount }} member(s), {{ stats.outstanding.totalMonths }} month(s)</span>
          </div>
          <div class="charge-chip" v-if="stats.currentSpecialCharge">
            <ReceiptText :size="16" />
            <span>{{ currentMonthName }} charge: {{ formatCurrency(stats.currentSpecialCharge.amount) }}</span>
          </div>
          <p v-else class="empty-note">No special charge set for {{ currentMonthName }}.</p>
        </section>
      </div>

      <div class="dashboard-grid mb-4">
        <section class="glass-panel table-panel">
          <div class="table-panel-header">
            <h3>Recently Added Members</h3>
          </div>
          <div class="table-responsive">
            <p v-if="isLoading" class="text-muted text-center py-4">Loading dashboard data...</p>
            <table class="data-table responsive-card-table" v-else-if="recentMembers.length > 0">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>NIC</th>
                  <th>City</th>
                  <th>Status</th>
                  <th>Date Added</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="member in recentMembers" :key="member.id">
                  <td data-label="Member">
                    <strong>{{ member.name }}</strong>
                    <span>{{ member.membership_number || 'No membership no' }}</span>
                  </td>
                  <td data-label="NIC">{{ member.nic }}</td>
                  <td data-label="City">{{ member.city }}</td>
                  <td data-label="Status"><span class="status-badge" :class="statusClass(member.status)">{{ member.status || 'Active' }}</span></td>
                  <td data-label="Date Added">{{ member.date_added }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-muted text-center py-4">No members found yet.</p>
          </div>
        </section>

        <section class="glass-panel table-panel compact-panel">
          <div class="table-panel-header">
            <h3>Top Outstanding</h3>
          </div>
          <div class="mini-list" v-if="topOutstandingMembers.length > 0">
            <div v-for="member in topOutstandingMembers" :key="member.id" class="mini-row">
              <div>
                <strong>{{ member.name }}</strong>
                <span>{{ member.membership_number || '-' }} | {{ member.outstanding_months }} month(s)</span>
              </div>
              <b>{{ formatCurrency(member.outstanding_amount) }}</b>
            </div>
          </div>
          <p v-else class="empty-note">No outstanding payments found.</p>
        </section>
      </div>

      <div class="activity-grid">
        <section class="glass-panel table-panel">
          <div class="table-panel-header">
            <h3>Recent Payments</h3>
          </div>
          <div class="mini-list" v-if="recentPayments.length > 0">
            <div v-for="payment in recentPayments" :key="payment.id" class="mini-row">
              <div>
                <strong>{{ payment.name }}</strong>
                <span>{{ formatMonth(payment.payment_month) }} {{ payment.payment_year }} | {{ formatDate(payment.paid_date) }}</span>
              </div>
              <b>{{ formatCurrency(payment.total_amount) }}</b>
            </div>
          </div>
          <p v-else class="empty-note">No payment records yet.</p>
        </section>

        <section class="glass-panel table-panel">
          <div class="table-panel-header">
            <h3>Recent Benefits</h3>
          </div>
          <div class="mini-list" v-if="recentBenefits.length > 0">
            <div v-for="benefit in recentBenefits" :key="benefit.id" class="mini-row">
              <div>
                <strong>{{ benefit.name }}</strong>
                <span>{{ benefitLabel(benefit) }} | {{ formatDate(benefit.paid_date) }}</span>
              </div>
              <b>{{ formatCurrency(benefit.amount) }}</b>
            </div>
          </div>
          <p v-else class="empty-note">No benefit records yet.</p>
        </section>

        <section class="glass-panel table-panel">
          <div class="table-panel-header">
            <h3>Top Cities</h3>
          </div>
          <div class="mini-list" v-if="topCities.length > 0">
            <div v-for="city in topCities" :key="city.city" class="mini-row city-row">
              <div>
                <strong>{{ city.city }}</strong>
                <span>{{ city.members }} member(s)</span>
              </div>
              <MapPinned :size="18" />
            </div>
          </div>
          <p v-else class="empty-note">No city data available.</p>
        </section>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  AlertTriangle,
  BadgeCheck,
  Banknote,
  BarChart3,
  MapPinned,
  Minus,
  ReceiptText,
  TrendingDown,
  TrendingUp,
  UserCheck,
  UserPlus,
  Users,
  WalletCards
} from 'lucide-vue-next'
import DashboardLayout from '../components/DashboardLayout.vue'
import { alertError } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const defaultStats = () => ({
  totalMembers: 0,
  totalDependents: 0,
  activeMembers: 0,
  inactiveMembers: 0,
  suspendedMembers: 0,
  statusCounts: { Active: 0, Inactive: 0, Suspended: 0 },
  memberTrend: { direction: 'flat', change: 0, percent: 0 },
  dependentTrend: { direction: 'flat', change: 0, percent: 0 },
  finance: {
    totalIncome: 0,
    monthIncome: 0,
    totalMemberFee: 0,
    totalShareCapital: 0,
    totalSpecialCharges: 0,
    paymentRecords: 0,
    lastPaymentDate: null,
    totalBenefits: 0,
    monthBenefits: 0,
    benefitsCount: 0,
    lastBenefitDate: null,
    netBalance: 0
  },
  outstanding: {
    membersCount: 0,
    totalMonths: 0,
    totalAmount: 0,
    asOfDate: null
  },
  currentSpecialCharge: null
})

const stats = ref(defaultStats())
const recentMembers = ref([])
const recentPayments = ref([])
const recentBenefits = ref([])
const topOutstandingMembers = ref([])
const topCities = ref([])
const isLoading = ref(false)

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

const currentMonthName = computed(() => monthNames[new Date().getMonth()])

const avgDependentsPerMember = computed(() => {
  const members = Number(stats.value.totalMembers || 0)
  const dependents = Number(stats.value.totalDependents || 0)
  if (!members) return '0.00'
  return (dependents / members).toFixed(2)
})

const statCards = computed(() => [
  {
    label: 'Total Members',
    value: stats.value.totalMembers,
    icon: Users,
    iconClass: 'icon-members',
    trend: stats.value.memberTrend
  },
  {
    label: 'Active Members',
    value: stats.value.activeMembers,
    icon: UserCheck,
    iconClass: 'icon-active',
    caption: `${stats.value.suspendedMembers} suspended, ${stats.value.inactiveMembers} inactive`
  },
  {
    label: 'Dependents',
    value: stats.value.totalDependents,
    icon: UserPlus,
    iconClass: 'icon-dependents',
    trend: stats.value.dependentTrend
  },
  {
    label: 'Avg. Dependents',
    value: avgDependentsPerMember.value,
    icon: BarChart3,
    iconClass: 'icon-average',
    caption: 'Per registered member'
  }
])

const wideStatCards = computed(() => [
  {
    label: 'Total Income',
    value: formatCurrency(stats.value.finance.totalIncome),
    icon: Banknote,
    iconClass: 'icon-income',
    caption: `${stats.value.finance.paymentRecords} payment record(s), ${formatCurrency(stats.value.finance.monthIncome)} this month`
  },
  {
    label: 'Outstanding',
    value: formatCurrency(stats.value.outstanding.totalAmount),
    icon: AlertTriangle,
    iconClass: 'icon-warning',
    caption: `${stats.value.outstanding.membersCount} member(s), ${stats.value.outstanding.totalMonths} month(s) pending`
  }
])

const statusBreakdown = computed(() => {
  const total = Math.max(Number(stats.value.totalMembers || 0), 1)
  return [
    { label: 'Active', count: Number(stats.value.statusCounts.Active || 0), className: 'fill-active' },
    { label: 'Suspended', count: Number(stats.value.statusCounts.Suspended || 0), className: 'fill-suspended' },
    { label: 'Inactive', count: Number(stats.value.statusCounts.Inactive || 0), className: 'fill-inactive' }
  ].map((item) => ({
    ...item,
    percent: Math.round((item.count / total) * 100)
  }))
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-LK', {
    style: 'currency',
    currency: 'LKR',
    minimumFractionDigits: 2
  }).format(Number(value || 0))
}

const formatMonth = (monthNo) => {
  const n = Number(monthNo)
  if (!Number.isFinite(n) || n < 1 || n > 12) return '-'
  return monthNames[n - 1]
}

const formatDate = (value) => value || '-'

const benefitLabel = (benefit) => {
  if (benefit.benefit_type === 'death_gratuity') {
    return benefit.dependent_name ? `Death gratuity: ${benefit.dependent_name}` : 'Death gratuity'
  }
  return benefit.aid_nature || 'Special donation'
}

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

const statusClass = (status) => {
  return {
    'status-active': status === 'Active' || !status,
    'status-inactive': status === 'Inactive',
    'status-suspended': status === 'Suspended'
  }
}

const loadDashboard = async () => {
  isLoading.value = true
  try {
    const res = await apiFetch('/api/get_dashboard_stats.php')
    const data = await res.json()
    if (data.success) {
      stats.value = { ...defaultStats(), ...data.stats }
      stats.value.finance = { ...defaultStats().finance, ...(data.stats?.finance || {}) }
      stats.value.outstanding = { ...defaultStats().outstanding, ...(data.stats?.outstanding || {}) }
      recentMembers.value = data.recentMembers || []
      recentPayments.value = data.recentPayments || []
      recentBenefits.value = data.recentBenefits || []
      topOutstandingMembers.value = data.topOutstandingMembers || []
      topCities.value = data.topCities || []
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

.dashboard-header,
.panel-heading,
.table-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.header-subtitle,
.panel-heading p {
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
  gap: 1rem;
}

.wide-cards-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(320px, 1fr));
  gap: 1rem;
}

.stat-card {
  display: flex;
  align-items: center;
  min-height: 142px;
  padding: 1.25rem;
  min-width: 0;
  transition: transform 0.22s ease, box-shadow 0.22s ease;
}

.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
}

.stat-card.is-loading {
  opacity: 0.75;
}

.wide-stat-card {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  min-width: 0;
  min-height: 150px;
  padding: 1.5rem;
  overflow: hidden;
}

.wide-stat-card.is-loading {
  opacity: 0.75;
}

.stat-icon {
  margin-right: 1rem;
  color: #fff;
  width: 64px;
  height: 64px;
  flex: 0 0 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 18px;
  box-shadow: 0 12px 26px rgba(15, 23, 42, 0.16);
}

.icon-members { background: linear-gradient(135deg, #0f766e, #14b8a6); }
.icon-active { background: linear-gradient(135deg, #15803d, #84cc16); }
.icon-dependents { background: linear-gradient(135deg, #0e7490, #2dd4bf); }
.icon-average { background: linear-gradient(135deg, #7c3aed, #14b8a6); }
.icon-income { background: linear-gradient(135deg, #047857, #d97706); }
.icon-warning { background: linear-gradient(135deg, #d97706, #ef4444); }

.wide-stat-icon {
  width: 76px;
  height: 76px;
  flex: 0 0 76px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 22px;
  color: #fff;
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
}

.wide-stat-details {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1.5rem;
  min-width: 0;
  flex: 1;
}

.wide-stat-details h3 {
  margin: 0;
  color: var(--text-muted);
  font-size: 1rem;
  font-weight: 800;
}

.wide-stat-details p {
  margin: 0.45rem 0 0;
  color: var(--text-muted);
  font-size: 0.88rem;
}

.wide-stat-details strong {
  color: var(--primary-dark);
  font-size: clamp(1.35rem, 2.25vw, 2.1rem);
  line-height: 1;
  text-align: right;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.stat-details {
  min-width: 0;
  flex: 1;
}

.stat-details h3 {
  margin: 0 0 0.45rem 0;
  font-size: 0.95rem;
  color: var(--text-muted);
  font-weight: 700;
}

.stat-number {
  margin: 0;
  font-size: clamp(1.55rem, 2.2vw, 2.25rem);
  font-weight: 800;
  color: var(--primary-dark);
  line-height: 1.05;
  max-width: 100%;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.stat-number.currency-value {
  font-size: clamp(1.15rem, 1.55vw, 1.72rem);
  line-height: 1.12;
}

.stat-caption {
  margin: 0.5rem 0 0;
  color: var(--text-muted);
  font-size: 0.82rem;
}

.trend-chip {
  margin-top: 0.45rem;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.78rem;
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

.insight-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.insight-panel,
.table-panel {
  padding: 1.25rem;
}

.panel-heading {
  color: var(--primary-dark);
  margin-bottom: 1rem;
}

.panel-heading h3,
.table-panel-header h3 {
  margin: 0;
  color: var(--primary-dark);
  font-size: 1.05rem;
}

.status-list,
.metric-list,
.mini-list {
  display: grid;
  gap: 0.75rem;
}

.status-row-top,
.metric-row,
.mini-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.status-row-top span,
.metric-row span,
.mini-row span {
  color: var(--text-muted);
  font-size: 0.82rem;
}

.status-row small {
  color: var(--text-muted);
  font-size: 0.75rem;
}

.progress-track {
  height: 8px;
  margin: 0.35rem 0 0.2rem;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.progress-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
}

.fill-active { background: #16a34a; }
.fill-suspended { background: #dc2626; }
.fill-inactive { background: #64748b; }

.metric-row,
.mini-row {
  padding: 0.75rem;
  border: 1px solid rgba(20, 184, 166, 0.14);
  border-radius: 8px;
  background: rgba(20, 184, 166, 0.04);
}

.metric-row strong,
.mini-row b {
  color: var(--primary-dark);
  white-space: nowrap;
}

.outstanding-summary {
  display: grid;
  gap: 0.25rem;
  padding: 1rem;
  border-radius: 8px;
  background: rgba(220, 53, 69, 0.08);
  border: 1px solid rgba(220, 53, 69, 0.18);
}

.outstanding-summary strong {
  color: #b42332;
  font-size: 1.45rem;
}

.outstanding-summary span,
.empty-note {
  color: var(--text-muted);
  font-size: 0.86rem;
}

.charge-chip {
  margin-top: 0.8rem;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  width: fit-content;
  padding: 0.45rem 0.65rem;
  border-radius: 999px;
  color: #92400e;
  background: rgba(217, 119, 6, 0.12);
  border: 1px solid rgba(217, 119, 6, 0.22);
  font-size: 0.82rem;
  font-weight: 700;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.8fr);
  gap: 1rem;
}

.activity-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.table-panel {
  overflow: hidden;
}

.table-panel-header {
  margin-bottom: 1rem;
}

.table-responsive {
  overflow-x: auto;
  overflow-y: auto;
  border: 1px solid rgba(15, 118, 110, 0.12);
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
  padding: 0.85rem;
  text-align: left;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  vertical-align: top;
}

.data-table td strong,
.mini-row strong {
  display: block;
  color: var(--text-main);
  font-size: 0.9rem;
}

.data-table td span,
.mini-row span {
  display: block;
  margin-top: 0.12rem;
}

.data-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  font-weight: 700;
  color: var(--primary-color);
  background: #eefaf7;
}

.data-table tbody tr:nth-child(even) {
  background: rgba(20, 184, 166, 0.025);
}

.data-table tbody tr:hover {
  background: rgba(20, 184, 166, 0.09);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 76px;
  padding: 0.25rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.status-active {
  background: rgba(25, 135, 84, 0.12);
  color: #126742;
}

.status-inactive {
  background: rgba(108, 117, 125, 0.14);
  color: #495057;
}

.status-suspended {
  background: rgba(220, 53, 69, 0.12);
  color: #b42332;
}

.city-row svg {
  color: var(--primary-color);
  flex: 0 0 auto;
}

@media (max-width: 1100px) {
  .insight-grid,
  .activity-grid,
  .wide-cards-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .dashboard-header {
    flex-direction: column;
    align-items: stretch;
  }

  .btn-refresh {
    width: 100%;
  }

  .stat-card {
    align-items: flex-start;
  }

  .stat-icon {
    width: 54px;
    height: 54px;
    flex-basis: 54px;
  }

  .wide-stat-card,
  .wide-stat-details {
    align-items: flex-start;
  }

  .wide-stat-card,
  .wide-stat-details {
    flex-direction: column;
  }

  .wide-stat-details strong {
    text-align: left;
  }
}
</style>
