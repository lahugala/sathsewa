<template>
  <DashboardLayout>
    <div class="report-page animate-fade-in">
      <div class="header-bar mb-4 report-header">
        <div>
          <h2>Loan Financial Report</h2>
          <p class="report-subtitle">Issued loans, recoveries, balances, and overdue installments.</p>
        </div>
        <div class="report-actions">
          <select v-model.number="selectedYear" class="year-select" @change="loadReport">
            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
          </select>
          <button class="btn btn-outline" type="button" :disabled="loading" @click="loadReport">
            <RefreshCw :size="16" />
            {{ loading ? 'Refreshing...' : 'Refresh' }}
          </button>
          <button class="btn btn-outline" type="button" :disabled="loading" @click="exportCsv">
            <Download :size="16" />
            Export CSV
          </button>
          <button class="btn btn-primary" type="button" @click="printReport">
            <Printer :size="16" />
            Print
          </button>
        </div>
      </div>

      <div v-if="loading" class="glass-panel loading-panel">Loading loan financial report...</div>

      <template v-else>
        <section class="summary-grid mb-4">
          <div v-for="card in summaryCards" :key="card.label" class="summary-card glass-panel" :class="{ warning: card.warning }">
            <div class="summary-icon" :class="card.iconClass">
              <component :is="card.icon" :size="24" />
            </div>
            <div>
              <span>{{ card.label }}</span>
              <strong>{{ card.value }}</strong>
              <small>{{ card.caption }}</small>
            </div>
          </div>
        </section>

        <section class="report-grid mb-4">
          <div class="glass-panel report-card">
            <div class="section-heading">
              <h3>{{ selectedYear }} Monthly Recoveries</h3>
              <Banknote :size="20" />
            </div>
            <div class="monthly-list" v-if="monthlyRecoveryRows.length > 0">
              <div v-for="item in monthlyRecoveryRows" :key="item.month" class="monthly-row">
                <div>
                  <span>{{ formatMonth(item.month) }}</span>
                  <small>{{ item.recoveries_count }} installment(s)</small>
                </div>
                <div class="bar-track">
                  <span class="bar-fill" :style="{ width: recoveryPercent(item.recovered_total) + '%' }"></span>
                </div>
                <strong>{{ formatCurrency(item.recovered_total) }}</strong>
              </div>
            </div>
            <p v-else class="empty-state">No loan recoveries recorded for {{ selectedYear }}.</p>
          </div>

          <div class="glass-panel report-card">
            <div class="section-heading">
              <h3>Portfolio Summary</h3>
              <WalletCards :size="20" />
            </div>
            <div class="metric-list">
              <div class="metric-row"><span>Total loan records</span><strong>{{ summary.portfolioLoans }}</strong></div>
              <div class="metric-row"><span>Active loans</span><strong>{{ summary.activeLoans }}</strong></div>
              <div class="metric-row"><span>Settled loans</span><strong>{{ summary.settledLoans }}</strong></div>
              <div class="metric-row warning-row"><span>Overdue loans</span><strong>{{ summary.overdueLoans }}</strong></div>
              <div class="metric-row"><span>Lifetime recovered</span><strong>{{ formatCurrency(summary.lifetimeRecovered) }}</strong></div>
            </div>
          </div>
        </section>

        <section class="glass-panel table-panel mb-4">
          <div class="section-heading">
            <h3>Loans Issued in {{ selectedYear }}</h3>
            <span>{{ loans.length }} record(s)</span>
          </div>
          <div class="table-responsive">
            <table class="data-table responsive-card-table" v-if="loans.length > 0">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Issued Date</th>
                  <th>Principal</th>
                  <th>Interest</th>
                  <th>Total Payable</th>
                  <th>Recovered</th>
                  <th>Balance</th>
                  <th>Installments</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="loan in loans" :key="loan.id">
                  <td data-label="Member">
                    <strong>{{ loan.name }}</strong>
                    <span>{{ loan.membership_number || '-' }}</span>
                  </td>
                  <td data-label="Issued Date">{{ loan.issued_date || '-' }}</td>
                  <td data-label="Principal">{{ formatCurrency(loan.principal_amount) }}</td>
                  <td data-label="Interest">{{ loan.interest_rate }}% | {{ formatCurrency(loan.total_interest) }}</td>
                  <td data-label="Total Payable">{{ formatCurrency(loan.total_payable) }}</td>
                  <td data-label="Recovered">{{ formatCurrency(loan.paid_total) }}</td>
                  <td data-label="Balance">{{ formatCurrency(loan.balance) }}</td>
                  <td data-label="Installments">{{ loan.paid_installments }}/{{ loan.installments_count }}</td>
                  <td data-label="Status">
                    <span class="status-pill" :class="loanStatusClass(loan.status)">{{ loan.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-state">No loans issued in {{ selectedYear }}.</p>
          </div>
        </section>

        <section class="report-grid mb-4">
          <div class="glass-panel table-panel">
            <div class="section-heading">
              <h3>Member Loan Balances</h3>
              <span>Top 50 by balance</span>
            </div>
            <div class="table-responsive">
              <table class="data-table compact responsive-card-table" v-if="memberSummary.length > 0">
                <thead>
                  <tr>
                    <th>Member</th>
                    <th>Loans</th>
                    <th>Principal</th>
                    <th>Recovered</th>
                    <th>Balance</th>
                    <th>Overdue</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="member in memberSummary" :key="member.member_id">
                    <td data-label="Member">
                      <strong>{{ member.name }}</strong>
                      <span>{{ member.membership_number || '-' }}</span>
                    </td>
                    <td data-label="Loans">{{ member.loans_count }}</td>
                    <td data-label="Principal">{{ formatCurrency(member.principal_total) }}</td>
                    <td data-label="Recovered">{{ formatCurrency(member.recovered_total) }}</td>
                    <td data-label="Balance">{{ formatCurrency(member.balance) }}</td>
                    <td data-label="Overdue">{{ member.overdue_installments }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="empty-state">No member loan balances found.</p>
            </div>
          </div>

          <div class="glass-panel table-panel">
            <div class="section-heading">
              <h3>Overdue Installments</h3>
              <span>{{ overdueInstallments.length }} record(s)</span>
            </div>
            <div class="table-responsive">
              <table class="data-table compact responsive-card-table" v-if="overdueInstallments.length > 0">
                <thead>
                  <tr>
                    <th>Member</th>
                    <th>Due Date</th>
                    <th>No</th>
                    <th>Due</th>
                    <th>Paid</th>
                    <th>Balance</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in overdueInstallments" :key="item.id">
                    <td data-label="Member">
                      <strong>{{ item.name }}</strong>
                      <span>{{ item.membership_number || '-' }}</span>
                    </td>
                    <td data-label="Due Date">{{ item.due_date || '-' }}</td>
                    <td data-label="No">{{ item.installment_no }}</td>
                    <td data-label="Due">{{ formatCurrency(item.amount_due) }}</td>
                    <td data-label="Paid">{{ formatCurrency(item.amount_paid) }}</td>
                    <td data-label="Balance">{{ formatCurrency(item.balance) }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="empty-state">No overdue loan installments.</p>
            </div>
          </div>
        </section>
      </template>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  AlertTriangle,
  Banknote,
  Download,
  HandCoins,
  Printer,
  RefreshCw,
  TrendingUp,
  WalletCards
} from 'lucide-vue-next'
import DashboardLayout from '../../components/DashboardLayout.vue'
import { alertError } from '../../utils/alerts'
import { apiFetch } from '../../utils/api'

const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)
const availableYears = ref(Array.from({ length: 8 }, (_, i) => currentYear - i))
const loading = ref(false)

const summary = ref(defaultSummary())
const monthlyRecovery = ref([])
const loans = ref([])
const memberSummary = ref([])
const overdueInstallments = ref([])

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

function defaultSummary() {
  return {
    loansIssued: 0,
    principalIssued: 0,
    interestExpected: 0,
    totalPayableIssued: 0,
    recoveriesCount: 0,
    recoveredThisYear: 0,
    portfolioLoans: 0,
    activeLoans: 0,
    settledLoans: 0,
    overdueLoans: 0,
    outstandingBalance: 0,
    lifetimeRecovered: 0,
    overdueBalance: 0,
    overdueInstallments: 0
  }
}

const monthlyRecoveryRows = computed(() => {
  const lookup = monthlyRecovery.value.reduce((acc, row) => {
    acc[Number(row.month)] = row
    return acc
  }, {})

  return Array.from({ length: 12 }, (_, index) => {
    const month = index + 1
    return lookup[month] || {
      month,
      recoveries_count: 0,
      recovered_total: 0
    }
  })
})

const maxMonthlyRecovery = computed(() => {
  return Math.max(...monthlyRecoveryRows.value.map((item) => Number(item.recovered_total || 0)), 1)
})

const summaryCards = computed(() => [
  {
    label: `${selectedYear.value} Principal Issued`,
    value: formatCurrency(summary.value.principalIssued),
    caption: `${summary.value.loansIssued} loan(s) issued`,
    icon: HandCoins,
    iconClass: 'icon-issued'
  },
  {
    label: `${selectedYear.value} Interest Expected`,
    value: formatCurrency(summary.value.interestExpected),
    caption: `${formatCurrency(summary.value.totalPayableIssued)} total payable`,
    icon: TrendingUp,
    iconClass: 'icon-interest'
  },
  {
    label: `${selectedYear.value} Recovered`,
    value: formatCurrency(summary.value.recoveredThisYear),
    caption: `${summary.value.recoveriesCount} recovery record(s)`,
    icon: Banknote,
    iconClass: 'icon-recovered'
  },
  {
    label: 'Current Loan Balance',
    value: formatCurrency(summary.value.outstandingBalance),
    caption: `${summary.value.activeLoans} active loan(s)`,
    icon: WalletCards,
    iconClass: 'icon-balance',
    warning: Number(summary.value.outstandingBalance || 0) > 0
  },
  {
    label: 'Overdue Balance',
    value: formatCurrency(summary.value.overdueBalance),
    caption: `${summary.value.overdueInstallments} overdue installment(s)`,
    icon: AlertTriangle,
    iconClass: 'icon-overdue',
    warning: Number(summary.value.overdueBalance || 0) > 0
  }
])

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-LK', {
    style: 'currency',
    currency: 'LKR',
    minimumFractionDigits: 2
  }).format(Number(value || 0))
}

