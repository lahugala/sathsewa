<template>
  <DashboardLayout>
    <div class="members-container animate-fade-in">
      <div class="header-bar mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Society Members</h2>
        <button class="btn bg-emerald-600 hover:bg-emerald-700 text-white shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 border-none flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold" @click="openAddModal">
          <UserPlus size="18" />
          Add New Member
        </button>
      </div>

      <div class="glass-panel" style="overflow: hidden;">
        <div class="search-bar">
          <input type="search" v-model="searchQuery" class="form-control" placeholder="Search by name, NIC or city...">
        </div>
        <div class="table-responsive" style="padding: 0 1rem 1rem 1rem;">
          <table class="data-table compact responsive-card-table" v-if="filteredMembers.length > 0">
            <thead>
              <tr>
                <th>Mem. No</th>
                <th>Name</th>
                <th>NIC</th>
                <th>City</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Loan</th>
                <th>Mem. Date</th>
                <th class="sticky-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in paginatedMembers" :key="member.id">
                <td data-label="Mem. No">{{ member.membership_number || '-' }}</td>
                <td data-label="Name">{{ member.name }}</td>
                <td data-label="NIC">{{ member.nic }}</td>
                <td data-label="City">{{ member.city }}</td>
                <td data-label="Contact">{{ member.contact_number }}</td>
                <td data-label="Status">
                  <span class="status-badge" :class="statusClass(member.status)">
                    {{ member.status || 'Active' }}
                  </span>
                  <div v-if="member.status_reason" class="status-reason">{{ member.status_reason }}</div>
                </td>
                <td data-label="Loan">
                  <button
                    v-if="hasLoanHistory(member)"
                    type="button"
                    class="loan-indicator"
                    :class="loanClass(member)"
                    @click="openLoansModal(member)"
                    title="Open loans"
                  >
                    <HandCoins size="14" />
                    <span>{{ loanLabel(member) }}</span>
                  </button>
                  <span v-else class="loan-none">No loan</span>
                  <div v-if="hasLoanHistory(member)" class="loan-balance">
                    {{ formatCurrency(loanBalance(member)) }} balance
                  </div>
                </td>
                <td data-label="Mem. Date">{{ member.membership_date || member.date_added }}</td>
                <td class="sticky-actions" data-label="Actions" style="white-space: nowrap;">
                  <button class="btn btn-sm btn-outline" @click="openDetails(member.id)" style="margin-right: 0.25rem; padding: 0.4rem;" title="Member Details">
                    <FileText size="16" />
                  </button>
                  <button class="btn btn-sm btn-success" @click="openLedgerModal(member)" style="margin-right: 0.25rem; padding: 0.4rem;" title="Ledger">
                    <BookOpen size="16" />
                  </button>
                  <button class="btn btn-sm btn-outline" @click="openBenefitsModal(member)" style="margin-right: 0.25rem; padding: 0.4rem; background-color: var(--primary-dark); color: white; border-color: var(--primary-dark);" title="Benefits & Payouts">
                    <Gift size="16" />
                  </button>
                  <button class="btn btn-sm btn-outline" @click="openLoansModal(member)" style="margin-right: 0.25rem; padding: 0.4rem; background-color: #92400e; color: white; border-color: #92400e;" title="Loans">
                    <HandCoins size="16" />
                  </button>
                  <button class="btn btn-sm btn-outline" @click="openEditModal(member.id)" style="margin-right: 0.25rem; padding: 0.4rem;" title="Edit">
                    <Pencil size="16" />
                  </button>
                  <button class="btn btn-sm btn-danger" @click="deleteMember(member.id)" style="padding: 0.4rem;" title="Delete">
                    <Trash2 size="16" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else-if="loading" class="text-center py-4">Loading members...</p>
          <p v-else class="text-muted text-center py-4">No members found.</p>
        </div>
        <div v-if="filteredMembers.length > 0" class="pagination-bar">
          <div class="pagination-summary">
            Showing {{ paginationStart }}-{{ paginationEnd }} of {{ filteredMembers.length }}
          </div>
          <div class="pagination-controls">
            <label class="page-size">
              Rows
              <select v-model.number="pageSize">
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
              </select>
            </label>
            <button class="page-btn" type="button" :disabled="currentPage === 1" @click="currentPage--">
              Prev
            </button>
            <span class="page-count">{{ currentPage }} / {{ totalPages }}</span>
            <button class="page-btn" type="button" :disabled="currentPage === totalPages" @click="currentPage++">
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Application Modal -->
    <ApplicationModal 
      :show="isModalOpen" 
      :editId="editingId"
      @close="closeModal" 
      @success="handleSuccess"
    />

    <!-- Payment Ledger Modal -->
    <PaymentLedgerModal
      :show="isLedgerOpen"
      :member="selectedMember"
      @close="closeLedgerModal"
    />

    <!-- Benefits/Payouts Modal -->
    <BenefitsModal
      :show="isBenefitsOpen"
      :member="selectedMember"
      @close="closeBenefitsModal"
    />

    <!-- Loans Modal -->
    <LoansModal
      :show="isLoansOpen"
      :member="selectedMember"
      @close="closeLoansModal"
    />

    <!-- Full Screen Member Details Modal -->
    <MemberDetailsModal
      :show="isDetailsOpen"
      :member-id="selectedDetailId"
      @close="closeDetailsModal"
    />
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import DashboardLayout from '../components/DashboardLayout.vue'
import ApplicationModal from '../components/ApplicationModal.vue'
import PaymentLedgerModal from '../components/PaymentLedgerModal.vue'
import BenefitsModal from '../components/BenefitsModal.vue'
import LoansModal from '../components/LoansModal.vue'
import MemberDetailsModal from '../components/MemberDetailsModal.vue'
import { BookOpen, FileText, Gift, HandCoins, Pencil, Trash2, UserPlus } from 'lucide-vue-next'
import { alertError, alertSuccess, confirmWarning } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const members = ref([])
const loading = ref(true)
const isModalOpen = ref(false)
const editingId = ref(null)
const searchQuery = ref('')
const isLedgerOpen = ref(false)
const isBenefitsOpen = ref(false)
const isLoansOpen = ref(false)
const isDetailsOpen = ref(false)
const selectedDetailId = ref(null)
const selectedMember = ref(null)
const currentPage = ref(1)
const pageSize = ref(10)

