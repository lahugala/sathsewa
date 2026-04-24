<template>
  <DashboardLayout>
    <div class="report-page animate-fade-in">
      <div class="header-bar mb-4 report-header">
        <div>
          <h2>Member Full Detailed Report</h2>
          <p class="report-subtitle">Complete member profile, dependents, payments, and benefits.</p>
        </div>
        <div class="report-actions">
          <button class="btn btn-outline" type="button" :disabled="isLoading" @click="loadReport">
            {{ isLoading ? 'Refreshing...' : 'Refresh' }}
          </button>
          <button class="btn btn-outline" type="button" :disabled="isLoading || filteredMembers.length === 0" @click="exportCsv">
            Export CSV
          </button>
          <button class="btn btn-outline" type="button" :disabled="isLoading || filteredMembers.length === 0" @click="exportExcel">
            Export Excel
          </button>
          <button class="btn btn-outline" type="button" :disabled="!memberDetail" @click="printDetailReport">
            Print Selected
          </button>
          <button class="btn btn-primary" type="button" @click="printReport">Print Report</button>
        </div>
      </div>

      <div class="summary-grid mb-4 no-print-detail">
        <div class="glass-panel summary-card">
          <p class="summary-label">Members</p>
          <p class="summary-value">{{ members.length }}</p>
        </div>
        <div class="glass-panel summary-card">
          <p class="summary-label">Dependents</p>
          <p class="summary-value">{{ totals.dependents }}</p>
        </div>
        <div class="glass-panel summary-card">
          <p class="summary-label">Total Payments</p>
          <p class="summary-value">{{ formatCurrency(totals.payments) }}</p>
        </div>
        <div class="glass-panel summary-card">
          <p class="summary-label">Total Benefits</p>
          <p class="summary-value">{{ formatCurrency(totals.benefits) }}</p>
        </div>
        <div class="glass-panel summary-card">
          <p class="summary-label">Outstanding Members</p>
          <p class="summary-value">{{ outstandingMembers.length }}</p>
        </div>
        <div class="glass-panel summary-card">
          <p class="summary-label">Outstanding Amount</p>
          <p class="summary-value">{{ formatCurrency(totals.outstanding) }}</p>
        </div>
      </div>

      <div class="glass-panel mb-4 lookup-panel no-print-detail">
        <div class="form-group" style="margin: 0;">
          <label class="form-label">Search by Membership Number</label>
          <input
            v-model="membershipSearch"
            type="search"
            class="form-control"
            placeholder="Type membership number (e.g., M-001)"
          >
        </div>

        <div class="lookup-results">
          <p v-if="!membershipSearch.trim()" class="row-subtext">
            Enter a membership number to find a member and open the detailed report.
          </p>

          <p v-else-if="filteredMembers.length === 0" class="row-subtext">
            No member found for this membership number.
          </p>

          <div v-else class="lookup-list">
            <div class="lookup-item" v-for="member in filteredMembers" :key="member.id">
              <div class="lookup-item-main">
                <strong>{{ member.name }}</strong>
                <div class="row-subtext">
                  {{ member.membership_number || '-' }} | {{ member.nic }} | {{ member.city }}
                  | {{ member.status || 'Active' }}
                </div>
              </div>
              <button class="btn btn-sm btn-outline" type="button" @click="loadMemberDetail(member.id)">
                View Detail
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="glass-panel mb-4 outstanding-panel no-print-detail">
        <div class="panel-heading">
          <div>
            <h3>Outstanding Payment Members</h3>
            <p class="row-subtext">Calculated through {{ outstandingAsOf || 'current date' }} from each member's membership date.</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="data-table report-table compact" v-if="outstandingMembers.length > 0">
            <thead>
              <tr>
                <th>Membership No</th>
                <th>Name</th>
                <th>Status</th>
                <th>Missing Months</th>
                <th>Outstanding</th>
                <th>Missing Periods</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in outstandingMembers" :key="member.id">
                <td>{{ member.membership_number || '-' }}</td>
                <td>{{ member.name }}</td>
                <td>
                  <span class="status-badge" :class="statusClass(member.status)">
                    {{ member.status || 'Active' }}
                  </span>
                </td>
                <td>{{ member.outstanding.outstanding_months }}</td>
                <td>{{ formatCurrency(member.outstanding.outstanding_amount) }}</td>
                <td>{{ formatMissingPeriods(member.outstanding.missing_periods) }}</td>
                <td>
                  <button class="btn btn-sm btn-outline" type="button" @click="loadMemberDetail(member.id)">
                    View Detail
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-center py-4">No outstanding payment members found.</p>
        </div>
      </div>

      <div class="glass-panel detail-panel" v-if="memberDetail">
        <div class="detail-header">
          <h3>Detailed Single Page Report</h3>
          <p>{{ memberDetail.member.name }} ({{ memberDetail.member.membership_number || 'N/A' }})</p>
        </div>

        <div class="history-filter no-print-detail" v-if="historyYears.length > 0">
          <label class="form-label" for="historyYear">History Year</label>
          <select id="historyYear" v-model="selectedHistoryYear" class="form-select">
            <option value="all">All Years</option>
            <option v-for="year in historyYears" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>

        <div v-if="detailLoading" class="text-center py-4">Loading detailed report...</div>
        <div v-else>
          <div class="extract-sheet mb-4">
            <h4 class="extract-title">Member Profile</h4>
            <div class="extract-row"><span class="extract-label">Member Name</span><span class="extract-value">{{ memberDetail.member.name }}</span></div>
            <div class="extract-row"><span class="extract-label">Membership No</span><span class="extract-value">{{ memberDetail.member.membership_number || '-' }}</span></div>
            <div class="extract-row"><span class="extract-label">Membership Date</span><span class="extract-value">{{ memberDetail.member.membership_date || '-' }}</span></div>
            <div class="extract-row"><span class="extract-label">NIC</span><span class="extract-value">{{ memberDetail.member.nic }}</span></div>
            <div class="extract-row"><span class="extract-label">City</span><span class="extract-value">{{ memberDetail.member.city }}</span></div>
            <div class="extract-row"><span class="extract-label">Contact</span><span class="extract-value">{{ memberDetail.member.contact_number }}</span></div>
            <div class="extract-row">
              <span class="extract-label">Status</span>
              <span class="extract-value">
                <span class="status-badge" :class="statusClass(memberDetail.member.status)">
                  {{ memberDetail.member.status || 'Active' }}
                </span>
              </span>
            </div>
            <div v-if="memberDetail.member.status_reason" class="extract-row"><span class="extract-label">Status Reason</span><span class="extract-value">{{ memberDetail.member.status_reason }}</span></div>
            <div class="extract-row"><span class="extract-label">Address</span><span class="extract-value">{{ memberDetail.member.address || '-' }}</span></div>
            <div class="extract-row"><span class="extract-label">Date Added</span><span class="extract-value">{{ memberDetail.member.date_added || '-' }}</span></div>
          </div>

          <div class="extract-sheet mb-4">
            <h4 class="extract-title">Report Summary</h4>
            <div class="extract-row"><span class="extract-label">Dependents Count</span><span class="extract-value">{{ memberDetail.summary.dependents_count }}</span></div>
            <div class="extract-row"><span class="extract-label">Payments Count</span><span class="extract-value">{{ memberDetail.summary.payments_count }}</span></div>
            <div class="extract-row"><span class="extract-label">Benefits Count</span><span class="extract-value">{{ memberDetail.summary.benefits_count }}</span></div>
            <div class="extract-row"><span class="extract-label">Total Paid</span><span class="extract-value">{{ formatCurrency(memberDetail.summary.total_paid) }}</span></div>
            <div class="extract-row"><span class="extract-label">Total Benefits</span><span class="extract-value">{{ formatCurrency(memberDetail.summary.total_benefits) }}</span></div>
            <div class="extract-row"><span class="extract-label">Outstanding Months</span><span class="extract-value">{{ memberDetail.outstanding?.outstanding_months || 0 }}</span></div>
            <div class="extract-row"><span class="extract-label">Outstanding Amount</span><span class="extract-value">{{ formatCurrency(memberDetail.outstanding?.outstanding_amount || 0) }}</span></div>
          </div>

          <div class="detail-section mb-4">
            <h4>Status History</h4>
            <div class="table-responsive">
              <table class="data-table report-table compact" v-if="memberDetail.status_history.length > 0">
                <thead>
                  <tr>
                    <th>Changed At</th>
                    <th>Previous</th>
                    <th>New Status</th>
                    <th>Reason</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in memberDetail.status_history" :key="index">
                    <td>{{ item.changed_at }}</td>
                    <td>{{ item.old_status || '-' }}</td>
                    <td>
                      <span class="status-badge" :class="statusClass(item.new_status)">
                        {{ item.new_status }}
                      </span>
                    </td>
                    <td>{{ item.reason || '-' }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="text-center py-4">No status history available.</p>
            </div>
          </div>

          <div class="detail-section mb-4">
            <h4>Dependents</h4>
            <div class="table-responsive">
              <table class="data-table report-table compact" v-if="memberDetail.dependents.length > 0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Relationship</th>
                    <th>Birth Year</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(dep, index) in memberDetail.dependents" :key="index">
                    <td>{{ dep.name }}</td>
                    <td>{{ dep.relationship }}</td>
                    <td>{{ dep.birth_year || '-' }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="text-center py-4">No dependents available.</p>
            </div>
          </div>

          <div class="detail-section mb-4">
            <h4>Payment History</h4>
            <div class="outstanding-summary" v-if="memberDetail.outstanding?.outstanding_months > 0">
              <strong>Outstanding:</strong>
              {{ memberDetail.outstanding.outstanding_months }} month(s),
              {{ formatCurrency(memberDetail.outstanding.outstanding_amount) }}
              <span>({{ formatMissingPeriods(memberDetail.outstanding.missing_periods) }})</span>
            </div>
            <div class="table-responsive">
              <table class="data-table report-table compact" v-if="filteredDetailPayments.length > 0">
                <thead>
                  <tr>
                    <th>Year</th>
                    <th>Month</th>
                    <th>Paid Date</th>
                    <th>Member Fee</th>
                    <th>Share Capital</th>
                    <th>Special Charges</th>
                    <th>Total</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(pay, index) in filteredDetailPayments" :key="index">
                    <td>{{ pay.payment_year }}</td>
                    <td>{{ formatMonth(pay.payment_month) }}</td>
                    <td>{{ pay.paid_date || '-' }}</td>
                    <td>{{ formatCurrency(pay.member_fee || 0) }}</td>
                    <td>{{ formatCurrency(pay.share_capital || 0) }}</td>
                    <td>{{ formatCurrency(pay.special_charges || 0) }}</td>
                    <td>{{ formatCurrency(pay.total_amount || 0) }}</td>
                    <td>{{ pay.remarks || '-' }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="text-center py-4">No payment records available for selected year.</p>
            </div>
          </div>

          <div class="detail-section">
            <h4>Benefit History</h4>
            <div class="table-responsive">
              <table class="data-table report-table compact" v-if="filteredDetailBenefits.length > 0">
                <thead>
                  <tr>
                    <th>Paid Date</th>
                    <th>Type</th>
                    <th>Details</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(benefit, index) in filteredDetailBenefits" :key="index">
                    <td>{{ benefit.paid_date || '-' }}</td>
                    <td>{{ benefit.benefit_type === 'death_gratuity' ? 'Death Gratuity' : 'Special Donation' }}</td>
                    <td>
                      <span v-if="benefit.benefit_type === 'death_gratuity'">
                        {{ benefit.dependent_name || '-' }} ({{ benefit.relationship || '-' }})
                      </span>
                      <span v-else>{{ benefit.aid_nature || '-' }}</span>
                    </td>
                    <td>{{ formatCurrency(benefit.amount || 0) }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="text-center py-4">No benefit records available for selected year.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import DashboardLayout from '../../components/DashboardLayout.vue'
import { alertError, alertWarning } from '../../utils/alerts'

const members = ref([])
const isLoading = ref(false)
const membershipSearch = ref('')

const selectedMemberId = ref(null)
const memberDetail = ref(null)
const detailLoading = ref(false)
const selectedHistoryYear = ref('all')

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

const filteredMembers = computed(() => {
  const q = membershipSearch.value.trim().toLowerCase()
  if (!q) return []
  return members.value.filter((m) =>
    String(m.membership_number || '').toLowerCase().includes(q)
  )
})

const outstandingMembers = computed(() => {
  return members.value
    .filter((member) => Number(member.outstanding?.outstanding_months || 0) > 0)
    .sort((a, b) => Number(b.outstanding?.outstanding_amount || 0) - Number(a.outstanding?.outstanding_amount || 0))
})

const outstandingAsOf = computed(() => {
  return outstandingMembers.value[0]?.outstanding?.as_of_date || ''
})

const totals = computed(() => {
  return members.value.reduce(
    (acc, member) => {
      acc.dependents += Number(member.dependents_count || 0)
      acc.payments += Number(member.payments?.total_paid || 0)
      acc.benefits += Number(member.benefits?.total_benefits || 0)
      acc.outstanding += Number(member.outstanding?.outstanding_amount || 0)
      return acc
    },
    { dependents: 0, payments: 0, benefits: 0, outstanding: 0 }
  )
})

const getYearFromValue = (value) => {
  if (value === null || value === undefined) return null
  const text = String(value).trim()
  if (!text) return null
  const matched = text.match(/^(\d{4})/)
  return matched ? matched[1] : null
}

const historyYears = computed(() => {
  if (!memberDetail.value) return []
  const years = new Set()

  memberDetail.value.payments.forEach((payment) => {
    const y = getYearFromValue(payment.payment_year || payment.paid_date)
    if (y) years.add(y)
  })

  memberDetail.value.benefits.forEach((benefit) => {
    const y = getYearFromValue(benefit.paid_date || benefit.payment_year)
    if (y) years.add(y)
  })

  return Array.from(years).sort((a, b) => Number(b) - Number(a))
})

const filteredDetailPayments = computed(() => {
  if (!memberDetail.value) return []
  if (selectedHistoryYear.value === 'all') return memberDetail.value.payments
  return memberDetail.value.payments.filter((payment) => {
    const y = getYearFromValue(payment.payment_year || payment.paid_date)
    return y === selectedHistoryYear.value
  })
})

const filteredDetailBenefits = computed(() => {
  if (!memberDetail.value) return []
  if (selectedHistoryYear.value === 'all') return memberDetail.value.benefits
  return memberDetail.value.benefits.filter((benefit) => {
    const y = getYearFromValue(benefit.paid_date || benefit.payment_year)
    return y === selectedHistoryYear.value
  })
})

const formatCurrency = (value) => {
  const amount = Number(value || 0)
  return new Intl.NumberFormat('en-LK', {
    style: 'currency',
    currency: 'LKR',
    minimumFractionDigits: 2
  }).format(amount)
}

const formatMonth = (monthNo) => {
  const n = Number(monthNo)
  if (!Number.isFinite(n) || n < 1 || n > 12) return '-'
  return monthNames[n - 1]
}

const formatMissingPeriods = (periods) => {
  if (!Array.isArray(periods) || periods.length === 0) return '-'
  const labels = periods.map((period) => period.label).filter(Boolean)
  if (labels.length <= 4) return labels.join(', ')
  return `${labels.slice(0, 4).join(', ')} +${labels.length - 4} more`
}

const statusClass = (status) => {
  return {
    'status-active': status === 'Active' || !status,
    'status-inactive': status === 'Inactive',
    'status-suspended': status === 'Suspended'
  }
}

const loadReport = async () => {
  isLoading.value = true

  try {
    const res = await fetch('/api/get_member_full_report.php')
    const data = await res.json()

    if (data.success) {
      members.value = Array.isArray(data.members) ? data.members : []

      if (selectedMemberId.value) {
        const stillExists = members.value.some((m) => m.id === selectedMemberId.value)
        if (!stillExists) {
          selectedMemberId.value = null
          memberDetail.value = null
        }
      }
    } else {
      alertError('Report error', data.message || 'Failed to load member report.')
    }
  } catch (error) {
    console.error('Error loading member full report:', error)
    alertError('Network error', 'Network error while loading report data.')
  } finally {
    isLoading.value = false
  }
}

const loadMemberDetail = async (memberId) => {
  selectedMemberId.value = memberId
  detailLoading.value = true
  selectedHistoryYear.value = 'all'

  try {
    const res = await fetch(`/api/get_member_detailed_report.php?member_id=${memberId}`)
    const data = await res.json()

    if (data.success) {
      memberDetail.value = {
        member: data.member,
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
          total_benefits: 0,
          status_changes_count: 0
        }
      }
    } else {
      memberDetail.value = null
      alertError('Detail report error', data.message || 'Failed to load member detail report.')
    }
  } catch (error) {
    console.error('Error loading member detail report:', error)
    memberDetail.value = null
    alertError('Network error', 'Network error while loading member detail report.')
  } finally {
    detailLoading.value = false
  }
}

const printReport = () => {
  window.print()
}

const printDetailReport = () => {
  if (!memberDetail.value) {
    alertWarning('No member selected', 'Select a member before printing the detailed report.')
    return
  }
  window.print()
}

const buildExportRows = () => {
  return filteredMembers.value.map((member) => {
    const dependentNames = Array.isArray(member.dependents)
      ? member.dependents.map((d) => d.name).filter(Boolean).join('; ')
      : ''

    return {
      membership_number: member.membership_number || '',
      name: member.name || '',
      nic: member.nic || '',
      contact_number: member.contact_number || '',
      city: member.city || '',
      status: member.status || 'Active',
      status_reason: member.status_reason || '',
      membership_date: member.membership_date || '',
      date_added: member.date_added || '',
      dependents_count: Number(member.dependents_count || 0),
      dependents: dependentNames,
      total_paid: Number(member.payments?.total_paid || 0).toFixed(2),
      total_benefits: Number(member.benefits?.total_benefits || 0).toFixed(2),
      outstanding_months: Number(member.outstanding?.outstanding_months || 0),
      outstanding_amount: Number(member.outstanding?.outstanding_amount || 0).toFixed(2),
      missing_periods: formatMissingPeriods(member.outstanding?.missing_periods || []),
      last_payment_date: member.payments?.last_payment_date || '',
      last_benefit_date: member.benefits?.last_benefit_date || ''
    }
  })
}

const downloadFile = (content, filename, mimeType) => {
  const blob = new Blob([content], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

const exportCsv = () => {
  const rows = buildExportRows()
  const headers = [
    'Membership No',
    'Name',
    'NIC',
    'Contact',
    'City',
    'Status',
    'Status Reason',
    'Membership Date',
    'Date Added',
    'Dependents Count',
    'Dependents',
    'Total Paid',
    'Total Benefits',
    'Outstanding Months',
    'Outstanding Amount',
    'Missing Periods',
    'Last Payment',
    'Last Benefit'
  ]

  const escapeCsv = (value) => {
    const text = String(value ?? '')
    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
      return `"${text.replace(/"/g, '""')}"`
    }
    return text
  }

  const lines = [
    headers.join(','),
    ...rows.map((r) =>
      [
        r.membership_number,
        r.name,
        r.nic,
        r.contact_number,
        r.city,
        r.status,
        r.status_reason,
        r.membership_date,
        r.date_added,
        r.dependents_count,
        r.dependents,
        r.total_paid,
        r.total_benefits,
        r.outstanding_months,
        r.outstanding_amount,
        r.missing_periods,
        r.last_payment_date,
        r.last_benefit_date
      ]
        .map(escapeCsv)
        .join(',')
    )
  ]

  const content = `\uFEFF${lines.join('\n')}`
  downloadFile(content, 'member_full_detailed_report.csv', 'text/csv;charset=utf-8;')
}

const exportExcel = () => {
  const rows = buildExportRows()
  const now = new Date()
  const dateLabel = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

  const escapeHtml = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')

  const bodyRows = rows
    .map(
      (r) => `
      <tr>
        <td>${escapeHtml(r.membership_number)}</td>
        <td>${escapeHtml(r.name)}</td>
        <td>${escapeHtml(r.nic)}</td>
        <td>${escapeHtml(r.contact_number)}</td>
        <td>${escapeHtml(r.city)}</td>
        <td>${escapeHtml(r.status)}</td>
        <td>${escapeHtml(r.status_reason)}</td>
        <td>${escapeHtml(r.membership_date)}</td>
        <td>${escapeHtml(r.date_added)}</td>
        <td>${escapeHtml(r.dependents_count)}</td>
        <td>${escapeHtml(r.dependents)}</td>
        <td>${escapeHtml(r.total_paid)}</td>
        <td>${escapeHtml(r.total_benefits)}</td>
        <td>${escapeHtml(r.outstanding_months)}</td>
        <td>${escapeHtml(r.outstanding_amount)}</td>
        <td>${escapeHtml(r.missing_periods)}</td>
        <td>${escapeHtml(r.last_payment_date)}</td>
        <td>${escapeHtml(r.last_benefit_date)}</td>
      </tr>`
    )
    .join('')

  const tableHtml = `
    <html>
      <head>
        <meta charset="utf-8" />
        <style>
          table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
          th, td { border: 1px solid #dcdcdc; padding: 6px; text-align: left; }
          th { background: #eef4ff; color: #1e40af; font-weight: 700; }
          caption { text-align: left; font-size: 16px; font-weight: 700; margin-bottom: 8px; }
        </style>
      </head>
      <body>
        <table>
          <caption>Member Full Detailed Report (${escapeHtml(dateLabel)})</caption>
          <thead>
            <tr>
              <th>Membership No</th>
              <th>Name</th>
              <th>NIC</th>
              <th>Contact</th>
              <th>City</th>
              <th>Status</th>
              <th>Status Reason</th>
              <th>Membership Date</th>
              <th>Date Added</th>
              <th>Dependents Count</th>
              <th>Dependents</th>
              <th>Total Paid</th>
              <th>Total Benefits</th>
              <th>Outstanding Months</th>
              <th>Outstanding Amount</th>
              <th>Missing Periods</th>
              <th>Last Payment</th>
              <th>Last Benefit</th>
            </tr>
          </thead>
          <tbody>${bodyRows}</tbody>
        </table>
      </body>
    </html>`

  downloadFile(tableHtml, 'member_full_detailed_report.xls', 'application/vnd.ms-excel')
}

onMounted(() => {
  loadReport()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.mt-4 { margin-top: 1.5rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.text-center { text-align: center; }

.report-page {
  width: 100%;
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.report-subtitle {
  margin: 0;
  opacity: 0.9;
  font-size: 0.92rem;
}

.report-actions {
  display: grid;
  grid-template-columns: repeat(5, minmax(120px, 1fr));
  gap: 0.5rem;
}

.report-actions .btn {
  color: #fff;
  border-color: rgba(255, 255, 255, 0.55);
  background: rgba(255, 255, 255, 0.12);
}

.report-actions .btn:hover {
  color: #fff;
  border-color: rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.2);
}

.report-actions .btn:disabled {
  opacity: 0.65;
  color: rgba(255, 255, 255, 0.85);
  border-color: rgba(255, 255, 255, 0.35);
  background: rgba(255, 255, 255, 0.08);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
}

.summary-card {
  padding: 1rem 1.25rem;
}

.summary-label {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.88rem;
}

.summary-value {
  margin: 0.3rem 0 0;
  color: var(--primary-color);
  font-weight: 700;
  font-size: 1.35rem;
}

.filter-panel {
  padding: 1rem;
}

.lookup-panel {
  padding: 1rem;
}

.outstanding-panel {
  padding: 1rem;
}

.panel-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 0.85rem;
}

.panel-heading h3 {
  margin: 0;
  color: var(--primary-color);
}

.lookup-results {
  margin-top: 0.85rem;
}

.lookup-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.lookup-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  padding: 0.7rem 0.8rem;
  border: 1px solid rgba(37, 99, 235, 0.14);
  border-radius: 8px;
  background: rgba(37, 99, 235, 0.04);
}

.lookup-item-main {
  min-width: 0;
}

.report-table-wrap,
.detail-panel {
  overflow: hidden;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  min-width: 900px;
}

.report-table th,
.report-table td {
  padding: 0.7rem 0.75rem;
  text-align: left;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  vertical-align: top;
}

.report-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  color: var(--primary-color);
  font-weight: 600;
  background: #eff6ff;
}

.report-table tbody tr:nth-child(even) {
  background: rgba(37, 99, 235, 0.015);
}

.report-table tbody tr:hover {
  background: rgba(37, 99, 235, 0.07);
}

.row-selected {
  outline: 2px solid rgba(37, 99, 235, 0.35);
}

.row-subtext {
  margin-top: 0.2rem;
  font-size: 0.76rem;
  color: var(--text-muted);
  line-height: 1.25;
}

.detail-panel {
  padding: 1rem;
}

.detail-header {
  margin-bottom: 1rem;
}

.detail-header h3 {
  margin: 0;
  color: var(--primary-color);
}

.detail-header p {
  margin: 0.25rem 0 0;
  color: var(--text-muted);
}

.history-filter {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.history-filter .form-label {
  margin: 0;
  min-width: 96px;
}

.history-filter .form-select {
  max-width: 240px;
}

.extract-sheet {
  border: 1px solid rgba(37, 99, 235, 0.15);
  border-radius: 10px;
  background: rgba(37, 99, 235, 0.03);
  overflow: hidden;
}

.extract-title {
  margin: 0;
  padding: 0.75rem 0.9rem;
  color: var(--primary-dark);
  background: rgba(37, 99, 235, 0.08);
  border-bottom: 1px solid rgba(37, 99, 235, 0.14);
}

.extract-row {
  display: grid;
  grid-template-columns: minmax(170px, 220px) 1fr;
  gap: 0.75rem;
  padding: 0.6rem 0.9rem;
  border-bottom: 1px solid rgba(37, 99, 235, 0.12);
}

.extract-row:last-child {
  border-bottom: none;
}

.extract-label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 600;
}

.extract-value {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-main);
}

.detail-section h4 {
  margin-bottom: 0.6rem;
  color: var(--primary-dark);
}

.outstanding-summary {
  padding: 0.75rem 0.85rem;
  margin-bottom: 0.85rem;
  border: 1px solid rgba(220, 53, 69, 0.18);
  border-radius: 8px;
  background: rgba(220, 53, 69, 0.06);
  color: #7f1d1d;
  font-size: 0.9rem;
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

@media (max-width: 992px) {
  .report-actions {
    grid-template-columns: repeat(3, minmax(110px, 1fr));
  }
}

@media (max-width: 768px) {
  .report-page {
    font-size: 0.95rem;
  }

  .report-header {
    flex-direction: column;
    align-items: stretch;
  }

  .report-actions {
    width: 100%;
    grid-template-columns: repeat(2, minmax(120px, 1fr));
  }

  .report-actions .btn {
    width: 100%;
    padding-inline: 0.65rem;
  }

  .extract-row {
    grid-template-columns: 1fr;
    gap: 0.25rem;
  }

  .lookup-item {
    flex-direction: column;
    align-items: stretch;
  }

  .history-filter {
    flex-direction: column;
    align-items: stretch;
  }

  .history-filter .form-select {
    max-width: none;
    width: 100%;
  }
}

@media (max-width: 520px) {
  .report-actions {
    grid-template-columns: 1fr;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .summary-card {
    padding: 0.85rem 1rem;
  }
}

@media print {
  .no-print-detail,
  .report-actions,
  .btn,
  .filter-panel,
  .dashboard-navbar,
  .dashboard-sidebar {
    display: none !important;
  }

  .report-page,
  .main-content-wrapper,
  .dashboard-main {
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
    overflow: visible !important;
  }

  .detail-panel {
    display: block !important;
    border: 1px solid #ddd !important;
    box-shadow: none !important;
    background: #fff !important;
  }

  .report-table th,
  .report-table td {
    font-size: 11px;
    padding: 6px;
  }
}
</style>
