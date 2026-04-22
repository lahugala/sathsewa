<template>
  <DashboardLayout>
    <div class="members-container animate-fade-in">
      <div class="header-bar mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Society Members</h2>
        <button class="btn btn-success" @click="openAddModal">+ Add New Member</button>
      </div>

      <div v-if="successMessage" class="alert alert-success mb-4">
        {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="alert alert-error mb-4">
        {{ errorMessage }}
      </div>

      <div class="glass-panel" style="overflow: hidden;">
        <div class="search-bar">
          <input type="search" v-model="searchQuery" class="form-control" placeholder="Search by name, NIC or city...">
        </div>
        <div class="table-responsive" style="padding: 0 1rem 1rem 1rem;">
          <table class="data-table compact" v-if="filteredMembers.length > 0">
            <thead>
              <tr>
                <th>Mem. No</th>
                <th>Name</th>
                <th>NIC</th>
                <th>City</th>
                <th>Contact</th>
                <th>Mem. Date</th>
                <th class="sticky-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in filteredMembers" :key="member.id">
                <td>{{ member.membership_number || '-' }}</td>
                <td>{{ member.name }}</td>
                <td>{{ member.nic }}</td>
                <td>{{ member.city }}</td>
                <td>{{ member.contact_number }}</td>
                <td>{{ member.membership_date || member.date_added }}</td>
                <td class="sticky-actions" style="white-space: nowrap;">
                  <button class="btn btn-sm btn-success" @click="openLedgerModal(member)" style="margin-right: 0.25rem; padding: 0.4rem;" title="Ledger">
                    <BookOpen size="16" />
                  </button>
                  <button class="btn btn-sm btn-outline" @click="openBenefitsModal(member)" style="margin-right: 0.25rem; padding: 0.4rem; background-color: var(--primary-dark); color: white; border-color: var(--primary-dark);" title="Benefits & Payouts">
                    <Gift size="16" />
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
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import DashboardLayout from '../components/DashboardLayout.vue'
import ApplicationModal from '../components/ApplicationModal.vue'
import PaymentLedgerModal from '../components/PaymentLedgerModal.vue'
import BenefitsModal from '../components/BenefitsModal.vue'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'
import { BookOpen, Gift, Pencil, Trash2 } from 'lucide-vue-next'

const members = ref([])
const loading = ref(true)
const isModalOpen = ref(false)
const editingId = ref(null)
const successMessage = ref('')
const errorMessage = ref('')
const searchQuery = ref('')
const isLedgerOpen = ref(false)
const isBenefitsOpen = ref(false)
const selectedMember = ref(null)

const fetchMembers = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/get_members.php')
    const data = await res.json()
    if (data.success) {
      members.value = data.members
    }
  } catch (error) {
    console.error('Error fetching members:', error)
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
    (m.membership_number && m.membership_number.toLowerCase().includes(query))
  )
})

const openAddModal = () => {
  editingId.value = null
  isModalOpen.value = true
}

const openEditModal = (id) => {
  editingId.value = id
  isModalOpen.value = true
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

const handleSuccess = (message) => {
  successMessage.value = message
  fetchMembers() // Refresh list
  setTimeout(() => {
    successMessage.value = ''
  }, 5000)
}

const deleteMember = async (id) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it!'
  })

  if (result.isConfirmed) {
    try {
      const res = await fetch('/api/delete_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      })
      const data = await res.json()
      if (data.success) {
        Swal.fire('Deleted!', 'Member deleted successfully.', 'success')
        fetchMembers()
      } else {
        Swal.fire('Error!', data.message || 'Failed to delete member', 'error')
      }
    } catch (e) {
      Swal.fire('Error!', 'Network error deleting member', 'error')
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
  background: #f8f1f1;
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
  background: #f8f1f1;
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
    background: #f8f1f1;
  }

  .data-table tbody td.sticky-actions {
    background: #fff;
  }

  .data-table tbody tr:nth-child(even) td.sticky-actions {
    background: rgba(37, 99, 235, 0.015);
  }

  .data-table tbody tr:hover td.sticky-actions {
    background: rgba(37, 99, 235, 0.07);
  }
}

.alert {
  padding: 1rem;
  border-radius: 8px;
  font-weight: 500;
}

.alert-success {
  background-color: rgba(25, 135, 84, 0.1);
  color: var(--success);
  border: 1px solid rgba(25, 135, 84, 0.2);
}

.alert-error {
  background-color: rgba(220, 53, 69, 0.1);
  color: var(--error);
  border: 1px solid rgba(220, 53, 69, 0.2);
}

.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.875rem;
}
</style>