const fetchMembers = async () => {
  loading.value = true
  try {
    const res = await apiFetch('/api/get_members.php')
    const data = await res.json()
    if (data.success) {
      members.value = data.members
    } else {
      alertError('Failed to load members', data.message || 'Unable to load member list.')
    }
  } catch (error) {
    console.error('Error fetching members:', error)
    alertError('Network error', 'Network error while loading members.')
  } finally {
    loading.value = false
  }
}

const filteredMembers = computed(() => {
  if (!searchQuery.value) return members.value
  const query = searchQuery.value.toLowerCase()
  return members.value.filter(m => 
    m.name.toLowerCase().includes(query) ||
    m.nic.toLowerCase().includes(query) ||
    m.city.toLowerCase().includes(query) ||
    String(m.status || '').toLowerCase().includes(query) ||
    String(m.status_reason || '').toLowerCase().includes(query) ||
    loanSearchText(m).includes(query) ||
    (m.membership_number && m.membership_number.toLowerCase().includes(query))
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredMembers.value.length / pageSize.value)))

const paginatedMembers = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredMembers.value.slice(start, start + pageSize.value)
})

const paginationStart = computed(() => {
  if (!filteredMembers.value.length) return 0
  return (currentPage.value - 1) * pageSize.value + 1
})

const paginationEnd = computed(() => Math.min(currentPage.value * pageSize.value, filteredMembers.value.length))