const formatMonth = (monthNo) => {
  const n = Number(monthNo)
  return Number.isFinite(n) && n >= 1 && n <= 12 ? monthNames[n - 1] : '-'
}

const recoveryPercent = (amount) => {
  return Math.max(3, Math.round((Number(amount || 0) / maxMonthlyRecovery.value) * 100))
}

const loanStatusClass = (status) => ({
  'status-active': status === 'Active',
  'status-overdue': status === 'Overdue',
  'status-settled': status === 'Settled'
})

const loadReport = async () => {
  loading.value = true

  try {
    const res = await apiFetch(`/api/get_financial_report.php?year=${selectedYear.value}`)
    const data = await res.json()

    if (data.success) {
      summary.value = { ...defaultSummary(), ...(data.summary || {}) }
      monthlyRecovery.value = Array.isArray(data.monthlyRecovery) ? data.monthlyRecovery : []
      loans.value = Array.isArray(data.loans) ? data.loans : []
      memberSummary.value = Array.isArray(data.memberSummary) ? data.memberSummary : []
      overdueInstallments.value = Array.isArray(data.overdueInstallments) ? data.overdueInstallments : []
      availableYears.value = Array.isArray(data.availableYears) && data.availableYears.length > 0
        ? data.availableYears
        : availableYears.value
    } else {
      alertError('Report error', data.message || 'Failed to load loan financial report.')
    }
  } catch (error) {
    alertError('Network error', 'Network error while loading loan financial report.')
  } finally {
    loading.value = false
  }
}

