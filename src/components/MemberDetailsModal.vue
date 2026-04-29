<template>
  <Teleport to="body">
    <div v-if="show" class="detail-modal">
      <header class="detail-topbar">
        <div class="title-block">
          <p class="eyebrow">Member Details</p>
          <h2>{{ detail?.member?.name || 'Loading member...' }}</h2>
          <div class="member-meta">
            <span>{{ detail?.member?.membership_number || 'N/A' }}</span>
            <span>{{ detail?.member?.nic || '-' }}</span>
            <span class="status-badge" :class="statusClass(detail?.member?.status)">
              {{ detail?.member?.status || 'Active' }}
            </span>
          </div>
        </div>
        <div class="topbar-actions">
          <button class="icon-button" type="button" @click="$emit('close')" title="Close">
            <X size="20" />
          </button>
        </div>
      </header>

      <main class="detail-body">
        <div v-if="loading" class="loading-state">Loading member details...</div>

        <template v-else-if="detail">
          <section class="summary-strip">
            <div class="summary-tile">
              <span>Total Paid</span>
              <strong>{{ formatCurrency(detail.summary.total_paid) }}</strong>
            </div>
            <div class="summary-tile">
              <span>Total Benefits</span>
              <strong>{{ formatCurrency(detail.summary.total_benefits) }}</strong>
            </div>
            <div class="summary-tile" :class="{ warning: Number(detail.outstanding?.outstanding_amount || 0) > 0 }">
              <span>Outstanding</span>
              <strong>{{ formatCurrency(detail.outstanding?.outstanding_amount || 0) }}</strong>
            </div>
            <div class="summary-tile">
              <span>Dependents</span>
              <strong>{{ detail.summary.dependents_count }}</strong>
            </div>
          </section>

          <nav class="tab-bar" aria-label="Member detail sections">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              type="button"
              class="tab-button"
              :class="{ active: activeTab === tab.id }"
              @click="activeTab = tab.id"
            >
              <component :is="tab.icon" size="17" />
              {{ tab.label }}
            </button>
          </nav>

          <section v-if="activeTab === 'profile'" class="content-grid">
            <div class="info-panel">
              <h3>Profile</h3>
              <div class="info-list">
                <div class="info-row"><span>Membership No</span><strong>{{ detail.member.membership_number || '-' }}</strong></div>
                <div class="info-row"><span>Membership Date</span><strong>{{ detail.member.membership_date || '-' }}</strong></div>
                <div class="info-row"><span>NIC</span><strong>{{ detail.member.nic || '-' }}</strong></div>
                <div class="info-row"><span>City</span><strong>{{ detail.member.city || '-' }}</strong></div>
                <div class="info-row"><span>Contact</span><strong>{{ detail.member.contact_number || '-' }}</strong></div>
                <div class="info-row"><span>Date Added</span><strong>{{ detail.member.date_added || '-' }}</strong></div>
                <div class="info-row full"><span>Address</span><strong>{{ detail.member.address || '-' }}</strong></div>
                <div v-if="detail.member.status_reason" class="info-row full"><span>Status Reason</span><strong>{{ detail.member.status_reason }}</strong></div>
              </div>
            </div>

            <div class="info-panel">
              <h3>Status History</h3>
              <div v-if="detail.status_history.length > 0" class="timeline">
                <div v-for="(item, index) in detail.status_history" :key="index" class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div>
                    <strong>{{ item.new_status }}</strong>
                    <p>{{ item.changed_at }} | {{ item.reason || '-' }}</p>
                    <small>Previous: {{ item.old_status || '-' }}</small>
                  </div>
                </div>
              </div>
              <p v-else class="empty-state">No status history available.</p>
            </div>
          </section>

          <section v-if="activeTab === 'dependents'" class="table-panel">
            <table class="detail-table responsive-card-table" v-if="detail.dependents.length > 0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Relationship</th>
                  <th>Birth Year</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(dep, index) in detail.dependents" :key="index">
                  <td data-label="Name">{{ dep.name }}</td>
                  <td data-label="Relationship">{{ dep.relationship }}</td>
                  <td data-label="Birth Year">{{ dep.birth_year || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-state">No dependents available.</p>
          </section>

          <section v-if="activeTab === 'payments'" class="table-panel">
            <div v-if="detail.outstanding?.outstanding_months > 0" class="outstanding-box">
              <strong>{{ detail.outstanding.outstanding_months }} outstanding month(s)</strong>
              <span>{{ formatMissingPeriods(detail.outstanding.missing_periods) }}</span>
            </div>

            <div class="history-filter">
              <label class="form-label">Year</label>
              <select v-model="selectedYear" class="form-control">
                <option value="all">All Years</option>
                <option v-for="year in historyYears" :key="year" :value="year">{{ year }}</option>
              </select>
            </div>

            <table class="detail-table responsive-card-table" v-if="filteredPayments.length > 0">
              <thead>
                <tr>
                  <th>Year</th>
                  <th>Month</th>
                  <th>Paid Date</th>
                  <th>Member Fee</th>
                  <th>Share Capital</th>
                  <th>Special</th>
                  <th>Total</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(pay, index) in filteredPayments" :key="index">
                  <td data-label="Year">{{ pay.payment_year }}</td>
                  <td data-label="Month">{{ formatMonth(pay.payment_month) }}</td>
                  <td data-label="Paid Date">{{ pay.paid_date || '-' }}</td>
                  <td data-label="Member Fee">{{ formatCurrency(pay.member_fee || 0) }}</td>
                  <td data-label="Share Capital">{{ formatCurrency(pay.share_capital || 0) }}</td>
                  <td data-label="Special">{{ formatCurrency(pay.special_charges || 0) }}</td>
                  <td data-label="Total">{{ formatCurrency(pay.total_amount || 0) }}</td>
                  <td data-label="Remarks">{{ pay.remarks || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-state">No payment records available.</p>
          </section>

          <section v-if="activeTab === 'benefits'" class="table-panel">
            <table class="detail-table responsive-card-table" v-if="filteredBenefits.length > 0">
              <thead>
                <tr>
                  <th>Paid Date</th>
                  <th>Type</th>
                  <th>Details</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(benefit, index) in filteredBenefits" :key="index">
                  <td data-label="Paid Date">{{ benefit.paid_date || '-' }}</td>
                  <td data-label="Type">{{ benefit.benefit_type === 'death_gratuity' ? 'Death Gratuity' : 'Special Donation' }}</td>
                  <td data-label="Details">
                    <span v-if="benefit.benefit_type === 'death_gratuity'">
                      {{ benefit.dependent_name || '-' }} ({{ benefit.relationship || '-' }})
                    </span>
                    <span v-else>{{ benefit.aid_nature || '-' }}</span>
                  </td>
                  <td data-label="Amount">{{ formatCurrency(benefit.amount || 0) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-state">No benefit records available.</p>
          </section>
        </template>
      </main>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import {
  CreditCard,
  Gift,
  UserRound,
  Users,
  X
} from 'lucide-vue-next'
import { alertError } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const props = defineProps({
  show: Boolean,
  memberId: { type: [Number, String], default: null }
})

defineEmits(['close'])

const loading = ref(false)
const detail = ref(null)
const activeTab = ref('profile')
const selectedYear = ref('all')

const tabs = [
  { id: 'profile', label: 'Profile', icon: UserRound },
  { id: 'dependents', label: 'Dependents', icon: Users },
  { id: 'payments', label: 'Payments', icon: CreditCard },
  { id: 'benefits', label: 'Benefits', icon: Gift }
]

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

const getYearFromValue = (value) => {
  if (value === null || value === undefined) return null
  const matched = String(value).trim().match(/^(\d{4})/)
  return matched ? matched[1] : null
}

const historyYears = computed(() => {
  if (!detail.value) return []
  const years = new Set()

  detail.value.payments.forEach((payment) => {
    const year = getYearFromValue(payment.payment_year || payment.paid_date)
    if (year) years.add(year)
  })

  detail.value.benefits.forEach((benefit) => {
    const year = getYearFromValue(benefit.paid_date)
    if (year) years.add(year)
  })

  return Array.from(years).sort((a, b) => Number(b) - Number(a))
})

const filteredPayments = computed(() => {
  if (!detail.value) return []
  if (selectedYear.value === 'all') return detail.value.payments
  return detail.value.payments.filter((payment) => {
    return getYearFromValue(payment.payment_year || payment.paid_date) === selectedYear.value
  })
})

const filteredBenefits = computed(() => {
  if (!detail.value) return []
  if (selectedYear.value === 'all') return detail.value.benefits
  return detail.value.benefits.filter((benefit) => {
    return getYearFromValue(benefit.paid_date) === selectedYear.value
  })
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
  return Number.isFinite(n) && n >= 1 && n <= 12 ? monthNames[n - 1] : '-'
}

const formatMissingPeriods = (periods) => {
  if (!Array.isArray(periods) || periods.length === 0) return '-'
  const labels = periods.map((period) => {
    if (!period.label) return ''
    if (Number(period.outstanding_amount || 0) > 0) {
      return `${period.label} (${formatCurrency(period.outstanding_amount)})`
    }
    return period.label
  }).filter(Boolean)
  if (labels.length <= 4) return labels.join(', ')
  return `${labels.slice(0, 4).join(', ')} +${labels.length - 4} more`
}

const statusClass = (status) => ({
  'status-active': status === 'Active' || !status,
  'status-inactive': status === 'Inactive',
  'status-suspended': status === 'Suspended'
})

const normalizeDetail = (data) => ({
  member: data.member || {},
  dependents: Array.isArray(data.dependents) ? data.dependents : [],
  payments: Array.isArray(data.payments) ? data.payments : [],
  benefits: Array.isArray(data.benefits) ? data.benefits : [],
  status_history: Array.isArray(data.status_history) ? data.status_history : [],
  outstanding: data.outstanding || {
    outstanding_months: 0,
    outstanding_amount: 0,
    missing_periods: []
  },
  summary: data.summary || {
    dependents_count: 0,
    payments_count: 0,
    benefits_count: 0,
    total_paid: 0,
    total_benefits: 0
  }
})

const loadDetail = async () => {
  if (!props.memberId) return

  loading.value = true
  detail.value = null
  activeTab.value = 'profile'
  selectedYear.value = 'all'

  try {
    const res = await apiFetch(`/api/get_member_detailed_report.php?member_id=${props.memberId}`)
    const data = await res.json()

    if (data.success) {
      detail.value = normalizeDetail(data)
    } else {
      alertError('Detail report error', data.message || 'Failed to load member details.')
    }
  } catch (error) {
    alertError('Network error', 'Network error while loading member details.')
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.show, props.memberId],
  ([show]) => {
    if (show) {
      loadDetail()
    }
  }
)
</script>

<style scoped>
.detail-modal {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: flex;
  flex-direction: column;
  background: #f6f8fb;
  color: var(--text-main);
}

.detail-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem clamp(1rem, 3vw, 2rem);
  background: linear-gradient(135deg, #0f172a 0%, #14532d 100%);
  color: #fff;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.2);
}

.title-block {
  min-width: 0;
}

.eyebrow {
  margin: 0 0 0.2rem;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.72);
}

.title-block h2 {
  margin: 0;
  color: #fff;
  font-size: clamp(1.25rem, 2vw, 1.85rem);
}

.member-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-top: 0.4rem;
  color: rgba(255, 255, 255, 0.82);
  font-size: 0.88rem;
}

.topbar-actions {
  display: flex;
  gap: 0.5rem;
}

.icon-button {
  width: 42px;
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
}

.icon-button:hover {
  background: rgba(255, 255, 255, 0.2);
}

.detail-body {
  flex: 1;
  overflow: auto;
  padding: clamp(1rem, 2.4vw, 2rem);
}

.loading-state,
.empty-state {
  padding: 2rem;
  text-align: center;
  color: var(--text-muted);
}

.summary-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(150px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.summary-tile,
.info-panel,
.table-panel {
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
}

.summary-tile {
  padding: 1rem;
}

.summary-tile span {
  display: block;
  color: var(--text-muted);
  font-size: 0.82rem;
  font-weight: 600;
}

.summary-tile strong {
  display: block;
  margin-top: 0.3rem;
  color: var(--primary-dark);
  font-size: 1.2rem;
}

.summary-tile.warning strong {
  color: #b42332;
}

.tab-bar {
  display: flex;
  gap: 0.5rem;
  overflow-x: auto;
  margin-bottom: 1rem;
  padding-bottom: 0.2rem;
}

.tab-button {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.62rem 0.9rem;
  border: 1px solid rgba(20, 184, 166, 0.18);
  border-radius: 8px;
  background: #fff;
  color: var(--text-main);
  font-family: inherit;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.tab-button.active {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: #fff;
}

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) minmax(320px, 0.7fr);
  gap: 1rem;
}

.info-panel {
  padding: 1rem;
}

.info-panel h3 {
  color: var(--primary-dark);
  margin-bottom: 1rem;
}

.info-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
}

.info-row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.75rem;
  border: 1px solid rgba(20, 184, 166, 0.14);
  border-radius: 8px;
  background: rgba(20, 184, 166, 0.04);
}

.info-row.full {
  grid-column: 1 / -1;
}

.info-row span {
  color: var(--text-muted);
  font-size: 0.78rem;
  font-weight: 700;
}

.info-row strong {
  font-size: 0.92rem;
  word-break: break-word;
}

.timeline {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.timeline-item {
  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 0.65rem;
}

.timeline-dot {
  width: 10px;
  height: 10px;
  margin-top: 0.35rem;
  border-radius: 50%;
  background: var(--primary-color);
  box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.14);
}

.timeline-item p {
  margin: 0.2rem 0;
  color: var(--text-muted);
  font-size: 0.82rem;
}

.timeline-item small {
  color: var(--text-muted);
}

.table-panel {
  overflow: hidden;
}

.history-filter {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 1rem 1rem 0;
}

.history-filter .form-label {
  margin: 0;
}

.history-filter .form-control {
  max-width: 180px;
}

.outstanding-box {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 1rem 1rem 0;
  padding: 0.8rem;
  border: 1px solid rgba(220, 53, 69, 0.18);
  border-radius: 8px;
  background: rgba(220, 53, 69, 0.06);
  color: #7f1d1d;
}

.detail-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 760px;
}

.detail-table th,
.detail-table td {
  padding: 0.72rem 0.85rem;
  border-bottom: 1px solid rgba(15, 23, 42, 0.07);
  text-align: left;
  vertical-align: top;
  font-size: 0.88rem;
}

.detail-table th {
  position: sticky;
  top: 0;
  z-index: 1;
  color: var(--primary-color);
  background: #eefaf7;
  font-weight: 700;
}

.detail-table tbody tr:nth-child(even) {
  background: rgba(20, 184, 166, 0.025);
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
  background: rgba(25, 135, 84, 0.16);
  color: #126742;
}

.status-inactive {
  background: rgba(108, 117, 125, 0.18);
  color: #495057;
}

.status-suspended {
  background: rgba(220, 53, 69, 0.16);
  color: #b42332;
}

.detail-topbar .status-badge {
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
}

@media (max-width: 900px) {
  .summary-strip,
  .content-grid {
    grid-template-columns: 1fr;
  }

  .info-list {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 620px) {
  .detail-topbar {
    align-items: flex-start;
  }

  .topbar-actions {
    flex: 0 0 auto;
  }

  .summary-strip {
    gap: 0.75rem;
  }

  .table-panel {
    max-height: 70vh;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
  }
}

@media print {
  .detail-modal {
    position: static;
    inset: auto;
    background: #fff;
  }

  .detail-topbar {
    color: #111;
    background: #fff;
    box-shadow: none;
    border-bottom: 1px solid #ddd;
  }

  .title-block h2,
  .eyebrow {
    color: #111;
  }

  .topbar-actions,
  .tab-bar {
    display: none !important;
  }

  .detail-body {
    overflow: visible;
    padding: 0.5rem;
  }
}
</style>