watch([filteredMembers, pageSize], () => {
  if (currentPage.value > totalPages.value) {
    currentPage.value = totalPages.value
  }
  if (currentPage.value < 1) {
    currentPage.value = 1
  }
})

watch(searchQuery, () => {
  currentPage.value = 1
})

const statusClass = (status) => {
  return {
    'status-active': status === 'Active' || !status,
    'status-inactive': status === 'Inactive',
    'status-suspended': status === 'Suspended'
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-LK', {
    style: 'currency',
    currency: 'LKR',
    minimumFractionDigits: 2
  }).format(Number(value || 0))
}

const loanSummary = (member) => member?.loans || {}

const hasLoanHistory = (member) => Number(loanSummary(member).loans_count || 0) > 0

const loanBalance = (member) => Number(loanSummary(member).total_balance || 0)

const loanLabel = (member) => {
  const summary = loanSummary(member)
  if (!hasLoanHistory(member)) return 'No loan'
  if (Number(summary.overdue_loans_count || 0) > 0) return 'Overdue'
  if (loanBalance(member) > 0) return 'Active loan'
  return 'Settled'
}

const loanClass = (member) => ({
  'loan-overdue': loanLabel(member) === 'Overdue',
  'loan-active': loanLabel(member) === 'Active loan',
  'loan-settled': loanLabel(member) === 'Settled'
})

const loanSearchText = (member) => {
  const summary = loanSummary(member)
  return [
    loanLabel(member),
    hasLoanHistory(member) ? 'loan issued' : 'no loan',
    Number(summary.active_loans_count || 0) > 0 ? 'active' : '',
    Number(summary.overdue_loans_count || 0) > 0 ? 'overdue fine due' : '',
    loanBalance(member) > 0 ? String(loanBalance(member)) : ''
  ].join(' ').toLowerCase()
}

const openAddModal = () => {
  editingId.value = null
  isModalOpen.value = true
}

const openEditModal = (id) => {
  editingId.value = id
  isModalOpen.value = true
}

const openDetails = (id) => {
  selectedDetailId.value = id
  isDetailsOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  editingId.value = null
}

const openLedgerModal = (member) => {
  selectedMember.value = member
  isLedgerOpen.value = true
}

const closeLedgerModal = () => {
  isLedgerOpen.value = false
  selectedMember.value = null
}

const openBenefitsModal = (member) => {
  selectedMember.value = member
  isBenefitsOpen.value = true
}

const closeBenefitsModal = () => {
  isBenefitsOpen.value = false
  selectedMember.value = null
}

const openLoansModal = (member) => {
  selectedMember.value = member
  isLoansOpen.value = true
}

const closeLoansModal = () => {
  isLoansOpen.value = false
  selectedMember.value = null
  fetchMembers()
}

const closeDetailsModal = () => {
  isDetailsOpen.value = false
  selectedDetailId.value = null
}

const handleSuccess = (message) => {
  alertSuccess('Member saved', message || 'Member saved successfully.')
  fetchMembers() // Refresh list
}