const printReport = () => {
  window.print()
}

const exportCsv = () => {
  const headers = [
    'Member',
    'Membership No',
    'Issued Date',
    'Principal',
    'Interest Rate',
    'Interest Amount',
    'Total Payable',
    'Recovered',
    'Balance',
    'Status'
  ]

  const rows = loans.value.map((loan) => [
    loan.name,
    loan.membership_number || '',
    loan.issued_date || '',
    Number(loan.principal_amount || 0).toFixed(2),
    Number(loan.interest_rate || 0).toFixed(2),
    Number(loan.total_interest || 0).toFixed(2),
    Number(loan.total_payable || 0).toFixed(2),
    Number(loan.paid_total || 0).toFixed(2),
    Number(loan.balance || 0).toFixed(2),
    loan.status || ''
  ])

  const escapeCsv = (value) => {
    const text = String(value ?? '')
    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
      return `"${text.replace(/"/g, '""')}"`
    }
    return text
  }

  const content = [
    headers.join(','),
    ...rows.map((row) => row.map(escapeCsv).join(','))
  ].join('\n')

  const blob = new Blob([`\uFEFF${content}`], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `loan_financial_report_${selectedYear.value}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  loadReport()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }

.report-header,
.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.report-subtitle {
  margin: 0;
  opacity: 0.9;
  font-size: 0.92rem;
}

.report-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.report-actions .btn {
  color: #fff;
  border-color: rgba(255, 255, 255, 0.55);
  background: rgba(255, 255, 255, 0.12);
  min-height: 40px;
  padding: 0.55rem 0.85rem;
}

.report-actions .btn-primary {
  border-color: transparent;
  background: rgba(6, 78, 59, 0.9);
}

.year-select {
  min-height: 40px;
  padding: 0.45rem 0.7rem;
  border: 1px solid rgba(255, 255, 255, 0.55);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  font-family: inherit;
  font-weight: 700;
}

.year-select option {
  color: var(--text-main);
}

.loading-panel,
.report-card,
.table-panel {
  padding: 1.25rem;
}

.loading-panel,
.empty-state {
  color: var(--text-muted);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-height: 126px;
  padding: 1rem;
}

.summary-card.warning strong {
  color: #b42332;
}

.summary-icon {
  width: 52px;
  height: 52px;
  flex: 0 0 52px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  color: #fff;
  box-shadow: 0 10px 22px rgba(15, 23, 42, 0.15);
}

.icon-issued { background: linear-gradient(135deg, #0f766e, #14b8a6); }
.icon-interest { background: linear-gradient(135deg, #047857, #d97706); }
.icon-recovered { background: linear-gradient(135deg, #15803d, #84cc16); }
.icon-balance { background: linear-gradient(135deg, #0e7490, #2dd4bf); }
.icon-overdue { background: linear-gradient(135deg, #dc2626, #d97706); }

.summary-card span,
.metric-row span,
.monthly-row span,
.section-heading span,
.data-table td span {
  color: var(--text-muted);
  font-size: 0.82rem;
}

.summary-card strong {
  display: block;
  margin-top: 0.2rem;
  color: var(--primary-dark);
  font-size: 1.15rem;
  overflow-wrap: anywhere;
}

.summary-card small,
.monthly-row small {
  display: block;
  margin-top: 0.1rem;
  color: var(--text-muted);
  font-size: 0.76rem;
}

.report-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.section-heading {
  margin-bottom: 1rem;
}

.section-heading h3 {
  margin: 0;
  color: var(--primary-dark);
  font-size: 1.05rem;
}

.monthly-list,
.metric-list {
  display: grid;
  gap: 0.7rem;
}

.monthly-row {
  display: grid;
  grid-template-columns: minmax(110px, 0.8fr) minmax(120px, 1fr) minmax(120px, auto);
  align-items: center;
  gap: 0.8rem;
}

.bar-track {
  height: 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.bar-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
}

.monthly-row strong,
.metric-row strong {
  color: var(--primary-dark);
  text-align: right;
}

.metric-row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem;
  border: 1px solid rgba(20, 184, 166, 0.14);
  border-radius: 8px;
  background: rgba(20, 184, 166, 0.04);
}

.warning-row strong {
  color: #b42332;
}

.table-responsive {
  overflow: auto;
  border: 1px solid rgba(15, 118, 110, 0.12);
  border-radius: 8px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 920px;
  background: #fff;
}

.data-table.compact {
  min-width: 680px;
}

.data-table th,
.data-table td {
  padding: 0.7rem 0.8rem;
  text-align: left;
  border-bottom: 1px solid rgba(15, 23, 42, 0.07);
  vertical-align: top;
  font-size: 0.88rem;
}

.data-table th {
  position: sticky;
  top: 0;
  z-index: 1;
  color: var(--primary-color);
  background: #eefaf7;
  font-weight: 700;
}

.data-table td strong {
  display: block;
  color: var(--text-main);
}

.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 78px;
  padding: 0.25rem 0.5rem;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 750;
}

.status-active {
  background: rgba(20, 184, 166, 0.13);
  color: var(--primary-dark);
}

.status-overdue {
  background: rgba(220, 53, 69, 0.13);
  color: #b42332;
}

.status-settled {
  background: rgba(25, 135, 84, 0.13);
  color: #126742;
}

@media (max-width: 1100px) {
  .report-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .report-header {
    align-items: stretch;
    flex-direction: column;
  }

  .report-actions {
    align-items: stretch;
  }

  .report-actions .btn,
  .year-select {
    flex: 1 1 160px;
  }

  .monthly-row {
    grid-template-columns: 1fr;
  }

  .monthly-row strong {
    text-align: left;
  }
}

@media print {
  .dashboard-navbar,
  .dashboard-sidebar,
  .report-actions {
    display: none !important;
  }

  .report-page,
  .dashboard-main,
  .main-content-wrapper {
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  .glass-panel {
    box-shadow: none !important;
    border-color: #ddd !important;
    break-inside: avoid;
  }
}
</style>