const deleteMember = async (id) => {
  const result = await confirmWarning({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    confirmButtonText: 'Yes, delete it!'
  })

  if (result.isConfirmed) {
    try {
      const res = await apiFetch('/api/delete_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      })
      const data = await res.json()
      if (data.success) {
        alertSuccess('Deleted!', 'Member deleted successfully.')
        fetchMembers()
      } else {
        alertError('Error!', data.message || 'Failed to delete member')
      }
    } catch (e) {
      alertError('Error!', 'Network error deleting member')
    }
  }
}

onMounted(() => {
  fetchMembers()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.text-center { text-align: center; }

.search-bar {
  padding: 1rem;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.table-responsive {
  overflow-x: auto;
  overflow-y: auto;
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 10px;
  max-height: 64vh;
}

.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0 1rem 1rem;
  color: var(--text-muted);
  font-size: 0.88rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.page-size {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-weight: 600;
}

.page-size select {
  height: 2.15rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 8px;
  padding: 0 0.55rem;
  color: var(--text-color);
  background: #fff;
}

.page-btn {
  height: 2.15rem;
  min-width: 4.2rem;
  border: 1px solid rgba(20, 184, 166, 0.32);
  border-radius: 8px;
  background: #fff;
  color: var(--primary-dark);
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, opacity 0.2s ease;
}

.page-btn:not(:disabled):hover {
  background: rgba(20, 184, 166, 0.1);
}

.page-btn:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.page-count {
  min-width: 3.5rem;
  text-align: center;
  color: var(--text-color);
  font-weight: 700;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}

.data-table th, .data-table td {
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
  background: #eefaf7;
}

.data-table tbody tr:nth-child(even) {
  background: rgba(20, 184, 166, 0.025);
}

.data-table tbody tr:hover {
  background: rgba(20, 184, 166, 0.09);
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
  background: #eefaf7;
}

.data-table tbody td:first-child,
.data-table tbody td:last-child {
  background: #fff;
}

.data-table tbody tr:nth-child(even) td:first-child,
.data-table tbody tr:nth-child(even) td:last-child {
  background: rgba(20, 184, 166, 0.025);
}

.data-table tbody tr:hover td:first-child,
.data-table tbody tr:hover td:last-child {
  background: rgba(20, 184, 166, 0.09);
}

.data-table.compact th,
.data-table.compact td {
  padding: 0.58rem 0.7rem;
  font-size: 0.88rem;
}

@media (max-width: 768px) {
  .data-table th:first-child,
  .data-table td:first-child {
    position: static;
    box-shadow: none;
  }

  .data-table th:last-child,
  .data-table td:last-child {
    position: static;
    box-shadow: none;
  }

  .data-table th.sticky-actions,
  .data-table td.sticky-actions {
    position: sticky;
    right: 0;
    z-index: 3;
    box-shadow: -1px 0 0 rgba(0, 0, 0, 0.08);
  }

  .data-table thead th.sticky-actions {
    z-index: 4;
    background: #eefaf7;
  }

  .data-table tbody td.sticky-actions {
    background: #fff;
  }

  .data-table tbody tr:nth-child(even) td.sticky-actions {
    background: rgba(20, 184, 166, 0.025);
  }

  .data-table tbody tr:hover td.sticky-actions {
    background: rgba(20, 184, 166, 0.09);
  }
}

@media (max-width: 640px) {
  .table-responsive {
    max-height: 70vh;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
  }

  .pagination-bar {
    align-items: stretch;
    flex-direction: column;
  }

  .pagination-controls {
    justify-content: space-between;
    flex-wrap: wrap;
  }
}

.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.875rem;
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

.status-reason {
  max-width: 180px;
  margin-top: 0.25rem;
  color: var(--text-muted);
  font-size: 0.72rem;
  line-height: 1.25;
  white-space: normal;
}

.loan-indicator {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
  min-width: 98px;
  min-height: 28px;
  padding: 0.25rem 0.55rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-family: inherit;
  font-size: 0.74rem;
  font-weight: 750;
  cursor: pointer;
  white-space: nowrap;
}

.loan-indicator:hover {
  filter: brightness(0.96);
}

.loan-active {
  background: rgba(20, 184, 166, 0.13);
  border-color: rgba(20, 184, 166, 0.28);
  color: var(--primary-dark);
}

.loan-overdue {
  background: rgba(220, 53, 69, 0.13);
  border-color: rgba(220, 53, 69, 0.26);
  color: #b42332;
}

.loan-settled {
  background: rgba(25, 135, 84, 0.13);
  border-color: rgba(25, 135, 84, 0.24);
  color: #126742;
}

.loan-none {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  color: var(--text-muted);
  font-size: 0.78rem;
  font-weight: 650;
}

.loan-balance {
  max-width: 150px;
  margin-top: 0.25rem;
  color: var(--text-muted);
  font-size: 0.72rem;
  line-height: 1.25;
  white-space: normal;
}
</style>

